<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_quizbuilder\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Imports questions from Moodle XML into a question bank of a course.
 *
 * Categories in the file ($course$/top/...) are created in that bank.
 *
 * @package    local_quizbuilder
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_questions extends external_api {
    /** @var string[] Question types (as in Moodle XML) that store correct, partially correct and incorrect feedback. */
    public const COMBINED_FEEDBACK_QTYPES = [
        'multichoice', 'matching', 'randomsamatch', 'ordering', 'ddwtos', 'gapselect', 'ddimageortext', 'ddmarker',
    ];

    /** @var string[] The combined feedback fields. */
    public const COMBINED_FEEDBACK_FIELDS = ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'rulesversion' => new external_value(
                PARAM_ALPHANUM,
                'Version from local_quizbuilder_get_rules: confirms that the rules for writing questions were read'
            ),
            'xml' => new external_value(
                PARAM_RAW,
                'Questions in Moodle XML format. Question types with combined feedback (' .
                    implode(', ', self::COMBINED_FEEDBACK_QTYPES) . ') need correctfeedback, ' .
                    'partiallycorrectfeedback and incorrectfeedback, each written for that question: ' .
                    'confirm what was right, say what to check again, give a hint without revealing the answer. ' .
                    'See local_quizbuilder_get_rules.'
            ),
            'bankcmid' => new external_value(
                PARAM_INT,
                'Course module ID of the question bank (0 = first bank of the course, created if missing)',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Imports the questions.
     *
     * @param int $courseid
     * @param string $rulesversion
     * @param string $xml
     * @param int $bankcmid
     * @return array
     */
    public static function execute(int $courseid, string $rulesversion, string $xml, int $bankcmid = 0): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid, 'rulesversion' => $rulesversion, 'xml' => $xml, 'bankcmid' => $bankcmid]
        );

        $course = get_course($params['courseid']);
        $coursecontext = \context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('moodle/course:manageactivities', $coursecontext);

        $bankcm = self::find_bank($course, $params['bankcmid']);
        $context = \context_module::instance($bankcm->id);
        require_capability('moodle/question:add', $context);

        if ($params['rulesversion'] !== get_rules::version()) {
            throw new \moodle_exception('rulesnotread', 'local_quizbuilder');
        }
        self::check_combined_feedback($params['xml']);

        $category = question_get_default_category($context->id, true);

        $dir = make_request_directory();
        $file = $dir . '/import.xml';
        file_put_contents($file, $params['xml']);

        $qformat = new \qformat_xml();
        $qformat->setCategory($category);
        $qformat->setContexts([$context]);
        $qformat->setCourse($course);
        $qformat->setFilename($file);
        $qformat->setRealfilename('import.xml');
        $qformat->setMatchgrades('error');
        $qformat->setCatfromfile(true);
        $qformat->setContextfromfile(false);
        $qformat->setStoponerror(true);

        // The import prints progress messages; keep them out of the webservice response.
        ob_start();
        $ok = $qformat->importpreprocess() && $qformat->importprocess() && $qformat->importpostprocess();
        $log = ob_get_clean();

        if (!$ok) {
            throw new \moodle_exception('importfailed', 'local_quizbuilder', '', trim(strip_tags($log)));
        }

        return [
            'bankcmid' => (int)$bankcm->id,
            'categoryid' => (int)$qformat->category->id,
            'count' => count($qformat->questionids),
            'questionids' => array_map('intval', array_values($qformat->questionids)),
        ];
    }

    /**
     * Refuses questions whose combined feedback is missing or empty.
     *
     * Moodle would store a missing field as NULL and fail with a database error, an empty one
     * gives students no feedback. The message tells the caller (often an AI assistant) which
     * questions to fix and what to write, before anything is imported.
     *
     * @param string $xml Moodle XML
     * @throws \moodle_exception missingfeedback
     */
    public static function check_combined_feedback(string $xml): void {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            // Broken XML: the importer reports the error.
            return;
        }

        $problems = [];
        $xpath = new \DOMXPath($doc);
        foreach ($xpath->query('/quiz/question') as $question) {
            $qtype = $question->getAttribute('type');
            if (!in_array($qtype, self::COMBINED_FEEDBACK_QTYPES)) {
                continue;
            }
            $missing = [];
            foreach (self::COMBINED_FEEDBACK_FIELDS as $field) {
                $text = $xpath->evaluate('string(' . $field . '/text)', $question);
                $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (preg_replace('/[\s\x{00A0}]+/u', '', $plain) === '') {
                    $missing[] = $field;
                }
            }
            if ($missing) {
                $name = trim($xpath->evaluate('string(name/text)', $question));
                $problems[] = '"' . ($name !== '' ? $name : $qtype) . '" (' . $qtype . '): ' . implode(', ', $missing);
            }
        }

        if ($problems) {
            throw new \moodle_exception('missingfeedback', 'local_quizbuilder', '', implode('; ', $problems));
        }
    }

    /**
     * Finds the question bank to import into.
     *
     * @param \stdClass $course
     * @param int $bankcmid
     * @return \cm_info|\stdClass
     */
    public static function find_bank(\stdClass $course, int $bankcmid) {
        $modinfo = get_fast_modinfo($course);
        if ($bankcmid) {
            $cm = $modinfo->get_cm($bankcmid);
            if ($cm->modname !== 'qbank') {
                throw new \moodle_exception('nobank', 'local_quizbuilder');
            }
            return $cm;
        }
        foreach ($modinfo->get_instances_of('qbank') as $cm) {
            return $cm;
        }
        $helper = '\core_question\local\bank\question_bank_helper';
        if (class_exists($helper) && method_exists($helper, 'get_default_open_instance_system_type')) {
            $cm = $helper::get_default_open_instance_system_type($course, true);
            if ($cm) {
                return $cm;
            }
        }
        throw new \moodle_exception('nobank', 'local_quizbuilder');
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'bankcmid' => new external_value(PARAM_INT, 'Course module ID of the question bank'),
            'categoryid' => new external_value(PARAM_INT, 'Category of the last imported question'),
            'count' => new external_value(PARAM_INT, 'Number of imported questions'),
            'questionids' => new external_multiple_structure(new external_value(PARAM_INT, 'Question ID')),
        ]);
    }
}

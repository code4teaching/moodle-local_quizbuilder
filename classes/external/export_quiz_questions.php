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
use core_external\external_single_structure;
use core_external\external_value;
use core_question\local\bank\question_version_status;

/**
 * Exports the questions of a quiz as Moodle XML, in the order of the quiz, together with its main settings.
 * All question types that Moodle can export are included (images as base64). Random slots are counted, not exported.
 *
 * @package    local_quizbuilder
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_quiz_questions extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the quiz'),
        ]);
    }

    /**
     * Exports the questions.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cm = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quiz:manage', $context);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = get_course($cm->course);

        $ids = [];
        $random = 0;
        foreach (\mod_quiz\question\bank\qbank_helper::get_question_structure($quiz->id, $context) as $slot) {
            if ($slot->qtype === 'random' || empty($slot->questionid)) {
                $random++;
            } else if (!in_array((int)$slot->questionid, $ids)) {
                $ids[] = (int)$slot->questionid;
            }
        }

        $format = new \qformat_xml();
        $format->setCourse($course);
        $xml = '';
        $skipped = 0;
        if ($ids) {
            $questions = question_load_questions($ids);
            foreach ($ids as $id) {
                if (empty($questions[$id]) || !is_object($questions[$id])) {
                    $skipped++;
                    continue;
                }
                $question = $questions[$id];
                question_require_capability_on($question, 'view');
                $entry = $DB->get_record_sql(
                    "SELECT qbe.idnumber, qc.contextid
                       FROM {question_versions} qv
                       JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                       JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                      WHERE qv.questionid = ?",
                    [$id],
                    MUST_EXIST
                );
                $question->contextid = $entry->contextid;
                $question->idnumber = $entry->idnumber;
                // The XML format writes the visibility as 0/1, as the question export does.
                $hidden = isset($question->status) && $question->status === question_version_status::QUESTION_STATUS_HIDDEN;
                $question->status = $hidden ? 1 : 0;
                $question->hidden = $question->status;
                $xml .= $format->writequestion($question);
            }
        }
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n" . $xml . "</quiz>\n";

        $shuffle = (int)$DB->get_field('quiz_sections', 'shufflequestions', ['quizid' => $quiz->id, 'firstslot' => 1]);
        return [
            'xml' => $xml,
            'count' => count($ids) - $skipped,
            'randomcount' => $random,
            'name' => format_string($quiz->name),
            'timelimit' => (int)$quiz->timelimit,
            'attempts' => (int)$quiz->attempts,
            'grademethod' => (int)$quiz->grademethod,
            'preferredbehaviour' => (string)$quiz->preferredbehaviour,
            'shuffleanswers' => (int)$quiz->shuffleanswers,
            'shufflequestions' => $shuffle,
            'questionsperpage' => (int)$quiz->questionsperpage,
            'grade' => (float)$quiz->grade,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'xml' => new external_value(PARAM_RAW, 'The questions as Moodle XML, in the order of the quiz'),
            'count' => new external_value(PARAM_INT, 'Number of exported questions'),
            'randomcount' => new external_value(PARAM_INT, 'Number of random slots (not exported)'),
            'name' => new external_value(PARAM_TEXT, 'Name of the quiz'),
            'timelimit' => new external_value(PARAM_INT, 'Time limit in seconds, 0 = none'),
            'attempts' => new external_value(PARAM_INT, 'Attempts allowed, 0 = unlimited'),
            'grademethod' => new external_value(PARAM_INT, 'Grading method (1 highest, 2 average, 3 first, 4 last)'),
            'preferredbehaviour' => new external_value(PARAM_ALPHANUMEXT, 'Question behaviour'),
            'shuffleanswers' => new external_value(PARAM_INT, 'Shuffle within questions (0/1)'),
            'shufflequestions' => new external_value(PARAM_INT, 'Shuffle questions (0/1)'),
            'questionsperpage' => new external_value(PARAM_INT, 'Questions per page, 0 = all on one page'),
            'grade' => new external_value(PARAM_FLOAT, 'Maximum grade of the quiz'),
        ]);
    }
}

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
 * Replaces all questions of a quiz with new ones, for example after the teacher revised the quiz file.
 * Name, settings, place and visibility of the quiz stay. Refused once someone has attempted the quiz,
 * because Moodle does not allow changing the questions then.
 *
 * @package    local_quizbuilder
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class replace_quiz_questions extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the quiz'),
            'questionids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Question ID'),
                'The new questions, in order',
                VALUE_DEFAULT,
                []
            ),
            'deleteold' => new external_value(
                PARAM_INT,
                'Delete the old questions from the question bank if no other quiz uses them (0/1)',
                VALUE_DEFAULT,
                1
            ),
            'dryrun' => new external_value(
                PARAM_INT,
                '1 = only check that the questions could be replaced (quiz exists, no attempts, capability)',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Replaces the questions.
     *
     * @param int $cmid
     * @param array $questionids
     * @param int $deleteold
     * @param int $dryrun
     * @return array
     */
    public static function execute(int $cmid, array $questionids = [], int $deleteold = 1, int $dryrun = 0): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/questionlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'questionids' => $questionids, 'deleteold' => $deleteold, 'dryrun' => $dryrun,
        ]);

        $cm = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quiz:manage', $context);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        if (quiz_has_attempts($quiz->id)) {
            throw new \moodle_exception('quizhasattempts', 'local_quizbuilder');
        }
        $old = self::question_bank_entries($quiz->id);
        if ($params['dryrun']) {
            return ['count' => 0, 'removed' => count($old), 'deleted' => 0,
                'sumgrades' => (float)$quiz->sumgrades];
        }
        if (!$params['questionids']) {
            throw new \moodle_exception('noquestions', 'local_quizbuilder');
        }

        // Keep the setting "shuffle questions" of the quiz.
        $shuffle = (int)$DB->get_field('quiz_sections', 'shufflequestions', ['quizid' => $quiz->id, 'firstslot' => 1]);

        $structure = \mod_quiz\quiz_settings::create($quiz->id)->get_structure();
        foreach (array_reverse($structure->get_slots()) as $slot) {
            $structure->remove_slot($slot->slot);
        }

        $added = add_quiz_questions::execute($cm->id, $params['questionids'], 0, 0, $shuffle);

        $deleted = 0;
        if ($params['deleteold']) {
            foreach ($old as $entryid) {
                if ($DB->record_exists('question_references', ['questionbankentryid' => $entryid])) {
                    continue; // Another quiz still uses it.
                }
                $versions = $DB->get_fieldset_select('question_versions', 'questionid', 'questionbankentryid = ?', [$entryid]);
                foreach ($versions as $questionid) {
                    question_require_capability_on($questionid, 'edit');
                    question_delete_question($questionid);
                }
                $deleted++;
            }
        }

        return ['count' => $added['count'], 'removed' => count($old), 'deleted' => $deleted,
            'sumgrades' => $added['sumgrades']];
    }

    /**
     * Question bank entries used by the fixed slots of a quiz.
     *
     * @param int $quizid
     * @return int[]
     */
    public static function question_bank_entries(int $quizid): array {
        global $DB;
        $sql = "SELECT DISTINCT qr.questionbankentryid
                  FROM {quiz_slots} s
                  JOIN {question_references} qr ON qr.itemid = s.id
                                               AND qr.component = 'mod_quiz'
                                               AND qr.questionarea = 'slot'
                 WHERE s.quizid = ?";
        return array_map('intval', $DB->get_fieldset_sql($sql, [$quizid]));
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'count' => new external_value(PARAM_INT, 'Number of questions now in the quiz'),
            'removed' => new external_value(PARAM_INT, 'Number of questions taken out of the quiz'),
            'deleted' => new external_value(PARAM_INT, 'Number of old questions deleted from the question bank'),
            'sumgrades' => new external_value(PARAM_FLOAT, 'Total marks of the quiz'),
        ]);
    }
}

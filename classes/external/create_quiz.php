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

/**
 * Creates a quiz in a course section.
 *
 * @package    local_quizbuilder
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_quiz extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number (0-based)'),
            'name' => new external_value(PARAM_TEXT, 'Quiz name'),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, 'Visible (1) or hidden (0)', VALUE_DEFAULT, 0),
            'attempts' => new external_value(PARAM_INT, 'Number of attempts (0 = unlimited)', VALUE_DEFAULT, 1),
            'preferredbehaviour' => new external_value(
                PARAM_ALPHANUMEXT,
                'deferredfeedback, interactive or immediatefeedback',
                VALUE_DEFAULT,
                'deferredfeedback'
            ),
            'shuffleanswers' => new external_value(PARAM_INT, 'Shuffle answers within questions (0/1)', VALUE_DEFAULT, 1),
            'timelimit' => new external_value(PARAM_INT, 'Time limit in seconds (0 = none)', VALUE_DEFAULT, 0),
            'grade' => new external_value(PARAM_FLOAT, 'Maximum grade', VALUE_DEFAULT, 10),
            'timeopen' => new external_value(PARAM_INT, 'Open from (Unix time, 0 = always)', VALUE_DEFAULT, 0),
            'timeclose' => new external_value(PARAM_INT, 'Close at (Unix time, 0 = never)', VALUE_DEFAULT, 0),
            'reviewafter' => new external_value(
                PARAM_INT,
                'Show marks, correctness and feedback after the attempt (0/1)',
                VALUE_DEFAULT,
                1
            ),
        ]);
    }

    /**
     * Creates the quiz.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string $name
     * @param string $intro
     * @param int $visible
     * @param int $attempts
     * @param string $preferredbehaviour
     * @param int $shuffleanswers
     * @param int $timelimit
     * @param float $grade
     * @param int $timeopen
     * @param int $timeclose
     * @param int $reviewafter
     * @return array
     */
    public static function execute(
        int $courseid,
        int $sectionnum,
        string $name,
        string $intro = '',
        int $visible = 0,
        int $attempts = 1,
        string $preferredbehaviour = 'deferredfeedback',
        int $shuffleanswers = 1,
        int $timelimit = 0,
        float $grade = 10,
        int $timeopen = 0,
        int $timeclose = 0,
        int $reviewafter = 1
    ): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid, 'sectionnum' => $sectionnum, 'name' => $name, 'intro' => $intro,
            'visible' => $visible, 'attempts' => $attempts, 'preferredbehaviour' => $preferredbehaviour,
            'shuffleanswers' => $shuffleanswers, 'timelimit' => $timelimit, 'grade' => $grade,
            'timeopen' => $timeopen, 'timeclose' => $timeclose, 'reviewafter' => $reviewafter,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);
        require_capability('mod/quiz:addinstance', $context);

        if (!in_array($params['preferredbehaviour'], ['deferredfeedback', 'interactive', 'immediatefeedback'])) {
            throw new \invalid_parameter_exception('preferredbehaviour');
        }

        $quiz = new \stdClass();
        $quiz->modulename = 'quiz';
        $quiz->module = $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST);
        $quiz->course = $course->id;
        $quiz->section = $params['sectionnum'];
        $quiz->visible = $params['visible'];
        $quiz->name = $params['name'];
        $quiz->introeditor = ['text' => $params['intro'], 'format' => FORMAT_HTML, 'itemid' => 0];
        $quiz->cmidnumber = '';

        $quiz->timeopen = $params['timeopen'];
        $quiz->timeclose = $params['timeclose'];
        $quiz->timelimit = $params['timelimit'];
        $quiz->overduehandling = 'autosubmit';
        $quiz->graceperiod = 0;
        $quiz->preferredbehaviour = $params['preferredbehaviour'];
        $quiz->canredoquestions = 0;
        $quiz->attempts = $params['attempts'];
        $quiz->attemptonlast = 0;
        $quiz->grademethod = QUIZ_GRADEHIGHEST;
        $quiz->decimalpoints = 2;
        $quiz->questiondecimalpoints = -1;
        $quiz->questionsperpage = 1;
        $quiz->navmethod = 'free';
        $quiz->shuffleanswers = $params['shuffleanswers'];
        $quiz->sumgrades = 0;
        $quiz->grade = $params['grade'];
        $quiz->quizpassword = '';
        $quiz->subnet = '';
        $quiz->browsersecurity = '-';
        $quiz->delay1 = 0;
        $quiz->delay2 = 0;
        $quiz->showuserpicture = 0;
        $quiz->showblocks = 0;
        $quiz->completionattemptsexhausted = 0;
        $quiz->completionminattempts = 0;
        $quiz->allowofflineattempts = 0;

        // Review options as the settings form would send them (field name + time).
        $fields = ['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback',
            'rightanswer', 'overallfeedback'];
        foreach ($fields as $field) {
            $times = ($params['reviewafter'] || $field === 'maxmarks') ? ['immediately', 'open', 'closed'] : [];
            foreach ($times as $when) {
                $quiz->{$field . $when} = 1;
            }
        }

        // One empty overall feedback, as the settings form creates it.
        $quiz->feedbacktext = [['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0]];
        $quiz->feedbackboundaries = [];

        $quiz = create_module(set_moduleinfo_defaults($quiz));

        return ['cmid' => (int)$quiz->coursemodule, 'quizid' => (int)$quiz->instance];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'quizid' => new external_value(PARAM_INT, 'Quiz ID'),
        ]);
    }
}

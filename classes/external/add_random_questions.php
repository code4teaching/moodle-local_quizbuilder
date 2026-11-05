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
 * Adds random questions from a category to a quiz.
 *
 * Every attempt draws its own questions from the category, so students sitting next to each other
 * get different quizzes.
 *
 * @package    local_quizbuilder
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_random_questions extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the quiz'),
            'categoryid' => new external_value(PARAM_INT, 'Question category to draw the questions from'),
            'number' => new external_value(PARAM_INT, 'Number of random questions to add'),
            'includesubcategories' => new external_value(
                PARAM_INT,
                'Also draw from subcategories (0/1)',
                VALUE_DEFAULT,
                0
            ),
            'shuffle' => new external_value(PARAM_INT, 'Shuffle the order of the questions (0/1)', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Adds the random questions.
     *
     * @param int $cmid
     * @param int $categoryid
     * @param int $number
     * @param int $includesubcategories
     * @param int $shuffle
     * @return array
     */
    public static function execute(
        int $cmid,
        int $categoryid,
        int $number,
        int $includesubcategories = 0,
        int $shuffle = 1
    ): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'categoryid' => $categoryid, 'number' => $number,
            'includesubcategories' => $includesubcategories, 'shuffle' => $shuffle,
        ]);

        $cm = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quiz:manage', $context);

        $category = $DB->get_record('question_categories', ['id' => $params['categoryid']], '*', MUST_EXIST);
        require_capability('moodle/question:useall', \context::instance_by_id($category->contextid));

        $available = count(add_quiz_questions::questions_in_category($category->id));
        if ($params['number'] < 1 || (!$params['includesubcategories'] && $params['number'] > $available)) {
            throw new \moodle_exception('invalidrandomnumber', 'local_quizbuilder', '', (object)[
                'number' => $params['number'], 'available' => $available,
            ]);
        }

        $quizobj = \mod_quiz\quiz_settings::create($cm->instance);
        $structure = \mod_quiz\structure::create_for_quiz($quizobj);
        $filtercondition = [
            'filter' => [
                'category' => [
                    'jointype' => \qbank_managecategories\category_condition::JOINTYPE_DEFAULT,
                    'values' => [$category->id],
                    'filteroptions' => ['includesubcategories' => (bool)$params['includesubcategories']],
                ],
            ],
        ];
        $structure->add_random_questions(0, $params['number'], $filtercondition);

        $DB->set_field('quiz_sections', 'shufflequestions', $params['shuffle'] ? 1 : 0, ['quizid' => $cm->instance]);
        \mod_quiz\quiz_settings::create($cm->instance)->get_grade_calculator()->recompute_quiz_sumgrades();

        return [
            'count' => $params['number'],
            'available' => $available,
            'sumgrades' => (float)$DB->get_field('quiz', 'sumgrades', ['id' => $cm->instance]),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'count' => new external_value(PARAM_INT, 'Number of random questions added'),
            'available' => new external_value(PARAM_INT, 'Number of questions in the category'),
            'sumgrades' => new external_value(PARAM_FLOAT, 'Total marks of the quiz'),
        ]);
    }
}

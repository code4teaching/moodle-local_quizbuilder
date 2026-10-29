<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_quizbuilder\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Adds questions to a quiz, given by id or as a whole category.
 *
 * @package    local_quizbuilder
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_quiz_questions extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the quiz'),
            'questionids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Question ID'), 'Questions to add', VALUE_DEFAULT, []),
            'categoryid' => new external_value(PARAM_INT,
                'Add all current questions of this category (used when questionids is empty)', VALUE_DEFAULT, 0),
            'maxmark' => new external_value(PARAM_FLOAT, 'Mark per question (0 = default mark of the question)',
                VALUE_DEFAULT, 0),
            'shuffle' => new external_value(PARAM_INT, 'Shuffle the order of the questions (0/1)', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Adds the questions.
     *
     * @param int $cmid
     * @param array $questionids
     * @param int $categoryid
     * @param float $maxmark
     * @param int $shuffle
     * @return array
     */
    public static function execute(int $cmid, array $questionids = [], int $categoryid = 0, float $maxmark = 0,
            int $shuffle = 1): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'questionids' => $questionids, 'categoryid' => $categoryid,
            'maxmark' => $maxmark, 'shuffle' => $shuffle,
        ]);

        $cm = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quiz:manage', $context);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $quiz->cmid = $cm->id;

        $ids = $params['questionids'];
        if (!$ids && $params['categoryid']) {
            $ids = self::questions_in_category($params['categoryid']);
        }
        if (!$ids) {
            throw new \moodle_exception('noquestions', 'local_quizbuilder');
        }

        foreach ($ids as $id) {
            $question = $DB->get_record('question', ['id' => $id], '*', MUST_EXIST);
            $questioncontext = \context::instance_by_id(
                $DB->get_field_sql('SELECT qc.contextid
                                      FROM {question_bank_entries} qbe
                                      JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                                      JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                                     WHERE qv.questionid = ?', [$question->id], MUST_EXIST));
            require_capability('moodle/question:useall', $questioncontext);
            quiz_add_quiz_question($question->id, $quiz, 0, $params['maxmark'] ?: null);
        }

        $DB->set_field('quiz_sections', 'shufflequestions', $params['shuffle'] ? 1 : 0, ['quizid' => $quiz->id]);
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();

        return [
            'count' => count($ids),
            'sumgrades' => (float)$DB->get_field('quiz', 'sumgrades', ['id' => $quiz->id]),
        ];
    }

    /**
     * Current versions of all top-level questions in a category, in order of creation.
     *
     * @param int $categoryid
     * @return int[]
     */
    public static function questions_in_category(int $categoryid): array {
        global $DB;
        $sql = "SELECT q.id
                  FROM {question_bank_entries} qbe
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  JOIN {question} q ON q.id = qv.questionid
                 WHERE qbe.questioncategoryid = :categoryid
                   AND q.parent = 0
                   AND qv.status = :ready
                   AND qv.version = (SELECT MAX(v.version)
                                       FROM {question_versions} v
                                      WHERE v.questionbankentryid = qbe.id)
              ORDER BY qbe.id";
        return array_map('intval', $DB->get_fieldset_sql($sql, [
            'categoryid' => $categoryid,
            'ready' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
        ]));
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'count' => new external_value(PARAM_INT, 'Number of added questions'),
            'sumgrades' => new external_value(PARAM_FLOAT, 'Total marks of the quiz'),
        ]);
    }
}

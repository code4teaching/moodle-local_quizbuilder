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
 * Creates a choice (poll), e.g. a progress poll with one option per lesson.
 *
 * @package    local_quizbuilder
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_choice extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number (0-based)'),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'options' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Option text')),
            'visible' => new external_value(PARAM_INT, 'Visible (1) or hidden (0)', VALUE_DEFAULT, 0),
            'shownames' => new external_value(PARAM_INT, 'Show results with names to everyone (0/1)', VALUE_DEFAULT, 1),
            'allowupdate' => new external_value(PARAM_INT, 'Allow changing the answer (0/1)', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Creates the choice.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string $name
     * @param string $intro
     * @param array $options
     * @param int $visible
     * @param int $shownames
     * @param int $allowupdate
     * @return array
     */
    public static function execute(int $courseid, int $sectionnum, string $name, string $intro, array $options,
            int $visible = 0, int $shownames = 1, int $allowupdate = 1): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/choice/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid, 'sectionnum' => $sectionnum, 'name' => $name, 'intro' => $intro,
            'options' => $options, 'visible' => $visible, 'shownames' => $shownames, 'allowupdate' => $allowupdate,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);
        require_capability('mod/choice:addinstance', $context);

        $choice = new \stdClass();
        $choice->modulename = 'choice';
        $choice->module = $DB->get_field('modules', 'id', ['name' => 'choice'], MUST_EXIST);
        $choice->course = $course->id;
        $choice->section = $params['sectionnum'];
        $choice->visible = $params['visible'];
        $choice->name = $params['name'];
        $choice->introeditor = ['text' => $params['intro'], 'format' => FORMAT_HTML, 'itemid' => 0];
        $choice->option = $params['options'];
        $choice->limit = array_fill(0, count($params['options']), 0);
        $choice->limitanswers = 0;
        $choice->allowupdate = $params['allowupdate'];
        $choice->allowmultiple = 0;
        $choice->showresults = CHOICE_SHOWRESULTS_ALWAYS;
        $choice->publish = $params['shownames'] ? CHOICE_PUBLISH_NAMES : CHOICE_PUBLISH_ANONYMOUS;
        $choice->display = CHOICE_DISPLAY_VERTICAL;
        $choice->showunanswered = 0;
        $choice->includeinactive = 0;
        $choice->showpreview = 0;
        $choice->showavailable = 0;
        $choice->timeopen = 0;
        $choice->timeclose = 0;
        $choice->completionsubmit = 0;

        $choice = create_module(set_moduleinfo_defaults($choice));

        return ['cmid' => (int)$choice->coursemodule, 'choiceid' => (int)$choice->instance];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'choiceid' => new external_value(PARAM_INT, 'Choice ID'),
        ]);
    }
}

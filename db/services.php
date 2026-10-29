<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Webservice functions.
 *
 * @package    local_quizbuilder
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_quizbuilder_import_questions' => [
        'classname'    => 'local_quizbuilder\external\import_questions',
        'description'  => 'Imports questions from Moodle XML into the default question bank of a course.',
        'type'         => 'write',
        'capabilities' => 'moodle/question:add',
    ],
    'local_quizbuilder_create_quiz' => [
        'classname'    => 'local_quizbuilder\external\create_quiz',
        'description'  => 'Creates a quiz in a course section.',
        'type'         => 'write',
        'capabilities' => 'mod/quiz:addinstance',
    ],
    'local_quizbuilder_add_quiz_questions' => [
        'classname'    => 'local_quizbuilder\external\add_quiz_questions',
        'description'  => 'Adds questions (by id or a whole category) to a quiz.',
        'type'         => 'write',
        'capabilities' => 'mod/quiz:manage',
    ],
    'local_quizbuilder_create_choice' => [
        'classname'    => 'local_quizbuilder\external\create_choice',
        'description'  => 'Creates a choice (poll) in a course section.',
        'type'         => 'write',
        'capabilities' => 'mod/choice:addinstance',
    ],
];

$services = [
    'Quiz Builder Service' => [
        'functions'       => array_keys($functions),
        'shortname'       => 'local_quizbuilder',
        'restrictedusers' => 1,
        'enabled'         => 1,
    ],
];

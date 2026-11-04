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
 * Returns the rules for writing questions (rules.md) and their version.
 *
 * import_questions only accepts the current version, so a caller has to read the rules first.
 *
 * @package    local_quizbuilder
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_rules extends external_api {
    /**
     * The rules as Markdown.
     *
     * @return string
     */
    public static function rules(): string {
        return file_get_contents(__DIR__ . '/../../rules.md');
    }

    /**
     * The version of the rules: changes whenever rules.md changes.
     *
     * @return string
     */
    public static function version(): string {
        return substr(sha1(self::rules()), 0, 8);
    }

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Returns the rules.
     *
     * @return array
     */
    public static function execute(): array {
        self::validate_context(\context_system::instance());
        return [
            'version' => self::version(),
            'rules' => self::rules(),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'version' => new external_value(PARAM_ALPHANUM, 'Version of the rules; pass it to import_questions as rulesversion'),
            'rules' => new external_value(PARAM_RAW, 'Rules for writing questions (Markdown)'),
        ]);
    }
}

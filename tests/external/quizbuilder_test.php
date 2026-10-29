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

/**
 * Tests for the Quiz Builder web service functions.
 *
 * @package    local_quizbuilder
 * @category   test
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(import_questions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(create_quiz::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(add_quiz_questions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(create_choice::class)]
final class quizbuilder_test extends \advanced_testcase {
    /**
     * Imports the fixture questions into a new course.
     *
     * @param \stdClass $course
     * @return array cleaned return value of import_questions
     */
    private function import_fixture(\stdClass $course): array {
        $xml = file_get_contents(__DIR__ . '/../fixtures/questions.xml');
        $result = import_questions::execute($course->id, $xml);
        return external_api::clean_returnvalue(import_questions::execute_returns(), $result);
    }

    /**
     * Questions from Moodle XML land in a new category of the course question bank.
     */
    public function test_import_questions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $result = $this->import_fixture($course);

        $this->assertSame(3, $result['count']);
        $this->assertCount(3, $result['questionids']);
        $category = $DB->get_record('question_categories', ['id' => $result['categoryid']], '*', MUST_EXIST);
        $this->assertSame('Quiz Builder test', $category->name);
        $this->assertSame('ordering', $DB->get_field('question', 'qtype', ['id' => $result['questionids'][2]]));
    }

    /**
     * A quiz is created hidden with the requested settings.
     */
    public function test_create_quiz(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);

        $result = create_quiz::execute($course->id, 2, 'Quiz 2', '<p>Intro</p>', 0, 1, 'deferredfeedback', 1, 600, 16);
        $result = external_api::clean_returnvalue(create_quiz::execute_returns(), $result);

        $quiz = $DB->get_record('quiz', ['id' => $result['quizid']], '*', MUST_EXIST);
        $this->assertSame('Quiz 2', $quiz->name);
        $this->assertEquals(1, $quiz->attempts);
        $this->assertEquals(600, $quiz->timelimit);
        $this->assertEquals(16, $quiz->grade);
        $this->assertSame('deferredfeedback', $quiz->preferredbehaviour);

        $cm = get_coursemodule_from_id('quiz', $result['cmid'], 0, true, MUST_EXIST);
        $this->assertEquals(0, $cm->visible);
        $this->assertEquals(2, $cm->sectionnum);
    }

    /**
     * An unknown question behaviour is rejected.
     */
    public function test_create_quiz_invalid_behaviour(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $this->expectException(\invalid_parameter_exception::class);
        create_quiz::execute($course->id, 1, 'Quiz', '', 0, 1, 'adaptive');
    }

    /**
     * All questions of a category are added to a quiz, in shuffled order.
     */
    public function test_add_quiz_questions_by_category(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $import = $this->import_fixture($course);
        $quiz = external_api::clean_returnvalue(
            create_quiz::execute_returns(),
            create_quiz::execute($course->id, 1, 'Quiz', '', 0)
        );

        $result = add_quiz_questions::execute($quiz['cmid'], [], $import['categoryid']);
        $result = external_api::clean_returnvalue(add_quiz_questions::execute_returns(), $result);

        $this->assertSame(3, $result['count']);
        $this->assertEquals(4, $result['sumgrades']);
        $this->assertSame(3, $DB->count_records('quiz_slots', ['quizid' => $quiz['quizid']]));
        $this->assertEquals(1, $DB->get_field('quiz_sections', 'shufflequestions', ['quizid' => $quiz['quizid']]));
    }

    /**
     * Questions given by id get the requested mark.
     */
    public function test_add_quiz_questions_by_id_with_mark(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $import = $this->import_fixture($course);
        $quiz = external_api::clean_returnvalue(
            create_quiz::execute_returns(),
            create_quiz::execute($course->id, 1, 'Quiz', '', 0)
        );

        $result = add_quiz_questions::execute($quiz['cmid'], $import['questionids'], 0, 1, 0);
        $result = external_api::clean_returnvalue(add_quiz_questions::execute_returns(), $result);

        $this->assertSame(3, $result['count']);
        $this->assertEquals(3, $result['sumgrades']);
    }

    /**
     * Without questions the function refuses to run.
     */
    public function test_add_quiz_questions_none(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = external_api::clean_returnvalue(
            create_quiz::execute_returns(),
            create_quiz::execute($course->id, 1, 'Quiz', '', 0)
        );

        $this->expectException(\moodle_exception::class);
        add_quiz_questions::execute($quiz['cmid']);
    }

    /**
     * A choice is created with one option per entry and names shown to everyone.
     */
    public function test_create_choice(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $result = create_choice::execute($course->id, 0, 'Where are you?', '', ['Lesson 1', 'Lesson 2', 'Lesson 3']);
        $result = external_api::clean_returnvalue(create_choice::execute_returns(), $result);

        $choice = $DB->get_record('choice', ['id' => $result['choiceid']], '*', MUST_EXIST);
        $this->assertEquals(CHOICE_PUBLISH_NAMES, $choice->publish);
        $this->assertEquals(1, $choice->allowupdate);
        $this->assertSame(3, $DB->count_records('choice_options', ['choiceid' => $choice->id]));
    }

    /**
     * Students cannot create quizzes.
     */
    public function test_create_quiz_requires_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        create_quiz::execute($course->id, 1, 'Quiz', '', 0);
    }

    /**
     * Students cannot import questions.
     */
    public function test_import_questions_requires_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        import_questions::execute($course->id, '<?xml version="1.0"?><quiz></quiz>');
    }
}

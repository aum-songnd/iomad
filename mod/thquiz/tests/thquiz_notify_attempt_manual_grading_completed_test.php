<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Contains the class containing unit tests for the thquiz notify attempt manual grading completed cron task.
 *
 * @package   mod_thquiz
 * @copyright 2021 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_thquiz;

use advanced_testcase;
use context_course;
use context_module;
use mod_thquiz\task\thquiz_notify_attempt_manual_grading_completed;
use question_engine;
use thquiz;
use thquiz_attempt;
use stdClass;

defined('MOODLE_INTERNAL') || die();


/**
 * Class containing unit tests for the thquiz notify attempt manual grading completed cron task.
 *
 * @package mod_thquiz
 * @copyright 2021 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class thquiz_notify_attempt_manual_grading_completed_test extends advanced_testcase {
    /** @var \stdClass $course Test course to contain thquiz. */
    protected $course;

    /** @var \stdClass $thquiz A test thquiz. */
    protected $thquiz;

    /** @var context The thquiz context. */
    protected $context;

    /** @var stdClass The course_module. */
    protected $cm;

    /** @var stdClass The student test. */
    protected $student;

    /** @var stdClass The teacher test. */
    protected $teacher;

    /** @var thquiz Object containing the thquiz settings. */
    protected $thquizobj;

    /** @var question_usage_by_activity The question usage for this thquiz attempt. */
    protected $quba;

    /**
     * Standard test setup.
     *
     * Create a course with a thquiz and a student and a(n editing) teacher.
     * the thquiz has a truefalse question and an essay question.
     *
     * Also create some bits of a thquiz attempt to be used later.
     */
    public function setUp(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $this->course = $this->getDataGenerator()->create_course();
        $this->thquiz = $this->getDataGenerator()->create_module('thquiz', ['course' => $this->course->id]);
        $this->context = context_module::instance($this->thquiz->cmid);
        $this->cm = get_coursemodule_from_instance('thquiz', $this->thquiz->id);

        // Create users.
        $this->student = self::getDataGenerator()->create_user();
        $this->teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);

        // Allow student to receive messages.
        $coursecontext = context_course::instance($this->course->id);
        assign_capability('mod/thquiz:emailnotifyattemptgraded', CAP_ALLOW, $studentrole->id, $coursecontext, true);

        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $teacherrole->id);

        // Make a thquiz.
        $thquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_thquiz');
        $this->thquiz = $thquizgenerator->create_instance(['course' => $this->course->id, 'questionsperpage' => 0,
            'grade' => 100.0, 'sumgrades' => 2]);

        // Create a truefalse question and an essay question.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $truefalse = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        $essay = $questiongenerator->create_question('essay', null, ['category' => $cat->id]);

        // Add them to the thquiz.
        thquiz_add_thquiz_question($truefalse->id, $this->thquiz);
        thquiz_add_thquiz_question($essay->id, $this->thquiz);

        $this->thquizobj = thquiz::create($this->thquiz->id);
        $this->quba = question_engine::make_questions_usage_by_activity('mod_thquiz', $this->thquizobj->get_context());
        $this->quba->set_preferred_behaviour($this->thquizobj->get_thquiz()->preferredbehaviour);
    }

    /**
     * Test SQL querry get list attempt in condition.
     */
    public function test_get_list_of_attempts_within_conditions() {
        global $DB;

        $timenow = time();

        // Create an attempt to be completely graded (one hour ago).
        $attempt1 = thquiz_create_attempt($this->thquizobj, 1, null, $timenow - HOURSECS, false, $this->student->id);
        thquiz_start_new_attempt($this->thquizobj, $this->quba, $attempt1, 1, $timenow - HOURSECS);
        thquiz_attempt_save_started($this->thquizobj, $this->quba, $attempt1);

        // Process some responses from the student (30 mins ago) and submit (20 mins ago).
        $attemptobj1 = thquiz_attempt::create($attempt1->id);
        $tosubmit = [2 => ['answer' => 'Student 1 answer', 'answerformat' => FORMAT_HTML]];
        $attemptobj1->process_submitted_actions($timenow - 30 * MINSECS, false, $tosubmit);
        $attemptobj1->process_finish($timenow - 20 * MINSECS, false);

        // Finish the attempt of student (now).
        $attemptobj1->get_question_usage()->manual_grade(2, 'Good!', 1, FORMAT_HTML);
        question_engine::save_questions_usage_by_activity($attemptobj1->get_question_usage());

        $update = new stdClass();
        $update->id = $attemptobj1->get_attemptid();
        $update->timemodified = $timenow;
        $update->sumgrades = $attemptobj1->get_question_usage()->get_total_mark();
        $DB->update_record('thquiz_attempts', $update);
        thquiz_save_best_grade($attemptobj1->get_thquiz(), $this->student->id);

        // Not quite time to send yet.
        $task = new thquiz_notify_attempt_manual_grading_completed();
        $task->set_time_for_testing($timenow + 5 * HOURSECS - 1);
        $attempts = $task->get_list_of_attempts();
        $this->assertEquals(0, iterator_count($attempts));

        // After time to send.
        $task->set_time_for_testing($timenow + 5 * HOURSECS + 1);
        $attempts = $task->get_list_of_attempts();
        $this->assertEquals(1, iterator_count($attempts));
    }

    /**
     * Test SQL query does not return attempts if the grading is not complete yet.
     */
    public function test_get_list_of_attempts_without_manual_graded() {

        $timenow = time();

        // Create an attempt which won't be graded (1 hour ago).
        $attempt2 = thquiz_create_attempt($this->thquizobj, 2, null, $timenow - HOURSECS, false, $this->student->id);
        thquiz_start_new_attempt($this->thquizobj, $this->quba, $attempt2, 2, $timenow - HOURSECS);
        thquiz_attempt_save_started($this->thquizobj, $this->quba, $attempt2);

        // Process some responses from the student (30 mins ago) and submit (now).
        $attemptobj2 = thquiz_attempt::create($attempt2->id);
        $tosubmit = [2 => ['answer' => 'Answer of student 2.', 'answerformat' => FORMAT_HTML]];
        $attemptobj2->process_submitted_actions($timenow - 30 * MINSECS, false, $tosubmit);
        $attemptobj2->process_finish($timenow, false);

        // After time to notify, except attempt not graded, so it won't appear.
        $task = new thquiz_notify_attempt_manual_grading_completed();
        $task->set_time_for_testing($timenow + 5 * HOURSECS + 1);
        $attempts = $task->get_list_of_attempts();

        $this->assertEquals(0, iterator_count($attempts));
    }

    /**
     * Test notify manual grading completed task which the user attempt has not capability.
     */
    public function test_notify_manual_grading_completed_task_without_capability() {
        global $DB;

        // Create an attempt for a user without the capability.
        $timenow = time();
        $attempt = thquiz_create_attempt($this->thquizobj, 3, null, $timenow, false, $this->teacher->id);
        thquiz_start_new_attempt($this->thquizobj, $this->quba, $attempt, 3, $timenow - HOURSECS);
        thquiz_attempt_save_started($this->thquizobj, $this->quba, $attempt);

        // Process some responses and submit.
        $attemptobj = thquiz_attempt::create($attempt->id);
        $tosubmit = [2 => ['answer' => 'Answer of teacher.', 'answerformat' => FORMAT_HTML]];
        $attemptobj->process_submitted_actions($timenow - 30 * MINSECS, false, $tosubmit);
        $attemptobj->process_finish($timenow - 20 * MINSECS, false);

        // Grade the attempt.
        $attemptobj->get_question_usage()->manual_grade(2, 'Good!', 1, FORMAT_HTML);
        question_engine::save_questions_usage_by_activity($attemptobj->get_question_usage());

        $update = new stdClass();
        $update->id = $attemptobj->get_attemptid();
        $update->timemodified = $timenow;
        $update->sumgrades = $attemptobj->get_question_usage()->get_total_mark();
        $DB->update_record('thquiz_attempts', $update);
        thquiz_save_best_grade($attemptobj->get_thquiz(), $this->student->id);

        // Run the thquiz notify attempt manual graded task.
        ob_start();
        $task = new thquiz_notify_attempt_manual_grading_completed();
        $task->set_time_for_testing($timenow + 5 * HOURSECS + 1);
        $task->execute();
        ob_get_clean();

        $attemptobj = thquiz_attempt::create($attempt->id);
        $this->assertEquals($attemptobj->get_attempt()->timefinish, $attemptobj->get_attempt()->gradednotificationsenttime);
    }

    /**
     * Test notify manual grading completed task which the user attempt has capability.
     */
    public function test_notify_manual_grading_completed_task_with_capability() {
        global $DB;

        // Create an attempt with capability.
        $timenow = time();
        $attempt = thquiz_create_attempt($this->thquizobj, 4, null, $timenow, false, $this->student->id);
        thquiz_start_new_attempt($this->thquizobj, $this->quba, $attempt, 4, $timenow - HOURSECS);
        thquiz_attempt_save_started($this->thquizobj, $this->quba, $attempt);

        // Process some responses from the student.
        $attemptobj = thquiz_attempt::create($attempt->id);
        $tosubmit = [2 => ['answer' => 'Answer of student.', 'answerformat' => FORMAT_HTML]];
        $attemptobj->process_submitted_actions($timenow - 30 * MINSECS, false, $tosubmit);
        $attemptobj->process_finish($timenow - 20 * MINSECS, false);

        // Finish the attempt of student.
        $attemptobj->get_question_usage()->manual_grade(2, 'Good!', 1, FORMAT_HTML);
        question_engine::save_questions_usage_by_activity($attemptobj->get_question_usage());

        $update = new stdClass();
        $update->id = $attemptobj->get_attemptid();
        $update->timemodified = $timenow;
        $update->sumgrades = $attemptobj->get_question_usage()->get_total_mark();
        $DB->update_record('thquiz_attempts', $update);
        thquiz_save_best_grade($attemptobj->get_thquiz(), $this->student->id);

        // Run the thquiz notify attempt manual graded task.
        ob_start();
        $task = new thquiz_notify_attempt_manual_grading_completed();
        $task->set_time_for_testing($timenow + 5 * HOURSECS + 1);
        $task->execute();
        ob_get_clean();

        $attemptobj = thquiz_attempt::create($attempt->id);

        $this->assertNotEquals(null, $attemptobj->get_attempt()->gradednotificationsenttime);
        $this->assertNotEquals($attemptobj->get_attempt()->timefinish, $attemptobj->get_attempt()->gradednotificationsenttime);
    }
}

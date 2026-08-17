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

namespace thquiz_overview;

use core_question\local\bank\question_version_status;
use mod_thquiz\external\submit_question_version;
use question_engine;
use thquiz;
use thquiz_attempt;
use thquiz_attempts_report;
use thquiz_overview_options;
use thquiz_overview_report;
use thquiz_overview_table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/thquiz/locallib.php');
require_once($CFG->dirroot . '/mod/thquiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/thquiz/report/default.php');
require_once($CFG->dirroot . '/mod/thquiz/report/overview/report.php');
require_once($CFG->dirroot . '/mod/thquiz/report/overview/overview_form.php');
require_once($CFG->dirroot . '/mod/thquiz/report/overview/tests/helpers.php');
require_once($CFG->dirroot . '/mod/thquiz/tests/thquiz_question_helper_test_trait.php');


/**
 * Tests for the thquiz overview report.
 *
 * @package    thquiz_overview
 * @copyright  2014 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_test extends \advanced_testcase {
    use \thquiz_question_helper_test_trait;

    /**
     * Data provider for test_report_sql.
     *
     * @return array the data for the test sub-cases.
     */
    public function report_sql_cases(): array {
        return [[null], ['csv']]; // Only need to test on or off, not all download types.
    }

    /**
     * Test how the report queries the database.
     *
     * @param string|null $isdownloading a download type, or null.
     * @dataProvider report_sql_cases
     */
    public function test_report_sql(?string $isdownloading): void {
        global $DB;
        $this->resetAfterTest();

        // Create a course and a thquiz.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $thquizgenerator = $generator->get_plugin_generator('mod_thquiz');
        $thquiz = $thquizgenerator->create_instance(array('course' => $course->id,
                'grademethod' => THQUIZ_GRADEHIGHEST, 'grade' => 100.0, 'sumgrades' => 10.0,
                'attempts' => 10));

        // Add one question.
        /** @var core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $q = $questiongenerator->create_question('essay', 'plain', ['category' => $cat->id]);
        thquiz_add_thquiz_question($q->id, $thquiz, 0 , 10);

        // Create some students and enrol them in the course.
        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $student3 = $generator->create_user();
        $generator->enrol_user($student1->id, $course->id);
        $generator->enrol_user($student2->id, $course->id);
        $generator->enrol_user($student3->id, $course->id);
        // This line is not really necessary for the test asserts below,
        // but what it does is add an extra user row returned by
        // get_enrolled_with_capabilities_join because of a second enrolment.
        // The extra row returned used to make $table->query_db complain
        // about duplicate records. So this is really a test that an extra
        // student enrolment does not cause duplicate records in this query.
        $generator->enrol_user($student2->id, $course->id, null, 'self');

        // Also create a user who should not appear in the reports,
        // because they have a role with neither 'mod/thquiz:attempt'
        // nor 'mod/thquiz:reviewmyattempts'.
        $tutor = $generator->create_user();
        $generator->enrol_user($tutor->id, $course->id, 'teacher');

        // The test data.
        $timestamp = 1234567890;
        $attempts = array(
            array($thquiz, $student1, 1, 0.0,  thquiz_attempt::FINISHED),
            array($thquiz, $student1, 2, 5.0,  thquiz_attempt::FINISHED),
            array($thquiz, $student1, 3, 8.0,  thquiz_attempt::FINISHED),
            array($thquiz, $student1, 4, null, thquiz_attempt::ABANDONED),
            array($thquiz, $student1, 5, null, thquiz_attempt::IN_PROGRESS),
            array($thquiz, $student2, 1, null, thquiz_attempt::ABANDONED),
            array($thquiz, $student2, 2, null, thquiz_attempt::ABANDONED),
            array($thquiz, $student2, 3, 7.0,  thquiz_attempt::FINISHED),
            array($thquiz, $student2, 4, null, thquiz_attempt::ABANDONED),
            array($thquiz, $student2, 5, null, thquiz_attempt::ABANDONED),
        );

        // Load it in to thquiz attempts table.
        foreach ($attempts as $attemptdata) {
            list($thquiz, $student, $attemptnumber, $sumgrades, $state) = $attemptdata;
            $timestart = $timestamp + $attemptnumber * 3600;

            $thquizobj = thquiz::create($thquiz->id, $student->id);
            $quba = question_engine::make_questions_usage_by_activity('mod_thquiz', $thquizobj->get_context());
            $quba->set_preferred_behaviour($thquizobj->get_thquiz()->preferredbehaviour);

            // Create the new attempt and initialize the question sessions.
            $attempt = thquiz_create_attempt($thquizobj, $attemptnumber, null, $timestart, false, $student->id);

            $attempt = thquiz_start_new_attempt($thquizobj, $quba, $attempt, $attemptnumber, $timestamp);
            $attempt = thquiz_attempt_save_started($thquizobj, $quba, $attempt);

            // Process some responses from the student.
            $attemptobj = thquiz_attempt::create($attempt->id);
            switch ($state) {
                case thquiz_attempt::ABANDONED:
                    $attemptobj->process_abandon($timestart + 300, false);
                    break;

                case thquiz_attempt::IN_PROGRESS:
                    // Do nothing.
                    break;

                case thquiz_attempt::FINISHED:
                    // Save answer and finish attempt.
                    $attemptobj->process_submitted_actions($timestart + 300, false, [
                            1 => ['answer' => 'My essay by ' . $student->firstname, 'answerformat' => FORMAT_PLAIN]]);
                    $attemptobj->process_finish($timestart + 600, false);

                    // Manually grade it.
                    $quba = $attemptobj->get_question_usage();
                    $quba->get_question_attempt(1)->manual_grade(
                            'Comment', $sumgrades, FORMAT_HTML, $timestart + 1200);
                    question_engine::save_questions_usage_by_activity($quba);
                    $update = new \stdClass();
                    $update->id = $attemptobj->get_attemptid();
                    $update->timemodified = $timestart + 1200;
                    $update->sumgrades = $quba->get_total_mark();
                    $DB->update_record('thquiz_attempts', $update);
                    thquiz_save_best_grade($attemptobj->get_thquiz(), $student->id);
                    break;
            }
        }

        // Actually getting the SQL to run is quite hard. Do a minimal set up of
        // some objects.
        $context = \context_module::instance($thquiz->cmid);
        $cm = get_coursemodule_from_id('thquiz', $thquiz->cmid);
        $qmsubselect = thquiz_report_qm_filter_select($thquiz);
        $studentsjoins = get_enrolled_with_capabilities_join($context, '',
                array('mod/thquiz:attempt', 'mod/thquiz:reviewmyattempts'));
        $empty = new \core\dml\sql_join();

        // Set the options.
        $reportoptions = new thquiz_overview_options('overview', $thquiz, $cm, null);
        $reportoptions->attempts = thquiz_attempts_report::ENROLLED_ALL;
        $reportoptions->onlygraded = true;
        $reportoptions->states = array(thquiz_attempt::IN_PROGRESS, thquiz_attempt::OVERDUE, thquiz_attempt::FINISHED);

        // Now do a minimal set-up of the table class.
        $q->slot = 1;
        $q->maxmark = 10;
        $table = new thquiz_overview_table($thquiz, $context, $qmsubselect, $reportoptions,
                $empty, $studentsjoins, array(1 => $q), null);
        $table->download = $isdownloading; // Cannot call the is_downloading API, because it gives errors.
        $table->define_columns(array('fullname'));
        $table->sortable(true, 'uniqueid');
        $table->define_baseurl(new \moodle_url('/mod/thquiz/report.php'));
        $table->setup();

        // Run the query.
        $table->setup_sql_queries($studentsjoins);
        $table->query_db(30, false);

        // Should be 4 rows, matching count($table->rawdata) tested below.
        // The count is only done if not downloading.
        if (!$isdownloading) {
            $this->assertEquals(4, $table->totalrows);
        }

        // Verify what was returned: Student 1's best and in progress attempts.
        // Student 2's finshed attempt, and Student 3 with no attempt.
        // The array key is {student id}#{attempt number}.
        $this->assertEquals(4, count($table->rawdata));
        $this->assertArrayHasKey($student1->id . '#3', $table->rawdata);
        $this->assertEquals(1, $table->rawdata[$student1->id . '#3']->gradedattempt);
        $this->assertArrayHasKey($student1->id . '#3', $table->rawdata);
        $this->assertEquals(0, $table->rawdata[$student1->id . '#5']->gradedattempt);
        $this->assertArrayHasKey($student2->id . '#3', $table->rawdata);
        $this->assertEquals(1, $table->rawdata[$student2->id . '#3']->gradedattempt);
        $this->assertArrayHasKey($student3->id . '#0', $table->rawdata);
        $this->assertEquals(0, $table->rawdata[$student3->id . '#0']->gradedattempt);

        // Check the calculation of averages.
        $averagerow = $table->compute_average_row('overallaverage', $studentsjoins);
        $this->assertStringContainsString('75.00', $averagerow['sumgrades']);
        $this->assertStringContainsString('75.00', $averagerow['qsgrade1']);
        if (!$isdownloading) {
            $this->assertStringContainsString('(2)', $averagerow['sumgrades']);
            $this->assertStringContainsString('(2)', $averagerow['qsgrade1']);
        }

        // Ensure that filtering by initial does not break it.
        // This involves setting a private properly of the base class, which is
        // only really possible using reflection :-(.
        $reflectionobject = new \ReflectionObject($table);
        while ($parent = $reflectionobject->getParentClass()) {
            $reflectionobject = $parent;
        }
        $prefsproperty = $reflectionobject->getProperty('prefs');
        $prefsproperty->setAccessible(true);
        $prefs = $prefsproperty->getValue($table);
        $prefs['i_first'] = 'A';
        $prefsproperty->setValue($table, $prefs);

        list($fields, $from, $where, $params) = $table->base_sql($studentsjoins);
        $table->set_count_sql("SELECT COUNT(1) FROM (SELECT $fields FROM $from WHERE $where) temp WHERE 1 = 1", $params);
        $table->set_sql($fields, $from, $where, $params);
        $table->query_db(30, false);
        // Just verify that this does not cause a fatal error.
    }

    /**
     * Bands provider.
     * @return array
     */
    public function get_bands_count_and_width_provider(): array {
        return [
            [10, [20, .5]],
            [20, [20, 1]],
            [30, [15, 2]],
            // TODO MDL-55068 Handle bands better when grade is 50.
            // [50, [10, 5]],
            [100, [20, 5]],
            [200, [20, 10]],
        ];
    }

    /**
     * Test bands.
     *
     * @dataProvider get_bands_count_and_width_provider
     * @param int $grade grade
     * @param array $expected
     */
    public function test_get_bands_count_and_width(int $grade, array $expected): void {
        $this->resetAfterTest();
        $thquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_thquiz');
        $thquiz = $thquizgenerator->create_instance(['course' => SITEID, 'grade' => $grade]);
        $this->assertEquals($expected, thquiz_overview_report::get_bands_count_and_width($thquiz));
    }

    /**
     * Test delete_selected_attempts function.
     */
    public function test_delete_selected_attempts(): void {
        $this->resetAfterTest();

        $timestamp = 1234567890;
        $timestart = $timestamp + 3600;

        // Create a course and a thquiz.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $thquizgenerator = $generator->get_plugin_generator('mod_thquiz');
        $thquiz = $thquizgenerator->create_instance([
                'course' => $course->id,
                'grademethod' => THQUIZ_GRADEHIGHEST,
                'grade' => 100.0,
                'sumgrades' => 10.0,
                'attempts' => 10
        ]);

        // Add one question.
        /** @var core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $q = $questiongenerator->create_question('essay', 'plain', ['category' => $cat->id]);
        thquiz_add_thquiz_question($q->id, $thquiz, 0 , 10);

        // Create student and enrol them in the course.
        // Note: we create two enrolments, to test the problem reported in MDL-67942.
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id);
        $generator->enrol_user($student->id, $course->id, null, 'self');

        $context = \context_module::instance($thquiz->cmid);
        $cm = get_coursemodule_from_id('thquiz', $thquiz->cmid);
        $allowedjoins = get_enrolled_with_capabilities_join($context, '', ['mod/thquiz:attempt', 'mod/thquiz:reviewmyattempts']);
        $thquizattemptsreport = new \testable_thquiz_attempts_report();

        // Create the new attempt and initialize the question sessions.
        $thquizobj = thquiz::create($thquiz->id, $student->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_thquiz', $thquizobj->get_context());
        $quba->set_preferred_behaviour($thquizobj->get_thquiz()->preferredbehaviour);
        $attempt = thquiz_create_attempt($thquizobj, 1, null, $timestart, false, $student->id);
        $attempt = thquiz_start_new_attempt($thquizobj, $quba, $attempt, 1, $timestamp);
        $attempt = thquiz_attempt_save_started($thquizobj, $quba, $attempt);

        // Delete the student's attempt.
        $thquizattemptsreport->delete_selected_attempts($thquiz, $cm, [$attempt->id], $allowedjoins);
    }

    /**
     * Test question regrade for selected versions.
     *
     * @covers ::regrade_question
     */
    public function test_regrade_question() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $thquiz = $this->create_test_thquiz($course);
        $cm = get_fast_modinfo($course->id)->get_cm($thquiz->cmid);
        $context = \context_module::instance($thquiz->cmid);

        /** @var core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        // Create a couple of questions.
        $cat = $questiongenerator->create_question_category(['contextid' => $context->id]);
        $q = $questiongenerator->create_question('shortanswer', null,
                ['category' => $cat->id, 'name' => 'Toad scores 0.8']);

        // Create a version, the last one draft.
        // Sadly, update_question is a bit dodgy, so it can't handle updating the answer score.
        $q2 = $questiongenerator->update_question($q, null,
                ['name' => 'Toad now scores 1.0']);
        $toadanswer = $DB->get_record_select('question_answers',
                'question = ? AND ' . $DB->sql_compare_text('answer') . ' = ?',
                [$q2->id, 'toad'], '*', MUST_EXIST);
        $DB->set_field('question_answers', 'fraction', 1, ['id' => $toadanswer->id]);

        // Add the question to the thquiz.
        thquiz_add_thquiz_question($q2->id, $thquiz, 0, 10);

        // Attempt the thquiz, submitting response 'toad'.
        $thquizobj = thquiz::create($thquiz->id);
        $attempt = thquiz_prepare_and_start_new_attempt($thquizobj, 1, null);
        $attemptobj = thquiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions(time(), false, [1 => ['answer' => 'toad']]);
        $attemptobj->process_finish(time(), false);

        // We should be using 'always latest' version, which is currently v2, so should be right.
        $this->assertEquals(10, $attemptobj->get_question_usage()->get_total_mark());

        // Now change the thquiz to use fixed version 1.
        $slot = $thquizobj->get_question($q2->id);
        submit_question_version::execute($slot->slotid, 1);

        // Regrade.
        $report = new thquiz_overview_report();
        $report->init('overview', 'thquiz_overview_settings_form', $thquiz, $cm, $course);
        $report->regrade_attempt($attempt);

        // The mark should now be 8.
        $attemptobj = thquiz_attempt::create($attempt->id);
        $this->assertEquals(8, $attemptobj->get_question_usage()->get_total_mark());

        // Now add two more versions, the second of which is draft.
        $q3 = $questiongenerator->update_question($q, null,
                ['name' => 'Toad now scores 0.5']);
        $toadanswer = $DB->get_record_select('question_answers',
                'question = ? AND ' . $DB->sql_compare_text('answer') . ' = ?',
                [$q3->id, 'toad'], '*', MUST_EXIST);
        $DB->set_field('question_answers', 'fraction', 0.5, ['id' => $toadanswer->id]);

        $q4 = $questiongenerator->update_question($q, null,
                ['name' => 'Toad now scores 0.3',
                    'status' => question_version_status::QUESTION_STATUS_DRAFT]);
        $toadanswer = $DB->get_record_select('question_answers',
                'question = ? AND ' . $DB->sql_compare_text('answer') . ' = ?',
                [$q4->id, 'toad'], '*', MUST_EXIST);
        $DB->set_field('question_answers', 'fraction', 0.3, ['id' => $toadanswer->id]);

        // Now change the thquiz back to always latest and regrade again.
        submit_question_version::execute($slot->slotid, 0);
        $report->clear_regrade_date_cache();
        $report->regrade_attempt($attempt);

        // Score should now be 5, because v3 is the latest non-draft version.
        $attemptobj = thquiz_attempt::create($attempt->id);
        $this->assertEquals(5, $attemptobj->get_question_usage()->get_total_mark());
    }
}

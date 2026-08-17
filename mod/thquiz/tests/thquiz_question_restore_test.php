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

namespace mod_thquiz;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/thquiz_question_helper_test_trait.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/mod/thquiz/locallib.php');

/**
 * Thquiz backup and restore tests.
 *
 * @package    mod_thquiz
 * @category   test
 * @copyright  2021 Catalyst IT Australia Pty Ltd
 * @author     Safat Shahin <safatshahin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_thquiz\question\bank\qbank_helper
 * @coversDefaultClass \backup_thquiz_activity_structure_step
 * @coversDefaultClass \restore_thquiz_activity_structure_step
 */
class thquiz_question_restore_test extends \advanced_testcase {
    use \thquiz_question_helper_test_trait;

    /**
     * @var \stdClass test student user.
     */
    protected $student;

    /**
     * Called before every test.
     */
    public function setUp(): void {
        global $USER;
        parent::setUp();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_user();
        $this->user = $USER;
    }

    /**
     * Test a thquiz backup and restore in a different course without attempts for course question bank.
     *
     * @covers ::get_question_structure
     */
    public function test_thquiz_restore_in_a_different_course_using_course_question_bank() {
        $this->resetAfterTest();

        // Create the test thquiz.
        $thquiz = $this->create_test_thquiz($this->course);
        $oldthquizcontext = \context_module::instance($thquiz->cmid);
        // Test for questions from a different context.
        $coursecontext = \context_course::instance($this->course->id);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->add_two_regular_questions($questiongenerator, $thquiz, ['contextid' => $coursecontext->id]);
        $this->add_one_random_question($questiongenerator, $thquiz, ['contextid' => $coursecontext->id]);

        // Make the backup.
        $backupid = $this->backup_thquiz($thquiz, $this->user);

        // Delete the current course to make sure there is no data.
        delete_course($this->course, false);

        // Check if the questions and associated data are deleted properly.
        $this->assertEquals(0, count(\mod_thquiz\question\bank\qbank_helper::get_question_structure(
                $thquiz->id, $oldthquizcontext)));

        // Restore the course.
        $newcourse = $this->getDataGenerator()->create_course();
        $this->restore_thquiz($backupid, $newcourse, $this->user);

        // Verify.
        $modules = get_fast_modinfo($newcourse->id)->get_instances_of('thquiz');
        $module = reset($modules);
        $questions = \mod_thquiz\question\bank\qbank_helper::get_question_structure(
                $module->instance, $module->context);
        $this->assertCount(3, $questions);
    }

    /**
     * Test a thquiz backup and restore in a different course without attempts for thquiz question bank.
     *
     * @covers ::get_question_structure
     */
    public function test_thquiz_restore_in_a_different_course_using_thquiz_question_bank() {
        $this->resetAfterTest();

        // Create the test thquiz.
        $thquiz = $this->create_test_thquiz($this->course);
        // Test for questions from a different context.
        $thquizcontext = \context_module::instance($thquiz->cmid);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->add_two_regular_questions($questiongenerator, $thquiz, ['contextid' => $thquizcontext->id]);
        $this->add_one_random_question($questiongenerator, $thquiz, ['contextid' => $thquizcontext->id]);

        // Make the backup.
        $backupid = $this->backup_thquiz($thquiz, $this->user);

        // Delete the current course to make sure there is no data.
        delete_course($this->course, false);

        // Check if the questions and associated datas are deleted properly.
        $this->assertEquals(0, count(\mod_thquiz\question\bank\qbank_helper::get_question_structure(
                $thquiz->id, $thquizcontext)));

        // Restore the course.
        $newcourse = $this->getDataGenerator()->create_course();
        $this->restore_thquiz($backupid, $newcourse, $this->user);

        // Verify.
        $modules = get_fast_modinfo($newcourse->id)->get_instances_of('thquiz');
        $module = reset($modules);
        $this->assertEquals(3, count(\mod_thquiz\question\bank\qbank_helper::get_question_structure(
                $module->instance, $module->context)));
    }

    /**
     * Count the questions for the context.
     *
     * @param int $contextid
     * @param string $extracondition
     * @return int the number of questions.
     */
    protected function question_count(int $contextid, string $extracondition = ''): int {
        global $DB;
        return $DB->count_records_sql(
            "SELECT COUNT(q.id)
               FROM {question} q
               JOIN {question_versions} qv ON qv.questionid = q.id
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
               JOIN {question_categories} qc on qc.id = qbe.questioncategoryid
              WHERE qc.contextid = ?
              $extracondition", [$contextid]);
    }

    /**
     * Test if a duplicate does not duplicate questions in course question bank.
     *
     * @covers ::duplicate_module
     */
    public function test_thquiz_duplicate_does_not_duplicate_course_question_bank_questions() {
        $this->resetAfterTest();
        $thquiz = $this->create_test_thquiz($this->course);
        // Test for questions from a different context.
        $context = \context_course::instance($this->course->id);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->add_two_regular_questions($questiongenerator, $thquiz, ['contextid' => $context->id]);
        $this->add_one_random_question($questiongenerator, $thquiz, ['contextid' => $context->id]);
        // Count the questions in course context.
        $this->assertEquals(7, $this->question_count($context->id));
        $newthquiz = $this->duplicate_thquiz($this->course, $thquiz);
        $this->assertEquals(7, $this->question_count($context->id));
        $context = \context_module::instance($newthquiz->id);
        // Count the questions in the thquiz context.
        $this->assertEquals(0, $this->question_count($context->id));
    }

    /**
     * Test thquiz duplicate for thquiz question bank.
     *
     * @covers ::duplicate_module
     */
    public function test_thquiz_duplicate_for_thquiz_question_bank_questions() {
        $this->resetAfterTest();
        $thquiz = $this->create_test_thquiz($this->course);
        // Test for questions from a different context.
        $context = \context_module::instance($thquiz->cmid);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->add_two_regular_questions($questiongenerator, $thquiz, ['contextid' => $context->id]);
        $this->add_one_random_question($questiongenerator, $thquiz, ['contextid' => $context->id]);
        // Count the questions in course context.
        $this->assertEquals(7, $this->question_count($context->id));
        $newthquiz = $this->duplicate_thquiz($this->course, $thquiz);
        $this->assertEquals(7, $this->question_count($context->id));
        $context = \context_module::instance($newthquiz->id);
        // Count the questions in the thquiz context.
        $this->assertEquals(7, $this->question_count($context->id));
    }

    /**
     * Test thquiz restore with attempts.
     *
     * @covers ::get_question_structure
     */
    public function test_thquiz_restore_with_attempts() {
        $this->resetAfterTest();

        // Create a thquiz.
        $thquiz = $this->create_test_thquiz($this->course);
        $thquizcontext = \context_module::instance($thquiz->cmid);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->add_two_regular_questions($questiongenerator, $thquiz, ['contextid' => $thquizcontext->id]);
        $this->add_one_random_question($questiongenerator, $thquiz, ['contextid' => $thquizcontext->id]);

        // Attempt it as a student, and check.
        /** @var \question_usage_by_activity $quba */
        [, $quba] = $this->attempt_thquiz($thquiz, $this->student);
        $this->assertEquals(3, $quba->question_count());
        $this->assertCount(1, thquiz_get_user_attempts($thquiz->id, $this->student->id));

        // Make the backup.
        $backupid = $this->backup_thquiz($thquiz, $this->user);

        // Delete the current course to make sure there is no data.
        delete_course($this->course, false);

        // Restore the backup.
        $newcourse = $this->getDataGenerator()->create_course();
        $this->restore_thquiz($backupid, $newcourse, $this->user);

        // Verify.
        $modules = get_fast_modinfo($newcourse->id)->get_instances_of('thquiz');
        $module = reset($modules);
        $this->assertCount(1, thquiz_get_user_attempts($module->instance, $this->student->id));
        $this->assertCount(3, \mod_thquiz\question\bank\qbank_helper::get_question_structure(
                $module->instance, $module->context));
    }

    /**
     * Test pre 4.0 thquiz restore for regular questions.
     *
     * @covers ::process_thquiz_question_legacy_instance
     */
    public function test_pre_4_thquiz_restore_for_regular_questions() {
        global $USER, $DB;
        $this->resetAfterTest();
        $backupid = 'abc';
        $backuppath = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname(
            __DIR__ . "/fixtures/moodle_28_thquiz.mbz", $backuppath);

        // Do the restore to new course with default settings.
        $categoryid = $DB->get_field_sql("SELECT MIN(id) FROM {course_categories}");
        $newcourseid = \restore_dbops::create_new_course('Test fullname', 'Test shortname', $categoryid);
        $rc = new \restore_controller($backupid, $newcourseid, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id,
            \backup::TARGET_NEW_COURSE);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // Get the information about the resulting course and check that it is set up correctly.
        $modinfo = get_fast_modinfo($newcourseid);
        $thquiz = array_values($modinfo->get_instances_of('thquiz'))[0];
        $thquizobj = \thquiz::create($thquiz->instance);
        $structure = structure::create_for_thquiz($thquizobj);

        // Are the correct slots returned?
        $slots = $structure->get_slots();
        $this->assertCount(2, $slots);

        $thquizobj->preload_questions();
        $thquizobj->load_questions();
        $questions = $thquizobj->get_questions();
        $this->assertCount(2, $questions);

        // Count the questions in thquiz qbank.
        $this->assertEquals(2, $this->question_count($thquizobj->get_context()->id));
    }

    /**
     * Test pre 4.0 thquiz restore for random questions.
     *
     * @covers ::process_thquiz_question_legacy_instance
     */
    public function test_pre_4_thquiz_restore_for_random_questions() {
        global $USER, $DB;
        $this->resetAfterTest();

        $backupid = 'abc';
        $backuppath = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname(
            __DIR__ . "/fixtures/random_by_tag_thquiz.mbz", $backuppath);

        // Do the restore to new course with default settings.
        $categoryid = $DB->get_field_sql("SELECT MIN(id) FROM {course_categories}");
        $newcourseid = \restore_dbops::create_new_course('Test fullname', 'Test shortname', $categoryid);
        $rc = new \restore_controller($backupid, $newcourseid, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id,
            \backup::TARGET_NEW_COURSE);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // Get the information about the resulting course and check that it is set up correctly.
        $modinfo = get_fast_modinfo($newcourseid);
        $thquiz = array_values($modinfo->get_instances_of('thquiz'))[0];
        $thquizobj = \thquiz::create($thquiz->instance);
        $structure = structure::create_for_thquiz($thquizobj);

        // Are the correct slots returned?
        $slots = $structure->get_slots();
        $this->assertCount(1, $slots);

        $thquizobj->preload_questions();
        $thquizobj->load_questions();
        $questions = $thquizobj->get_questions();
        $this->assertCount(1, $questions);

        // Count the questions for course question bank.
        $this->assertEquals(6, $this->question_count(\context_course::instance($newcourseid)->id));
        $this->assertEquals(6, $this->question_count(\context_course::instance($newcourseid)->id,
            "AND q.qtype <> 'random'"));

        // Count the questions in thquiz qbank.
        $this->assertEquals(0, $this->question_count($thquizobj->get_context()->id));
    }

    /**
     * Test pre 4.0 thquiz restore for random question tags.
     *
     * @covers ::process_thquiz_question_legacy_instance
     */
    public function test_pre_4_thquiz_restore_for_random_question_tags() {
        global $USER, $DB;
        $this->resetAfterTest();
        $randomtags = [
            '1' => ['first question', 'one', 'number one'],
            '2' => ['first question', 'one', 'number one'],
            '3' => ['one', 'number one', 'second question'],
        ];
        $backupid = 'abc';
        $backuppath = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname(
            __DIR__ . "/fixtures/moodle_311_thquiz.mbz", $backuppath);

        // Do the restore to new course with default settings.
        $categoryid = $DB->get_field_sql("SELECT MIN(id) FROM {course_categories}");
        $newcourseid = \restore_dbops::create_new_course('Test fullname', 'Test shortname', $categoryid);
        $rc = new \restore_controller($backupid, $newcourseid, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id,
            \backup::TARGET_NEW_COURSE);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // Get the information about the resulting course and check that it is set up correctly.
        $modinfo = get_fast_modinfo($newcourseid);
        $thquiz = array_values($modinfo->get_instances_of('thquiz'))[0];
        $thquizobj = \thquiz::create($thquiz->instance);
        $structure = \mod_thquiz\structure::create_for_thquiz($thquizobj);

        // Count the questions in thquiz qbank.
        $context = \context_module::instance(get_coursemodule_from_instance("thquiz", $thquizobj->get_thquizid(), $newcourseid)->id);
        $this->assertEquals(2, $this->question_count($context->id));

        // Are the correct slots returned?
        $slots = $structure->get_slots();
        $this->assertCount(3, $slots);

        // Check if the tags match with the actual restored data.
        foreach ($slots as $slot) {
            $setreference = $DB->get_record('question_set_references',
                ['itemid' => $slot->id, 'component' => 'mod_thquiz', 'questionarea' => 'slot']);
            $filterconditions = json_decode($setreference->filtercondition);
            $tags = [];
            foreach ($filterconditions->tags as $tagstring) {
                $tag = explode(',', $tagstring);
                $tags[] = $tag[1];
            }
            $this->assertEquals([], array_diff($randomtags[$slot->slot], $tags));
        }

    }

    /**
     * Test pre 4.0 thquiz restore for random question used on multiple thquizzes.
     *
     * @covers ::process_thquiz_question_legacy_instance
     */
    public function test_pre_4_thquiz_restore_shared_random_question() {
        global $USER, $DB;
        $this->resetAfterTest();

        $backupid = 'abc';
        $backuppath = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname(
                __DIR__ . "/fixtures/pre-40-shared-random-question.mbz", $backuppath);

        // Do the restore to new course with default settings.
        $categoryid = $DB->get_field_sql("SELECT MIN(id) FROM {course_categories}");
        $newcourseid = \restore_dbops::create_new_course('Test fullname', 'Test shortname', $categoryid);
        $rc = new \restore_controller($backupid, $newcourseid, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id,
                \backup::TARGET_NEW_COURSE);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // Get the information about the resulting course and check that it is set up correctly.
        // Each thquiz should contain an instance of the random question.
        $modinfo = get_fast_modinfo($newcourseid);
        $thquizzes = $modinfo->get_instances_of('thquiz');
        $this->assertCount(2, $thquizzes);
        foreach ($thquizzes as $thquiz) {
            $thquizobj = \thquiz::create($thquiz->instance);
            $structure = structure::create_for_thquiz($thquizobj);

            // Are the correct slots returned?
            $slots = $structure->get_slots();
            $this->assertCount(1, $slots);

            $thquizobj->preload_questions();
            $thquizobj->load_questions();
            $questions = $thquizobj->get_questions();
            $this->assertCount(1, $questions);
        }

        // Count the questions for course question bank.
        // We should have a single question, the random question should have been deleted after the restore.
        $this->assertEquals(1, $this->question_count(\context_course::instance($newcourseid)->id));
        $this->assertEquals(1, $this->question_count(\context_course::instance($newcourseid)->id,
                "AND q.qtype <> 'random'"));

        // Count the questions in thquiz qbank.
        $this->assertEquals(0, $this->question_count($thquizobj->get_context()->id));
    }

    /**
     * Ensure that question slots are correctly backed up and restored with all properties.
     *
     * @covers \backup_thquiz_activity_structure_step::define_structure()
     * @return void
     */
    public function test_backup_restore_question_slots(): void {
        $this->resetAfterTest(true);

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        $user1 = $this->getDataGenerator()->create_and_enrol($course1, 'editingteacher');
        $this->getDataGenerator()->enrol_user($user1->id, $course2->id, 'editingteacher');

        // Make a thquiz.
        $thquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_thquiz');

        $thquiz = $thquizgenerator->create_instance(['course' => $course1->id, 'questionsperpage' => 0, 'grade' => 100.0,
                'sumgrades' => 3]);

        // Create some fixed and random questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        $matchq = $questiongenerator->create_question('match', null, ['category' => $cat->id]);
        $randomcat = $questiongenerator->create_question_category();
        $questiongenerator->create_question('shortanswer', null, ['category' => $randomcat->id]);
        $questiongenerator->create_question('numerical', null, ['category' => $randomcat->id]);
        $questiongenerator->create_question('match', null, ['category' => $randomcat->id]);

        // Add them to the thquiz.
        thquiz_add_thquiz_question($saq->id, $thquiz, 1, 3);
        thquiz_add_thquiz_question($numq->id, $thquiz, 2, 2);
        thquiz_add_thquiz_question($matchq->id, $thquiz, 3, 1);
        thquiz_add_random_questions($thquiz, 3, $randomcat->id, 2, false);

        $thquizobj = \thquiz::create($thquiz->id, $user1->id);
        $originalstructure = \mod_thquiz\structure::create_for_thquiz($thquizobj);
        $originalslots = $originalstructure->get_slots();

        // Set one slot to requireprevious.
        $lastslot = end($originalslots);
        $originalstructure->update_question_dependency($lastslot->id, true);

        // Backup and restore the thquiz.
        $backupid = $this->backup_thquiz($thquiz, $user1);
        $this->restore_thquiz($backupid, $course2, $user1);

        // Ensure the restored slots match the original slots.
        $modinfo = get_fast_modinfo($course2);
        $thquizzes = $modinfo->get_instances_of('thquiz');
        $restoredthquiz = reset($thquizzes);
        $restoredthquizobj = \thquiz::create($restoredthquiz->instance, $user1->id);
        $restoredstructure = \mod_thquiz\structure::create_for_thquiz($restoredthquizobj);
        $restoredslots = array_values($restoredstructure->get_slots());
        $originalstructure = \mod_thquiz\structure::create_for_thquiz($thquizobj);
        $originalslots = array_values($originalstructure->get_slots());
        foreach ($restoredslots as $key => $restoredslot) {
            $originalslot = $originalslots[$key];
            $this->assertEquals($originalslot->thquizid, $thquiz->id);
            $this->assertEquals($restoredslot->thquizid, $restoredthquiz->instance);
            $this->assertEquals($originalslot->slot, $restoredslot->slot);
            $this->assertEquals($originalslot->page, $restoredslot->page);
            $this->assertEquals($originalslot->requireprevious, $restoredslot->requireprevious);
            $this->assertEquals($originalslot->maxmark, $restoredslot->maxmark);
        }
    }
}

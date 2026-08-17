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
 * Helper trait for thquiz question unit tests.
 *
 * This trait helps to execute different tests for thquiz, for example if it needs to create a thquiz, add question
 * to the question, add random quetion to the thquiz, do a backup or restore.
 *
 * @package    mod_thquiz
 * @category   test
 * @copyright  2021 Catalyst IT Australia Pty Ltd
 * @author     Safat Shahin <safatshahin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait thquiz_question_helper_test_trait {

    /** @var \stdClass $course Test course to contain thquiz. */
    protected $course;

    /** @var \stdClass $thquiz A test thquiz. */
    protected $thquiz;

    /** @var \stdClass $user A test logged-in user. */
    protected $user;

    /**
     * Create a test thquiz for the specified course.
     *
     * @param \stdClass $course
     * @return  \stdClass
     */
    protected function create_test_thquiz(\stdClass $course): \stdClass {

        /** @var mod_thquiz_generator $thquizgenerator */
        $thquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_thquiz');

        return $thquizgenerator->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => 100.0,
            'sumgrades' => 2,
        ]);
    }

    /**
     * Helper method to add regular questions in thquiz.
     *
     * @param component_generator_base $questiongenerator
     * @param \stdClass $thquiz
     * @param array $override
     */
    protected function add_two_regular_questions($questiongenerator, \stdClass $thquiz, $override = null): void {
        // Create a couple of questions.
        $cat = $questiongenerator->create_question_category($override);

        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        // Create another version.
        $questiongenerator->update_question($saq);
        thquiz_add_thquiz_question($saq->id, $thquiz);
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        // Create two version.
        $questiongenerator->update_question($numq);
        $questiongenerator->update_question($numq);
        thquiz_add_thquiz_question($numq->id, $thquiz);
    }

    /**
     * Helper method to add random question to thquiz.
     *
     * @param component_generator_base $questiongenerator
     * @param \stdClass $thquiz
     * @param array $override
     */
    protected function add_one_random_question($questiongenerator, \stdClass $thquiz, $override = []): void {
        // Create a random question.
        $cat = $questiongenerator->create_question_category($override);
        $questiongenerator->create_question('truefalse', null, array('category' => $cat->id));
        $questiongenerator->create_question('essay', null, array('category' => $cat->id));
        thquiz_add_random_questions($thquiz, 0, $cat->id, 1, false);
    }

    /**
     * Attempt questions for a thquiz and user.
     *
     * @param \stdClass $thquiz Thquiz to attempt.
     * @param \stdClass $user A user to attempt the thquiz.
     * @param int $attemptnumber
     * @return array
     */
    protected function attempt_thquiz(\stdClass $thquiz, \stdClass $user, $attemptnumber = 1): array {
        $this->setUser($user);

        $starttime = time();
        $thquizobj = thquiz::create($thquiz->id, $user->id);

        $quba = question_engine::make_questions_usage_by_activity('mod_thquiz', $thquizobj->get_context());
        $quba->set_preferred_behaviour($thquizobj->get_thquiz()->preferredbehaviour);

        // Start the attempt.
        $attempt = thquiz_create_attempt($thquizobj, $attemptnumber, null, $starttime, false, $user->id);
        thquiz_start_new_attempt($thquizobj, $quba, $attempt, $attemptnumber, $starttime);
        thquiz_attempt_save_started($thquizobj, $quba, $attempt);

        // Finish the attempt.
        $attemptobj = thquiz_attempt::create($attempt->id);
        $attemptobj->process_finish($starttime, false);

        $this->setUser();
        return [$thquizobj, $quba, $attemptobj];
    }

    /**
     * A helper method to backup test thquiz.
     *
     * @param \stdClass $thquiz Thquiz to attempt.
     * @param \stdClass $user A user to attempt the thquiz.
     * @return string A backup ID ready to be restored.
     */
    protected function backup_thquiz(\stdClass $thquiz, \stdClass $user): string {
        global $CFG;

        // Get the necessary files to perform backup and restore.
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $backupid = 'test-question-backup-restore';

        $bc = new backup_controller(backup::TYPE_1ACTIVITY, $thquiz->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_GENERAL, $user->id);
        $bc->execute_plan();

        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $fp = get_file_packer('application/vnd.moodle.backup');
        $filepath = $CFG->dataroot . '/temp/backup/' . $backupid;
        $file->extract_to_pathname($fp, $filepath);
        $bc->destroy();

        return $backupid;
    }

    /**
     * A helper method to restore provided backup.
     *
     * @param string $backupid Backup ID to restore.
     * @param stdClass $course
     * @param stdClass $user
     */
    protected function restore_thquiz(string $backupid, stdClass $course, stdClass $user): void {
        $rc = new restore_controller($backupid, $course->id,
            backup::INTERACTIVE_NO, backup::MODE_GENERAL, $user->id, backup::TARGET_CURRENT_ADDING);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
    }

    /**
     * A helper method to emulate duplication of the thquiz.
     *
     * @param stdClass $course
     * @param stdClass $thquiz
     * @return \cm_info|null
     */
    protected function duplicate_thquiz($course, $thquiz): ?\cm_info {
        return duplicate_module($course, get_fast_modinfo($course)->get_cm($thquiz->cmid));
    }
}

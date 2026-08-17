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

namespace mod_thquiz\task;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core_user;
use moodle_recordset;
use question_display_options;
use mod_thquiz_display_options;
use thquiz_attempt;

require_once($CFG->dirroot . '/mod/thquiz/locallib.php');

/**
 * Cron Thquiz Notify Attempts Graded Task.
 *
 * @package    mod_thquiz
 * @copyright  2021 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class thquiz_notify_attempt_manual_grading_completed extends \core\task\scheduled_task {
    /**
     * @var int|null For using in unit testing only. Override the time we consider as now.
     */
    protected $forcedtime = null;

    /**
     * Get name of schedule task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('notifyattemptsgradedtask', 'mod_thquiz');
    }

    /**
     * To let this class be unit tested, we wrap all accesses to the current time in this method.
     *
     * @return int The current time.
     */
    protected function get_time(): int {
        if (PHPUNIT_TEST && $this->forcedtime !== null) {
            return $this->forcedtime;
        }

        return time();
    }

    /**
     * For testing only, pretend the current time is different.
     *
     * @param int $time The time to set as the current time.
     */
    public function set_time_for_testing(int $time): void {
        if (!PHPUNIT_TEST) {
            throw new \coding_exception('set_time_for_testing should only be used in unit tests.');
        }
        $this->forcedtime = $time;
    }

    /**
     * Execute sending notification for manual graded attempts.
     */
    public function execute() {
        global $DB;

        mtrace('Looking for thquiz attempts which may need a graded notification sent...');

        $attempts = $this->get_list_of_attempts();
        $course = null;
        $thquiz = null;
        $cm = null;

        foreach ($attempts as $attempt) {
            mtrace('Checking attempt ' . $attempt->id . ' at thquiz ' . $attempt->thquiz . '.');

            if (!$thquiz || $attempt->thquiz != $thquiz->id) {
                $thquiz = $DB->get_record('thquiz', ['id' => $attempt->thquiz], '*', MUST_EXIST);
                $cm = get_coursemodule_from_instance('thquiz', $attempt->thquiz);
            }

            if (!$course || $course->id != $thquiz->course) {
                $course = $DB->get_record('course', ['id' => $thquiz->course], '*', MUST_EXIST);
                $coursecontext = context_course::instance($thquiz->course);
            }

            $thquiz = thquiz_update_effective_access($thquiz, $attempt->userid);
            $attemptobj = new thquiz_attempt($attempt, $thquiz, $cm, $course, false);
            $options = mod_thquiz_display_options::make_from_thquiz($thquiz, thquiz_attempt_state($thquiz, $attempt));

            if ($options->manualcomment == question_display_options::HIDDEN) {
                // User cannot currently see the feedback, so don't message them.
                // However, this may change in future, so leave them on the list.
                continue;
            }

            if (!has_capability('mod/thquiz:emailnotifyattemptgraded', $coursecontext, $attempt->userid, false)) {
                // User not eligible to get a notification. Mark them done while doing nothing.
                $DB->set_field('thquiz_attempts', 'gradednotificationsenttime', $attempt->timefinish, ['id' => $attempt->id]);
                continue;
            }

            // OK, send notification.
            mtrace('Sending email to user ' . $attempt->userid . '...');
            $ok = thquiz_send_notify_manual_graded_message($attemptobj, core_user::get_user($attempt->userid));
            if ($ok) {
                mtrace('Send email successfully!');
                $attempt->gradednotificationsenttime = $this->get_time();
                $DB->set_field('thquiz_attempts', 'gradednotificationsenttime', $attempt->gradednotificationsenttime,
                        ['id' => $attempt->id]);
                $attemptobj->fire_attempt_manual_grading_completed_event();
            }
        }

        $attempts->close();
    }

    /**
     * Get a number of records as an array of thquiz_attempts using a SQL statement.
     *
     * @return moodle_recordset Of thquiz_attempts that need to be processed.
     */
    public function get_list_of_attempts(): moodle_recordset {
        global $DB;

        $delaytime = $this->get_time() - get_config('thquiz', 'notifyattemptgradeddelay');

        $sql = "SELECT qa.*
                  FROM {thquiz_attempts} qa
                  JOIN {thquiz} thquiz ON thquiz.id = qa.thquiz
                 WHERE qa.state = 'finished'
                       AND qa.gradednotificationsenttime IS NULL
                       AND qa.sumgrades IS NOT NULL
                       AND qa.timemodified < :delaytime
              ORDER BY thquiz.course, qa.thquiz";

        return $DB->get_recordset_sql($sql, ['delaytime' => $delaytime]);
    }
}

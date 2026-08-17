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
 * Library code used by thquiz cron.
 *
 * @package   mod_thquiz
 * @copyright 2012 the Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/thquiz/locallib.php');


/**
 * This class holds all the code for automatically updating all attempts that have
 * gone over their time limit.
 *
 * @copyright 2012 the Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_thquiz_overdue_attempt_updater {

    /**
     * Do the processing required.
     * @param int $timenow the time to consider as 'now' during the processing.
     * @param int $processto only process attempt with timecheckstate longer ago than this.
     * @return array with two elements, the number of attempt considered, and how many different thquizzes that was.
     */
    public function update_overdue_attempts($timenow, $processto) {
        global $DB;

        $attemptstoprocess = $this->get_list_of_overdue_attempts($processto);

        $course = null;
        $thquiz = null;
        $cm = null;

        $count = 0;
        $thquizcount = 0;
        foreach ($attemptstoprocess as $attempt) {
            try {

                // If we have moved on to a different thquiz, fetch the new data.
                if (!$thquiz || $attempt->thquiz != $thquiz->id) {
                    $thquiz = $DB->get_record('thquiz', array('id' => $attempt->thquiz), '*', MUST_EXIST);
                    $cm = get_coursemodule_from_instance('thquiz', $attempt->thquiz);
                    $thquizcount += 1;
                }

                // If we have moved on to a different course, fetch the new data.
                if (!$course || $course->id != $thquiz->course) {
                    $course = $DB->get_record('course', array('id' => $thquiz->course), '*', MUST_EXIST);
                }

                // Make a specialised version of the thquiz settings, with the relevant overrides.
                $thquizforuser = clone($thquiz);
                $thquizforuser->timeclose = $attempt->usertimeclose;
                $thquizforuser->timelimit = $attempt->usertimelimit;

                // Trigger any transitions that are required.
                $attemptobj = new thquiz_attempt($attempt, $thquizforuser, $cm, $course);
                $attemptobj->handle_if_time_expired($timenow, false);
                $count += 1;

            } catch (moodle_exception $e) {
                // If an error occurs while processing one attempt, don't let that kill cron.
                mtrace("Error while processing attempt {$attempt->id} at {$attempt->thquiz} thquiz:");
                mtrace($e->getMessage());
                mtrace($e->getTraceAsString());
                // Close down any currently open transactions, otherwise one error
                // will stop following DB changes from being committed.
                $DB->force_transaction_rollback();
            }
        }

        $attemptstoprocess->close();
        return array($count, $thquizcount);
    }

    /**
     * @return moodle_recordset of thquiz_attempts that need to be processed because time has
     *     passed. The array is sorted by courseid then thquizid.
     */
    public function get_list_of_overdue_attempts($processto) {
        global $DB;


        // SQL to compute timeclose and timelimit for each attempt:
        $thquizausersql = thquiz_get_attempt_usertime_sql(
                "ithquiza.state IN ('inprogress', 'overdue') AND ithquiza.timecheckstate <= :iprocessto");

        // This query should have all the thquiz_attempts columns.
        return $DB->get_recordset_sql("
         SELECT thquiza.*,
                thquizauser.usertimeclose,
                thquizauser.usertimelimit

           FROM {thquiz_attempts} thquiza
           JOIN {thquiz} thquiz ON thquiz.id = thquiza.thquiz
           JOIN ( $thquizausersql ) thquizauser ON thquizauser.id = thquiza.id

          WHERE thquiza.state IN ('inprogress', 'overdue')
            AND thquiza.timecheckstate <= :processto
       ORDER BY thquiz.course, thquiza.thquiz",

                array('processto' => $processto, 'iprocessto' => $processto));
    }
}

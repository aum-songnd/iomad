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
 * Version details
 *
 * @package   local_th_config_login
 * @copyright 2017 Manoj Solanki (Coventry University)
 * @copyright
 * @copyright
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

namespace local_th_config_login\task;

/**
 * Task for updating RSS feeds for rss client block
 *
 * @package   block_recent_activity
 * @author    Farhan Karmali <farhan6318@gmail.com>
 * @copyright Farhan Karmali 2018
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class clean_otp_expiry extends \core\task\scheduled_task {

    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskname', 'local_th_config_login');
    }

    /**
     * Remove old entries from table block_recent_activity
     */
    public function execute() {
        global $DB;

        $timeexpiry = get_config('local_th_config_login', 'timeexpiry');
        $pwresettime = isset($timeexpiry) ? $timeexpiry : 1800;
        $earliestvalid = time() - $pwresettime - DAYSECS;
        $DB->delete_records_select('local_th_user_login', "timerequested < ?", array($earliestvalid));
        mtrace('Cleaned up old OTP expired records');
    }
}

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

namespace thquiz_statistics\task;

use core\dml\sql_join;
use thquiz_attempt;
use thquiz_statistics_report;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/thquiz/locallib.php');
require_once($CFG->dirroot . '/mod/thquiz/report/statistics/statisticslib.php');
require_once($CFG->dirroot . '/mod/thquiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/thquiz/report/statistics/report.php');

/**
 * Re-calculate question statistics.
 *
 * @package    thquiz_statistics
 * @copyright  2022 Catalyst IT Australia Pty Ltd
 * @author     Nathan Nguyen <nathannguyen@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recalculate extends \core\task\adhoc_task {
    /**
     * The time to delay queued runs by, to prevent repeated recalculations.
     */
    const DELAY = HOURSECS;

    /**
     * Create a new instance of the task.
     *
     * This sets the properties so that only one task will be queued at a time for a given thquiz.
     *
     * @param int $thquizid
     * @return recalculate
     */
    public static function instance(int $thquizid): recalculate {
        $task = new self();
        $task->set_component('thquiz_statistics');
        $task->set_custom_data((object)[
            'thquizid' => $thquizid,
        ]);
        return $task;
    }


    public function get_name(): string {
        return get_string('recalculatetask', 'thquiz_statistics');
    }

    public function execute(): void {
        global $DB;
        $dateformat = get_string('strftimedatetimeshortaccurate', 'core_langconfig');
        $data = $this->get_custom_data();
        $thquiz = $DB->get_record('thquiz', ['id' => $data->thquizid]);
        if (!$thquiz) {
            mtrace('Could not find thquiz with ID ' . $data->thquizid . '.');
            return;
        }
        $course = $DB->get_record('course', ['id' => $thquiz->course]);
        if (!$course) {
            mtrace('Could not find course with ID ' . $thquiz->course . '.');
            return;
        }
        $attemptcount = $DB->count_records('thquiz_attempts', ['thquiz' => $data->thquizid, 'state' => thquiz_attempt::FINISHED]);
        if ($attemptcount === 0) {
            mtrace('Could not find any finished attempts for course with ID ' . $data->thquizid . '.');
            return;
        }

        mtrace("Re-calculating statistics for thquiz {$thquiz->name} ({$thquiz->id}) " .
            "from course {$course->shortname} ({$course->id}) with {$attemptcount} attempts, start time " .
            userdate(time(), $dateformat) . " ...");

        $qubaids = thquiz_statistics_qubaids_condition(
            $thquiz->id,
            new sql_join(),
            $thquiz->grademethod
        );

        $report = new thquiz_statistics_report();
        $report->clear_cached_data($qubaids);
        $report->calculate_questions_stats_for_question_bank($thquiz->id);
        mtrace('    Calculations completed at ' . userdate(time(), $dateformat) . '.');
    }

    /**
     * Queue an instance of this task to happen after a delay.
     *
     * Multiple events may happen over a short period that require a recalculation. Rather than
     * run the recalculation each time, this will queue a single run of the task for a given thquiz,
     * within the delay period.
     *
     * @param int $thquizid The thquiz to run the recalculation for.
     * @return bool true of the task was queued.
     */
    public static function queue_future_run(int $thquizid): bool {
        $task = self::instance($thquizid);
        $task->set_next_run_time(time() + self::DELAY);
        return \core\task\manager::queue_adhoc_task($task, true);
    }
}

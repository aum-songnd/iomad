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

namespace thquiz_statistics;

use core\dml\sql_join;

/**
 * Clear the statistics cache when the thquiz structure is modified.
 *
 * @package   thquiz_statistics
 * @copyright 2023 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Mark Johnson <mark.johnson@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class thquiz_structure_modified {
    /**
     * Clear the statistics cache.
     *
     * @param int $thquizid The thquiz to clear the cache for.
     * @return void
     */
    public static function callback(int $thquizid): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/thquiz/report/statistics/statisticslib.php');
        require_once($CFG->dirroot . '/mod/thquiz/report/statistics/report.php');
        $thquiz = $DB->get_record('thquiz', ['id' => $thquizid]);
        if (!$thquiz) {
            throw new \coding_exception('Could not find thquiz with ID ' . $thquizid . '.');
        }
        $qubaids = thquiz_statistics_qubaids_condition(
            $thquiz->id,
            new sql_join(),
            $thquiz->grademethod
        );

        $report = new \thquiz_statistics_report();
        $report->clear_cached_data($qubaids);
    }
}

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
 * Base class for thquiz report plugins.
 *
 * @package   mod_thquiz
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Base class for thquiz report plugins.
 *
 * Doesn't do anything on it's own -- it needs to be extended.
 * This class displays thquiz reports.  Because it is called from
 * within /mod/thquiz/report.php you can assume that the page header
 * and footer are taken care of.
 *
 * This file can refer to itself as report.php to pass variables
 * to itself - all these will also be globally available.  You must
 * pass "id=$cm->id" or q=$thquiz->id", and "mode=reportname".
 *
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class thquiz_default_report {
    const NO_GROUPS_ALLOWED = -2;

    /**
     * Override this function to displays the report.
     * @param $cm the course-module for this thquiz.
     * @param $course the coures we are in.
     * @param $thquiz this thquiz.
     */
    public abstract function display($cm, $course, $thquiz);

    /**
     * Initialise some parts of $PAGE and start output.
     *
     * @param object $cm the course_module information.
     * @param object $coures the course settings.
     * @param object $thquiz the thquiz settings.
     * @param string $reportmode the report name.
     */
    public function print_header_and_tabs($cm, $course, $thquiz, $reportmode = 'overview') {
        global $PAGE, $OUTPUT, $CFG;

        // Print the page header.
        $PAGE->set_title($thquiz->name);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        $context = context_module::instance($cm->id);
        if (!$PAGE->has_secondary_navigation()) {
            echo $OUTPUT->heading(format_string($thquiz->name, true, array('context' => $context)));
        }
        if (!empty($CFG->enableplagiarism)) {
            require_once($CFG->libdir . '/plagiarismlib.php');
            echo plagiarism_update_status($course, $cm);
        }
    }

    /**
     * Get the current group for the user user looking at the report.
     *
     * @param object $cm the course_module information.
     * @param object $coures the course settings.
     * @param context $context the thquiz context.
     * @return int the current group id, if applicable. 0 for all users,
     *      NO_GROUPS_ALLOWED if the user cannot see any group.
     */
    public function get_current_group($cm, $course, $context) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        $currentgroup = groups_get_activity_group($cm, true);

        if ($groupmode == SEPARATEGROUPS && !$currentgroup && !has_capability('moodle/site:accessallgroups', $context)) {
            $currentgroup = self::NO_GROUPS_ALLOWED;
        }

        return $currentgroup;
    }
}

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
 * This script lists all the instances of thquiz in a particular course
 *
 * @package    mod_thquiz
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once("../../config.php");
require_once("locallib.php");

$id = required_param('id', PARAM_INT);
$PAGE->set_url('/mod/thquiz/index.php', array('id'=>$id));
if (!$course = $DB->get_record('course', array('id' => $id))) {
    throw new \moodle_exception('invalidcourseid');
}
$coursecontext = context_course::instance($id);
require_login($course);
$PAGE->set_pagelayout('incourse');

$params = array(
    'context' => $coursecontext
);
$event = \mod_thquiz\event\course_module_instance_list_viewed::create($params);
$event->trigger();

// Print the header.
$strthquizzes = get_string("modulenameplural", "thquiz");
$PAGE->navbar->add($strthquizzes);
$PAGE->set_title($strthquizzes);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($strthquizzes, 2);

// Get all the appropriate data.
if (!$thquizzes = get_all_instances_in_course("thquiz", $course)) {
    notice(get_string('thereareno', 'moodle', $strthquizzes), "../../course/view.php?id=$course->id");
    die;
}

// Check if we need the feedback header.
$showfeedback = false;
foreach ($thquizzes as $thquiz) {
    if (thquiz_has_feedback($thquiz)) {
        $showfeedback=true;
    }
    if ($showfeedback) {
        break;
    }
}

// Configure table for displaying the list of instances.
$headings = array(get_string('name'));
$align = array('left');

array_push($headings, get_string('thquizcloses', 'thquiz'));
array_push($align, 'left');

if (course_format_uses_sections($course->format)) {
    array_unshift($headings, get_string('sectionname', 'format_'.$course->format));
} else {
    array_unshift($headings, '');
}
array_unshift($align, 'center');

$showing = '';

if (has_capability('mod/thquiz:viewreports', $coursecontext)) {
    array_push($headings, get_string('attempts', 'thquiz'));
    array_push($align, 'left');
    $showing = 'stats';

} else if (has_any_capability(array('mod/thquiz:reviewmyattempts', 'mod/thquiz:attempt'),
        $coursecontext)) {
    array_push($headings, get_string('grade', 'thquiz'));
    array_push($align, 'left');
    if ($showfeedback) {
        array_push($headings, get_string('feedback', 'thquiz'));
        array_push($align, 'left');
    }
    $showing = 'grades';

    $grades = $DB->get_records_sql_menu('
            SELECT qg.thquiz, qg.grade
            FROM {thquiz_grades} qg
            JOIN {thquiz} q ON q.id = qg.thquiz
            WHERE q.course = ? AND qg.userid = ?',
            array($course->id, $USER->id));
}

$table = new html_table();
$table->head = $headings;
$table->align = $align;

// Populate the table with the list of instances.
$currentsection = '';
// Get all closing dates.
$timeclosedates = thquiz_get_user_timeclose($course->id);
foreach ($thquizzes as $thquiz) {
    $cm = get_coursemodule_from_instance('thquiz', $thquiz->id);
    $context = context_module::instance($cm->id);
    $data = array();

    // Section number if necessary.
    $strsection = '';
    if ($thquiz->section != $currentsection) {
        if ($thquiz->section) {
            $strsection = $thquiz->section;
            $strsection = get_section_name($course, $thquiz->section);
        }
        if ($currentsection !== "") {
            $table->data[] = 'hr';
        }
        $currentsection = $thquiz->section;
    }
    $data[] = $strsection;

    // Link to the instance.
    $class = '';
    if (!$thquiz->visible) {
        $class = ' class="dimmed"';
    }
    $data[] = "<a$class href=\"view.php?id=$thquiz->coursemodule\">" .
            format_string($thquiz->name, true) . '</a>';

    // Close date.
    if (($timeclosedates[$thquiz->id]->usertimeclose != 0)) {
        $data[] = userdate($timeclosedates[$thquiz->id]->usertimeclose);
    } else {
        $data[] = get_string('noclose', 'thquiz');
    }

    if ($showing == 'stats') {
        // The $thquiz objects returned by get_all_instances_in_course have the necessary $cm
        // fields set to make the following call work.
        $data[] = thquiz_attempt_summary_link_to_reports($thquiz, $cm, $context);

    } else if ($showing == 'grades') {
        // Grade and feedback.
        $attempts = thquiz_get_user_attempts($thquiz->id, $USER->id, 'all');
        list($someoptions, $alloptions) = thquiz_get_combined_reviewoptions(
                $thquiz, $attempts);

        $grade = '';
        $feedback = '';
        if ($thquiz->grade && array_key_exists($thquiz->id, $grades)) {
            if ($alloptions->marks >= question_display_options::MARK_AND_MAX) {
                $a = new stdClass();
                $a->grade = thquiz_format_grade($thquiz, $grades[$thquiz->id]);
                $a->maxgrade = thquiz_format_grade($thquiz, $thquiz->grade);
                $grade = get_string('outofshort', 'thquiz', $a);
            }
            if ($alloptions->overallfeedback) {
                $feedback = thquiz_feedback_for_grade($grades[$thquiz->id], $thquiz, $context);
            }
        }
        $data[] = $grade;
        if ($showfeedback) {
            $data[] = $feedback;
        }
    }

    $table->data[] = $data;
} // End of loop over thquiz instances.

// Display the table.
echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();

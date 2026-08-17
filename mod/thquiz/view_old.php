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
 * This page is the entry page into the thquiz UI. Displays information about the
 * thquiz to students and teachers, and lets students see their previous attempts.
 *
 * @package   mod_thquiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/mod/thquiz/locallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/format/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID, or ...
$q = optional_param('q',  0, PARAM_INT);  // Thquiz ID.

if ($id) {
    if (!$cm = get_coursemodule_from_id('thquiz', $id)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        throw new \moodle_exception('coursemisconf');
    }
} else {
    if (!$thquiz = $DB->get_record('thquiz', array('id' => $q))) {
        throw new \moodle_exception('invalidthquizid', 'thquiz');
    }
    if (!$course = $DB->get_record('course', array('id' => $thquiz->course))) {
        throw new \moodle_exception('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("thquiz", $thquiz->id, $course->id)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
}

// Check login and get context.
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/thquiz:view', $context);

// Cache some other capabilities we use several times.
$canattempt = has_capability('mod/thquiz:attempt', $context);
$canreviewmine = has_capability('mod/thquiz:reviewmyattempts', $context);
$canpreview = has_capability('mod/thquiz:preview', $context);

// Create an object to manage all the other (non-roles) access rules.
$timenow = time();
$thquizobj = thquiz::create($cm->instance, $USER->id);
$accessmanager = new thquiz_access_manager($thquizobj, $timenow,
        has_capability('mod/thquiz:ignoretimelimits', $context, null, false));
$thquiz = $thquizobj->get_thquiz();

// Trigger course_module_viewed event and completion.
thquiz_view($thquiz, $course, $cm, $context);

// Initialize $PAGE, compute blocks.
$PAGE->set_url('/mod/thquiz/view.php', array('id' => $cm->id));

// Create view object which collects all the information the renderer will need.
$viewobj = new mod_thquiz_view_object();
$viewobj->accessmanager = $accessmanager;
$viewobj->canreviewmine = $canreviewmine || $canpreview;

// Get this user's attempts.
$attempts = thquiz_get_user_attempts($thquiz->id, $USER->id, 'finished', true);
$lastfinishedattempt = end($attempts);
$unfinished = false;
$unfinishedattemptid = null;
if ($unfinishedattempt = thquiz_get_user_attempt_unfinished($thquiz->id, $USER->id)) {
    $attempts[] = $unfinishedattempt;

    // If the attempt is now overdue, deal with that - and pass isonline = false.
    // We want the student notified in this case.
    $thquizobj->create_attempt_object($unfinishedattempt)->handle_if_time_expired(time(), false);

    $unfinished = $unfinishedattempt->state == thquiz_attempt::IN_PROGRESS ||
            $unfinishedattempt->state == thquiz_attempt::OVERDUE;
    if (!$unfinished) {
        $lastfinishedattempt = $unfinishedattempt;
    }
    $unfinishedattemptid = $unfinishedattempt->id;
    $unfinishedattempt = null; // To make it clear we do not use this again.
}
$numattempts = count($attempts);

$viewobj->attempts = $attempts;
$viewobj->attemptobjs = array();
foreach ($attempts as $attempt) {
    $viewobj->attemptobjs[] = new thquiz_attempt($attempt, $thquiz, $cm, $course, false);
}

// Work out the final grade, checking whether it was overridden in the gradebook.
if (!$canpreview) {
    $mygrade = thquiz_get_best_grade($thquiz, $USER->id);
} else if ($lastfinishedattempt) {
    // Users who can preview the thquiz don't get a proper grade, so work out a
    // plausible value to display instead, so the page looks right.
    $mygrade = thquiz_rescale_grade($lastfinishedattempt->sumgrades, $thquiz, false);
} else {
    $mygrade = null;
}

$mygradeoverridden = false;
$gradebookfeedback = '';

$item = null;

$grading_info = grade_get_grades($course->id, 'mod', 'thquiz', $thquiz->id, $USER->id);
if (!empty($grading_info->items)) {
    $item = $grading_info->items[0];
    if (isset($item->grades[$USER->id])) {
        $grade = $item->grades[$USER->id];

        if ($grade->overridden) {
            $mygrade = $grade->grade + 0; // Convert to number.
            $mygradeoverridden = true;
        }
        if (!empty($grade->str_feedback)) {
            $gradebookfeedback = $grade->str_feedback;
        }
    }
}

$title = $course->shortname . ': ' . format_string($thquiz->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);
if (html_is_blank($thquiz->intro)) {
    $PAGE->activityheader->set_description('');
}
$PAGE->add_body_class('limitedwidth');
/** @var mod_thquiz_renderer $output */
$output = $PAGE->get_renderer('mod_thquiz');

// Print table with existing attempts.
if ($attempts) {
    // Work out which columns we need, taking account what data is available in each attempt.
    list($someoptions, $alloptions) = thquiz_get_combined_reviewoptions($thquiz, $attempts);

    $viewobj->attemptcolumn  = $thquiz->attempts != 1;

    $viewobj->gradecolumn    = $someoptions->marks >= question_display_options::MARK_AND_MAX &&
            thquiz_has_grades($thquiz);
    $viewobj->markcolumn     = $viewobj->gradecolumn && ($thquiz->grade != $thquiz->sumgrades);
    $viewobj->overallstats   = $lastfinishedattempt && $alloptions->marks >= question_display_options::MARK_AND_MAX;

    $viewobj->feedbackcolumn = thquiz_has_feedback($thquiz) && $alloptions->overallfeedback;
}

$viewobj->timenow = $timenow;
$viewobj->numattempts = $numattempts;
$viewobj->mygrade = $mygrade;
$viewobj->moreattempts = $unfinished ||
        !$accessmanager->is_finished($numattempts, $lastfinishedattempt);
$viewobj->mygradeoverridden = $mygradeoverridden;
$viewobj->gradebookfeedback = $gradebookfeedback;
$viewobj->lastfinishedattempt = $lastfinishedattempt;
$viewobj->canedit = has_capability('mod/thquiz:manage', $context);
$viewobj->editurl = new moodle_url('/mod/thquiz/edit.php', array('cmid' => $cm->id));
$viewobj->backtocourseurl = new moodle_url('/course/view.php', array('id' => $course->id));
$viewobj->startattempturl = $thquizobj->start_attempt_url();

if ($accessmanager->is_preflight_check_required($unfinishedattemptid)) {
    $viewobj->preflightcheckform = $accessmanager->get_preflight_check_form(
            $viewobj->startattempturl, $unfinishedattemptid);
}
$viewobj->popuprequired = $accessmanager->attempt_must_be_in_popup();
$viewobj->popupoptions = $accessmanager->get_popup_options();

// Display information about this thquiz.
$viewobj->infomessages = $viewobj->accessmanager->describe_rules();
if ($thquiz->attempts != 1) {
    $viewobj->infomessages[] = get_string('gradingmethod', 'thquiz',
            thquiz_get_grading_option_name($thquiz->grademethod));
}

// Inform user of the grade to pass if non-zero.
if ($item && grade_floats_different($item->gradepass, 0)) {
    $a = new stdClass();
    $a->grade = thquiz_format_grade($thquiz, $item->gradepass);
    $a->maxgrade = thquiz_format_grade($thquiz, $thquiz->grade);
    $viewobj->infomessages[] = get_string('gradetopassoutof', 'thquiz', $a);
}

// Determine wheter a start attempt button should be displayed.
$viewobj->thquizhasquestions = $thquizobj->has_questions();
$viewobj->preventmessages = array();
if (!$viewobj->thquizhasquestions) {
    $viewobj->buttontext = '';

} else {
    if ($unfinished) {
        if ($canpreview) {
            $viewobj->buttontext = get_string('continuepreview', 'thquiz');
        } else if ($canattempt) {
            $viewobj->buttontext = get_string('continueattemptthquiz', 'thquiz');
        }
    } else {
        if ($canpreview) {
            $viewobj->buttontext = get_string('previewthquizstart', 'thquiz');
        } else if ($canattempt) {
            $viewobj->preventmessages = $viewobj->accessmanager->prevent_new_attempt(
                    $viewobj->numattempts, $viewobj->lastfinishedattempt);
            if ($viewobj->preventmessages) {
                $viewobj->buttontext = '';
            } else if ($viewobj->numattempts == 0) {
                $viewobj->buttontext = get_string('attemptthquiz', 'thquiz');
            } else {
                $viewobj->buttontext = get_string('reattemptthquiz', 'thquiz');
            }
        }
    }

    // Users who can preview the thquiz should be able to see all messages for not being able to access the thquiz.
    if ($canpreview) {
        $viewobj->preventmessages = $viewobj->accessmanager->prevent_access();
    } else if ($viewobj->buttontext) {
        // If, so far, we think a button should be printed, so check if they will be allowed to access it.
        if (!$viewobj->moreattempts) {
            $viewobj->buttontext = '';
        } else if ($canattempt) {
            $viewobj->preventmessages = $viewobj->accessmanager->prevent_access();
            if ($viewobj->preventmessages) {
                $viewobj->buttontext = '';
            }
        }
    }
}

$viewobj->showbacktocourse = ($viewobj->buttontext === '' &&
        course_get_format($course)->has_view_page());

echo $OUTPUT->header();

if (isguestuser()) {
    // Guests can't do a thquiz, so offer them a choice of logging in or going back.
    echo $output->view_page_guest($course, $thquiz, $cm, $context, $viewobj->infomessages, $viewobj);
} else if (!isguestuser() && !($canattempt || $canpreview
          || $viewobj->canreviewmine)) {
    // If they are not enrolled in this course in a good enough role, tell them to enrol.
    echo $output->view_page_notenrolled($course, $thquiz, $cm, $context, $viewobj->infomessages, $viewobj);
} else {
    echo $output->view_page($course, $thquiz, $cm, $context, $viewobj);
}

echo $OUTPUT->footer();

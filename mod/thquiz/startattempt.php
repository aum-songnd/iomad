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
 * This script deals with starting a new attempt at a thquiz.
 *
 * Normally, it will end up redirecting to attempt.php - unless a password form is displayed.
 *
 * This code used to be at the top of attempt.php, if you are looking for CVS history.
 *
 * @package   mod_thquiz
 * @copyright 2009 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/thquiz/locallib.php');

// Get submitted parameters.
$id = required_param('cmid', PARAM_INT); // Course module id
$forcenew = optional_param('forcenew', false, PARAM_BOOL); // Used to force a new preview
$page = optional_param('page', -1, PARAM_INT); // Page to jump to in the attempt.

if (!$cm = get_coursemodule_from_id('thquiz', $id)) {
    throw new \moodle_exception('invalidcoursemodule');
}
if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
    throw new \moodle_exception("coursemisconf");
}

$thquizobj = thquiz::create($cm->instance, $USER->id);
// This script should only ever be posted to, so set page URL to the view page.
$PAGE->set_url($thquizobj->view_url());
// During thquiz attempts, the browser back/forwards buttons should force a reload.
$PAGE->set_cacheable(false);

// Check login and sesskey.
require_login($thquizobj->get_course(), false, $thquizobj->get_cm());
require_sesskey();
$PAGE->set_heading($thquizobj->get_course()->fullname);

// If no questions have been set up yet redirect to edit.php or display an error.
// if (!$thquizobj->has_questions()) {
//     if ($thquizobj->has_capability('mod/thquiz:manage')) {
//         redirect($thquizobj->edit_url());
//     } else {
//         throw new \moodle_exception('cannotstartnoquestions', 'thquiz', $thquizobj->view_url());
//     }
// }

// Create an object to manage all the other (non-roles) access rules.
$timenow = time();
$accessmanager = $thquizobj->get_access_manager($timenow);

// Validate permissions for creating a new attempt and start a new preview attempt if required.
list($currentattemptid, $attemptnumber, $lastattempt, $messages, $page) =
    thquiz_validate_new_attempt($thquizobj, $accessmanager, $forcenew, $page, true);

// Check access.
if (!$thquizobj->is_preview_user() && $messages) {
    $output = $PAGE->get_renderer('mod_thquiz');
    throw new \moodle_exception('attempterror', 'thquiz', $thquizobj->view_url(),
            $output->access_messages($messages));
}

// if ($accessmanager->is_preflight_check_required($currentattemptid)) {
//     // Need to do some checks before allowing the user to continue.
//     $mform = $accessmanager->get_preflight_check_form(
//             $thquizobj->start_attempt_url($page), $currentattemptid);

//     if ($mform->is_cancelled()) {
//         $accessmanager->back_to_view_page($PAGE->get_renderer('mod_thquiz'));

//     } else if (!$mform->get_data()) {

//         // Form not submitted successfully, re-display it and stop.
//         $PAGE->set_url($thquizobj->start_attempt_url($page));
//         $PAGE->set_title($thquizobj->get_thquiz_name());
//         $accessmanager->setup_attempt_page($PAGE);
//         $output = $PAGE->get_renderer('mod_thquiz');
//         if (empty($thquizobj->get_thquiz()->showblocks)) {
//             $PAGE->blocks->show_only_fake_blocks();
//         }

//         echo $output->start_attempt_page($thquizobj, $mform);
//         die();
//     }

//     // Pre-flight check passed.
//     $accessmanager->notify_preflight_check_passed($currentattemptid);
// }
if ($currentattemptid) {
    if ($lastattempt->state == thquiz_attempt::OVERDUE) {
        redirect($thquizobj->summary_url($lastattempt->id));
    } else {
        redirect($thquizobj->attempt_url($currentattemptid, $page));
    }
}

// $attempt = thquiz_prepare_and_start_new_attempt($thquizobj, $attemptnumber, $lastattempt);

$url = new moodle_url($CFG->wwwroot . '/mod/thquiz/view_cluster.php', array('id' => $id));

// Redirect to the attempt page.
// redirect($thquizobj->attempt_url($attempt->id, $page));

redirect($url);

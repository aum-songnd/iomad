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
 * This script deals with starting a new attempt at a quiz.
 *
 * Normally, it will end up redirecting to attempt.php - unless a password form is displayed.
 *
 * This code used to be at the top of attempt.php, if you are looking for CVS history.
 *
 * @package   mod_quiz
 * @copyright 2009 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once $CFG->dirroot.'/mod/thquiz/quizcluster/attemptlib.php';
require_once $CFG->dirroot.'/mod/thquiz/quizcluster/moodlelib.php';
require_once $CFG->dirroot.'/mod/thquiz/quizcluster/locallib.php';

// Get submitted parameters.
$id = required_param('cmid', PARAM_INT); // Course module id
$forcenew = optional_param('forcenew', false, PARAM_BOOL); // Used to force a new preview
$page = optional_param('page', -1, PARAM_INT); // Page to jump to in the attempt.
$cm_cluster_id = required_param('cm_cluster_id', PARAM_INT);

if (!$cm = get_coursemodule_from_id('quiz', $id)) {
    print_error('invalidcoursemodule');
}
if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error("coursemisconf");
}

$quizobj = quiz_cluster::create($cm->instance, $USER->id);
// This script should only ever be posted to, so set page URL to the view page.
$PAGE->set_url($quizobj->view_url());
// During quiz attempts, the browser back/forwards buttons should force a reload.
$PAGE->set_cacheable(false);

// Check login and sesskey.
require_login_cluster($quizobj->get_course(), false, $quizobj->get_cm());
require_sesskey();
$PAGE->set_heading($quizobj->get_course()->fullname);

// If no questions have been set up yet redirect to edit.php or display an error.
if (!$quizobj->has_questions()) {
    if ($quizobj->has_capability('mod/quiz:manage')) {
        redirect($quizobj->edit_url());
    } else {
        print_error('cannotstartnoquestions', 'quiz', $quizobj->view_url());
    }
}

$context = context_module::instance($cm->id);
$canpreview = has_capability('mod/quiz:preview', $context);

// Create an object to manage all the other (non-roles) access rules.
$timenow = time();
$accessmanager = $quizobj->get_access_manager($timenow);

$accessmanager_cluster = get_accessmanager_cluster($cm_cluster_id);
if($accessmanager_cluster->is_rule_seb()){
    $PAGE->set_pagelayout('secure');
    $PAGE->blocks->show_only_fake_blocks();
}

// Validate permissions for creating a new attempt and start a new preview attempt if required.
list($currentattemptid, $attemptnumber, $lastattempt, $messages, $page) =
    quiz_validate_new_attempt($quizobj, $accessmanager, $forcenew, $page, true);

//Check acessmanager hien thi popup
// Check access.
// if (!$quizobj->is_preview_user() && $messages) {
//     $output = $PAGE->get_renderer('mod_quiz');
//     print_error('attempterror', 'quiz', $quizobj->view_url(),
//             $output->access_messages($messages));
// }

// if ($accessmanager->is_preflight_check_required($currentattemptid)) {
//     // Need to do some checks before allowing the user to continue.
//     $mform = $accessmanager->get_preflight_check_form(
//             $quizobj->start_attempt_url_cluster($cm_cluster_id, $page), $currentattemptid);

//     if ($mform->is_cancelled()) {
//         $accessmanager->back_to_view_page($PAGE->get_renderer('mod_quiz'));

//     } else if (!$mform->get_data()) {

//         // Form not submitted successfully, re-display it and stop.
//         $PAGE->set_url($quizobj->start_attempt_url_cluster($cm_cluster_id, $page));
//         $PAGE->set_title($quizobj->get_quiz_name());
//         $accessmanager->setup_attempt_page($PAGE);
//         $output = $PAGE->get_renderer('mod_quiz');
//         if (empty($quizobj->get_quiz()->showblocks)) {
//             $PAGE->blocks->show_only_fake_blocks();
//         }

//         echo $output->start_attempt_page($quizobj, $mform);
//         die();
//     }

//     // Pre-flight check passed.
//     $accessmanager->notify_preflight_check_passed($currentattemptid);
// }

if(!$canpreview){
    $record = $DB->get_record('thquiz_attempt', ['thquiz_id' => $cm_cluster_id, 'userid' => $USER->id]);
    if($record) {

        if ($record->state == 'inprogress' && $currentattemptid != $record->current_quizattempt_id){
            $current_quizattempt_obj = quiz_cluster_create_attempt_handling_errors($record->current_quizattempt_id);
            $current_quizname = $current_quizattempt_obj->get_quiz_name();

            echo $OUTPUT->header();
            echo $OUTPUT->confirm("Bạn đang làm bài thi <b>$current_quizname</b>,Bạn cần phải nộp bài thi này trước khi chuyển sang bài thi kế tiếp", new single_button($current_quizattempt_obj->summary_url_cluster($cm_cluster_id), "Nộp bài", "get"), new single_button($current_quizattempt_obj->attempt_cluster_url($cm_cluster_id), "Tiếp tục làm", "get"));
            echo $OUTPUT->footer();
        } else {

            if ($currentattemptid) {
                if ($lastattempt->state == quiz_cluster_attempt::OVERDUE) {
                    redirect($quizobj->summary_url_cluster($lastattempt->id, $cm_cluster_id));
                } else {
                    redirect($quizobj->attempt_url_cluster($currentattemptid, $cm_cluster_id, $page));
                }
            }

            $attempt = quiz_prepare_and_start_new_attempt($quizobj, $attemptnumber, $lastattempt);
            
            $record->current_quizattempt_id = $attempt->id;
            $record->state = "inprogress";
            $DB->update_record('thquiz_attempt', $record);

            // Redirect to the attempt page.
            redirect($quizobj->attempt_url_cluster($attempt->id, $cm_cluster_id, $page));
        }

    } else {

        if ($currentattemptid) {
            if ($lastattempt->state == quiz_cluster_attempt::OVERDUE) {
                redirect($quizobj->summary_url_cluster($lastattempt->id, $cm_cluster_id));
            } else {
                redirect($quizobj->attempt_url_cluster($currentattemptid, $cm_cluster_id, $page));
            }
        }

        $attempt = quiz_prepare_and_start_new_attempt($quizobj, $attemptnumber, $lastattempt);


        $record = new stdClass();
        $record->thquiz_id = $cm_cluster_id;
        $record->userid = $USER->id;
        $record->current_quizattempt_id = $attempt->id;
        $record->state = "inprogress";

        $DB->insert_record('thquiz_attempt', $record);

        // Redirect to the attempt page.
        redirect($quizobj->attempt_url_cluster($attempt->id, $cm_cluster_id, $page));
    }
} else {

    if ($currentattemptid) {
        if ($lastattempt->state == quiz_cluster_attempt::OVERDUE) {
            redirect($quizobj->summary_url_cluster($lastattempt->id, $cm_cluster_id));
        } else {
            redirect($quizobj->attempt_url_cluster($currentattemptid, $cm_cluster_id, $page));
        }
    }

    $attempt = quiz_prepare_and_start_new_attempt($quizobj, $attemptnumber, $lastattempt);
    // Redirect to the attempt page.
    redirect($quizobj->attempt_url_cluster($attempt->id, $cm_cluster_id, $page));
}





<?php

require_once __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/mod/quiz/locallib.php';
require_once $CFG->dirroot.'/mod/thquiz/quizcluster/locallib.php';
require_once $CFG->dirroot.'/mod/thquiz/quizcluster/moodlelib.php';
global $DB, $COURSE, $PAGE;

// Look for old-style URLs, such as may be in the logs, and redirect them to startattemtp.php.
if ($id = optional_param('id', 0, PARAM_INT)) {
	redirect($CFG->wwwroot . '/mod/thquiz/quizcluster/startattempt.php?cmid=' . $id . '&sesskey=' . sesskey());
} else if ($qid = optional_param('q', 0, PARAM_INT)) {
	if (!$cm = get_coursemodule_from_instance('quiz', $qid)) {
		print_error('invalidquizid', 'quiz');
	}
	redirect(new moodle_url('/mod/thquiz/quizcluster/startattempt.php',
		array('cmid' => $cm->id, 'sesskey' => sesskey())));
}

// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$cmid = optional_param('cmid', null, PARAM_INT);
$cm_cluster_id = required_param('cm_cluster_id', PARAM_INT);

$attemptobj = quiz_cluster_create_attempt_handling_errors($attemptid, $cmid);

$page = $attemptobj->force_page_number_into_range($page);

$PAGE->set_url($attemptobj->attempt_cluster_url($cm_cluster_id,null, $page));
// During quiz attempts, the browser back/forwards buttons should force a reload.
$PAGE->set_cacheable(false);

// Check login.
require_login_cluster($attemptobj->get_course(), false, $attemptobj->get_cm());

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
	if ($attemptobj->has_capability('mod/quiz:viewreports')) {
		redirect($attemptobj->review_url(null, $page));
	} else {
		throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'notyourattempt');
	}
}

// Check capabilities and block settings.
if (!$attemptobj->is_preview_user()) {
	$attemptobj->require_capability('mod/quiz:attempt');
	if (empty($attemptobj->get_quiz()->showblocks)) {
		$PAGE->blocks->show_only_fake_blocks();
	}

} else {
	navigation_node::override_active_url($attemptobj->start_attempt_url());
}

// If the attempt is already closed, send them to the review page.
if ($attemptobj->is_finished()) {
	redirect($attemptobj->review_cluster_url($cm_cluster_id, null, $page));
} else if ($attemptobj->get_state() == quiz_attempt::OVERDUE) {
	redirect($attemptobj->summary_url_cluster($cm_cluster_id));
}

// Check the access rules.
$accessmanager = $attemptobj->get_access_manager(time());

$accessmanager->setup_attempt_page($PAGE);
$output = $PAGE->get_renderer('mod_thquiz');

$messages = $accessmanager->prevent_access();

if (!$attemptobj->is_preview_user() && $messages) {
	print_error('attempterror', 'quiz', $attemptobj->view_url(),
		$output->access_messages($messages));
}
if ($accessmanager->is_preflight_check_required($attemptobj->get_attemptid())) {
	redirect($attemptobj->start_attempt_url(null, $page));
}

// Set up auto-save if required.
$autosaveperiod = get_config('quiz', 'autosaveperiod');
if ($autosaveperiod) {
	$PAGE->requires->yui_module('moodle-mod_quiz-autosave',
		'M.mod_quiz.autosave.init', array($autosaveperiod));
}

// Log this page view.
$attemptobj->fire_attempt_viewed_event();

// Get the list of questions needed by this page.
$slots = $attemptobj->get_slots($page);
// Check.
if (empty($slots)) {
	throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'noquestionsfound');
}

// Update attempt page, redirecting the user if $page is not valid.
if (!$attemptobj->set_currentpage($page)) {
	redirect($attemptobj->start_attempt_url(null, $attemptobj->get_currentpage()));
}

// Initialise the JavaScript.
$headtags = $attemptobj->get_html_head_contributions($page);

$PAGE->requires->js_init_call('M.mod_quiz.init_attempt_form', null, false, quiz_get_js_module());
\core\session\manager::keepalive(); // Try to prevent sessions expiring during quiz attempts.

// Arrange for the navigation to be displayed in the first region on the page.
$navbc = $attemptobj->get_navigation_panel_cluster($output, $cm_cluster_id, 'thquiz_attempt_nav_panel', $page);
// $regions = $PAGE->blocks->get_regions();
// $PAGE->blocks->add_fake_block($navbc, reset($regions));

$headtags = $attemptobj->get_html_head_contributions($page);
$PAGE->set_title($attemptobj->attempt_page_title($page));
$PAGE->set_heading($attemptobj->get_course()->fullname);

$accessmanager = get_accessmanager_cluster($cm_cluster_id);
if($accessmanager->is_rule_seb()){
    $PAGE->set_pagelayout('secure');
    $PAGE->blocks->show_only_fake_blocks();
}

if ($attemptobj->is_last_page($page)) {
	$nextpage = -1;
} else {
	$nextpage = $page + 1;
}

echo $output->attempt_cluster_page($attemptobj, $page, $accessmanager, $messages, $slots, $id, $nextpage, $cm_cluster_id);
echo '<style>
		.activity-navigation {
			display: none
		}
	</style>';

?>


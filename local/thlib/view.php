<?php

require_once '../../config.php';
require_once 'lib.php';
require_once 'externallib.php';

global $DB, $OUTPUT, $PAGE, $COURSE;

$courseid = $COURSE->id;
if (!$course = $DB->get_record('course', array('id' => $COURSE->id))) {
	print_error('invalidcourse', 'local_th_tnuapi', $courseid);
}

require_login($COURSE->id);

$pageurl = "/locals/thlib/view.php?id=$courseid";
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('activepagetitle', 'local_th_tnuapi'));
$PAGE->set_title($SITE->fullname . ': ' . get_string('activepagetitle', 'local_th_tnuapi'));

$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');

echo $OUTPUT->header();

// $result = local_thlib_external::loadcourses2(['AUM0221HN']);
// print_object("result");
// print_object($result);

$result = local_thlib_external::loadcourses(['AUM0221HN']);

echo $OUTPUT->footer();

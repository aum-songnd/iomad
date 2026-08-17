<?php

require_once '../../../config.php';
require_once 'lib.php';

global $DB, $OUTPUT, $PAGE, $COURSE;

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$returnurl = optional_param('returnurl', '', PARAM_URL);

// Check for all required variables.
if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourse');
}

require_login($id);
require_capability('qtype/thvstepcluster:delete_tags', context_system::instance());
if (empty($returnurl)) {
    $returnurl = new moodle_url('/course/view.php', array('id' => $id));
}

$PAGE->set_url(new moodle_url('/question/type/thvstepcluster/delete_tags.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('course');

if ($confirm and confirm_sesskey()) {
    $check = qtype_thvstepcluster_delete_tags($id);

    if ($check) {
        redirect($returnurl, get_string('success'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($returnurl, get_string('warning'), null, \core\output\notification::NOTIFY_WARNING);
    }
}
// $strheading = $COURSE->fullname;
$PAGE->navbar->add(get_string('pluginname', 'qtype_thvstepcluster'));
$PAGE->set_title(get_string('pluginname', 'qtype_thvstepcluster'));
$PAGE->set_heading($COURSE->fullname);
$params = array(
    'id' => $id, 
    'confirm' => 1, 
    'sesskey' => sesskey(), 
    'returnurl' => $returnurl
);
$yesurl = new moodle_url('/question/type/thvstepcluster/delete_tags.php', $params);
$message = get_string('delete_tag', 'qtype_thvstepcluster');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'qtype_thvstepcluster'));
echo $OUTPUT->confirm($message, $yesurl, $returnurl);
echo $OUTPUT->footer();

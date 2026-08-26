<?php

require_once '../../config.php';

global $DB, $OUTPUT, $PAGE, $COURSE, $USER;

// Check for all required variables.
$courseid = $COURSE->id;
$returnto = optional_param('returnto', 'course', PARAM_ALPHANUM); // Generic navigation return page switch.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
	print_error('invalidcourse', 'block_th_scoring_collaborators', $courseid);
}

require_login($courseid);
require_capability('block/th_scoring_collaborators:view', context_course::instance($COURSE->id));

$pageurl = '/blocks/th_scoring_collaborators/edit.php';
$title = get_string('title', 'block_th_scoring_collaborators');
$PAGE->set_url('/blocks/th_scoring_collaborators/edit.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('th_scoring_collaborators', 'block_th_scoring_collaborators'));
$PAGE->set_title($SITE->fullname . ': ' . get_string('title', 'block_th_scoring_collaborators'));

$id = optional_param('id', -1, PARAM_INT);
if($id) {
    $attempt = $DB->get_record_sql("SELECT * FROM {block_th_assign_grading} AS ag WHERE ag.id = $id");
    if($attempt) {
        $newData = array(
            'id' => $attempt->id,
            'status' => 1,
            'grading_date' => time()
        );    
        $DB->update_record('block_th_assign_grading', (object)$newData);
        redirect($CFG->wwwroot . '/blocks/th_scoring_collaborators/view.php', get_string('success', 'block_th_scoring_collaborators'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->footer();

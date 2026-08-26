<?php

require_once '../../config.php';

global $DB, $OUTPUT, $PAGE, $COURSE, $USER;

// Check for all required variables.
$courseid = $COURSE->id;
$returnto = optional_param('returnto', 'course', PARAM_ALPHANUM); // Generic navigation return page switch.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
	print_error('invalidcourse', 'block_th_assign_grading', $courseid);
}

require_login($courseid);

$pageurl = '/blocks/th_assign_grading/action.php';
$title = get_string('title', 'block_th_assign_grading');
$PAGE->set_url('/blocks/th_assign_grading/action.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('th_assign_grading', 'block_th_assign_grading'));
$PAGE->set_title($SITE->fullname . ': ' . get_string('title', 'block_th_assign_grading'));

$confirm = optional_param('confirm', -1, PARAM_INT);
$delete_attempt = optional_param('delete_attempt', -1, PARAM_INT);
$delete_assign_turns = optional_param('delete_assign_turns', -1, PARAM_INT);

if ($confirm != -1 && !empty($confirm)) {
    $data = $DB->get_record('block_th_assign_grading', array('id' => $confirm), '*', MUST_EXIST);

    $data->confirm_grading = 1;
    $data->userid_confirm = $USER->id;
    $data->confirm_date = time();
  
    $update_success = $DB->update_record('block_th_assign_grading', $data);
    if ($update_success) {
        // Event
        \block_th_assign_grading\event\approval_completed::create([
            'objectid' => $data->id,
            'context' => context_system::instance(), // Hoặc context_course::instance nếu biết course
            'userid' => $USER->id,
            'other' => [
                'approved_count' => 1,
                'approved_ids' => [$data->id],
                'mode' => 'single'
            ]
        ])->trigger();
        redirect($CFG->wwwroot . '/blocks/th_assign_grading/view.php', 'Phê duyệt thành công', null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($CFG->wwwroot . '/blocks/th_assign_grading/view.php', 'Phê duyệt thất bại', null, \core\output\notification::NOTIFY_ERROR);
    }
} else if ($delete_attempt != -1 && !empty($delete_attempt)) {
    $delete_success = $DB->delete_records('block_th_assign_grading', array('id' => $delete_attempt));
    if ($delete_success) {
        redirect($CFG->wwwroot . '/blocks/th_assign_grading/view.php', 'Xóa thành công', null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($CFG->wwwroot . '/blocks/th_assign_grading/view.php', 'Xóa thất bại', null, \core\output\notification::NOTIFY_ERROR);
    }
}else if ($delete_assign_turns != -1 && !empty($delete_assign_turns)) {
    $delete_success = $DB->delete_records('th_assign_turns', array('id' => $delete_assign_turns));
    if ($delete_success) {
        redirect($CFG->wwwroot . '/blocks/th_assign_grading/index.php', 'Xóa thành công', null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($CFG->wwwroot . '/blocks/th_assign_grading/index.php', 'Xóa thất bại', null, \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->footer();

?>
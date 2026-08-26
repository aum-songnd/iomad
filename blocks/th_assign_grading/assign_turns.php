<?php

require_once '../../config.php';
require_once $CFG->dirroot . '/blocks/th_assign_grading/classes/lib.php';
require_once $CFG->dirroot . '/blocks/th_assign_grading/th_assign_turns_form.php';

global $DB, $OUTPUT, $PAGE, $COURSE, $USER;

// Kiểm tra tất cả các biến cần thiết.
$courseid = $COURSE->id;
$returnto = optional_param('returnto', 'course', PARAM_ALPHANUM); // Switch trang web chuyển hướng chung.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourse', 'block_th_assign_grading', $courseid);
}

require_login($courseid);
require_capability('block/th_assign_grading:view', context_course::instance($COURSE->id));

$pageurl = '/blocks/th_assign_grading/assign_turns.php';
$title = get_string('title', 'block_th_assign_grading');
$PAGE->set_url('/blocks/th_assign_grading/assign_turns.php');
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('th_assign_grading', 'block_th_assign_grading'));
$PAGE->set_title($SITE->fullname . ': ' . get_string('title', 'block_th_assign_grading'));

if ($returnurl) {
    $returnurl = new moodle_url($returnurl);
} else {
    $returnurl = new moodle_url('/blocks/th_assign_grading/view.php');
}
$companyid = 0;
if (class_exists('iomad')) {

	// Set the companyid
	$companyid = iomad::get_my_companyid($context);
	// Kiểm tra cuối cùng
	if (empty($companyid)) {
	    print_error('nocompany', 'block_th_assign_grading');
	}
}

// Truyền companyid vào form
$editform = new th_assign_turns_form(null, ['companyid' => $companyid]);

$insert_success = false;
$update_success = false;

if ($editform->is_cancelled()) {
    redirect($returnurl);
}elseif ($data = $editform->get_data()) {
    // KIỂM TRA USER CÓ THUỘC COMPANY KHÔNG
    $user_in_company = $DB->record_exists('company_users', [
        'userid' => $data->userid,
        'companyid' => $companyid
    ]);

    if (!$user_in_company) {
        redirect(
            $CFG->wwwroot . '/blocks/th_assign_grading/index.php',
            get_string('usernotincompany', 'block_th_assign_grading'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    // Kiểm tra xem user đã có record chưa
    $attemp_data = $DB->get_record_sql(
        "SELECT * FROM {th_assign_turns} WHERE userid = :userid",
        ['userid' => $data->userid]
    );
    
    if (empty($attemp_data)) {
        $newData = array(
            'userid' => $data->userid,
            'numberturns' => $data->count,
            'unused' => $data->count,
            'assign_date' => time()
        );
        $insert_success = $DB->insert_record('th_assign_turns', (object)$newData);
    } else {
        $newData = array(
            'id' => $attemp_data->id,
            'userid' => $data->userid,
            'numberturns' => $attemp_data->numberturns + $data->count,
            'unused' => $attemp_data->unused + $data->count,
            'assign_date' => time()
        );
        $update_success = $DB->update_record('th_assign_turns', (object)$newData);
    }

    if ($insert_success || $update_success) {
        redirect($CFG->wwwroot . '/blocks/th_assign_grading/index.php', 'success', null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($CFG->wwwroot . '/blocks/th_assign_grading/index.php', 'failed', null, \core\output\notification::NOTIFY_ERROR);
    }
    echo $OUTPUT->header();
    echo $editform->display();
    echo $OUTPUT->footer();
} else {
    echo $OUTPUT->header();
    echo "<center><h4>Quản lý phân chia công việc cho CTV</h4></center>";
    $baseurl = new moodle_url('/blocks/th_assign_grading/assign_turns.php');

    if ($editcontrols = block_th_assign_grading_controls($context, $baseurl)) {
        echo $OUTPUT->render($editcontrols);
    }
    echo $editform->display();
    echo $OUTPUT->footer();
}

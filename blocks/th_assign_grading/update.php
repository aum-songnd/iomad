<?php

require_once '../../config.php';
require_once $CFG->dirroot . '/blocks/th_assign_grading/lib.php';
require_once $CFG->dirroot . '/blocks/th_assign_grading/th_update_assign_turns_form.php';


global $DB, $OUTPUT, $PAGE, $COURSE, $USER;

// Check for all required variables.
$courseid = $COURSE->id;
$returnto = optional_param('returnto', 'course', PARAM_ALPHANUM); // Generic navigation return page switch.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourse', 'block_th_assign_grading', $courseid);
}

require_login($courseid);
require_capability('block/th_assign_grading:approve_grading', context_course::instance($COURSE->id));
$pageurl = '/blocks/th_assign_grading/update.php';
$title = get_string('title', 'block_th_assign_grading');
$PAGE->set_url('/blocks/th_assign_grading/update.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('th_assign_grading', 'block_th_assign_grading'));
$PAGE->set_title($SITE->fullname . ': ' . get_string('title', 'block_th_assign_grading'));

$update_assign_turns = optional_param('update_assign_turns', -1, PARAM_INT);

if ($update_assign_turns != -1 && !empty($update_assign_turns)) {
    $assign_turns = $DB->get_record_sql("SELECT * FROM {th_assign_turns} WHERE id = ?", array($update_assign_turns));

    if (!$assign_turns) {
        $assign_turns = new stdClass();
        $assign_turns->numberturns = '';
    }
} else {
    $assign_turns = new stdClass();
    $assign_turns->numberturns = '';
}


$th_update_assign_turns_form = new th_update_assign_turns_form();

$th_update_assign_turns_form->set_data(array(
    'assign_turns' => $assign_turns->numberturns,
    'id' => $update_assign_turns
));

if ($th_update_assign_turns_form->is_cancelled()) {
    $returnurl = new moodle_url('/blocks/th_assign_grading/index.php');
    redirect($returnurl);
} else if ($data = $th_update_assign_turns_form->get_data()) {
    $id = $data->id;
    $assign_turns = $DB->get_record_sql("SELECT * FROM {th_assign_turns} WHERE id = ?", array($id));

    if (!empty($assign_turns)) {
        $newData = array(
            'id' => $assign_turns->id,
            'numberturns' => $data->assign_turns,
            'unused' => $data->assign_turns,
            'assign_date' => time()
        );
        $DB->update_record('th_assign_turns', (object)$newData);
    }

    $returnurl = new moodle_url('/blocks/th_assign_grading/index.php');
    redirect($returnurl);
} else {
    echo $OUTPUT->header();
    echo "<center style = 'margin-bottom: 50px;'><h4>Chỉnh sửa lượt chấm</h4></center>";
    echo $th_update_assign_turns_form->display();
    echo $OUTPUT->footer();
}

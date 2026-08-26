<?php

require_once '../../config.php';
require_once $CFG->dirroot . '/blocks/th_assign_grading/classes/lib.php';
require_once $CFG->dirroot . '/blocks/th_assign_grading/th_grading_progress_form.php';

global $DB, $OUTPUT, $PAGE, $COURSE, $USER;

$courseid = $COURSE->id;
$returnto = optional_param('returnto', 'course', PARAM_ALPHANUM);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    print_error('invalidcourse', 'block_th_assign_grading', $courseid);
}

require_login($courseid);
require_capability('block/th_assign_grading:view', context_course::instance($COURSE->id));
$context = context_system::instance();
$PAGE->set_url('/blocks/th_assign_grading/grading_progress.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('th_assign_grading', 'block_th_assign_grading'));
$PAGE->set_title($SITE->fullname . ': ' . get_string('title', 'block_th_assign_grading'));

$returnurl = $returnurl ? new moodle_url($returnurl) : new moodle_url('/blocks/th_assign_grading/view.php');
$companyid = 0;
if (class_exists('iomad')) {
    // Set the companyid
    $companyid = iomad::get_my_companyid($context);
    // Kiểm tra cuối cùng
    if (empty($companyid)) {
        print_error('nocompany', 'block_th_assign_grading');
    }
}

$th_grading_progress_form = new th_grading_progress_form(null, ['companyid' => $companyid]);

if ($th_grading_progress_form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $th_grading_progress_form->get_data()) {
    $data->to_date = strtotime('+1 day', $data->to_date);
    
    $params = [
        'from_date' => $data->from_date, 
        'to_date' => $data->to_date, 
        'companyid' => $data->companyid
    ];
    $status_sql = '';
    $userid_sql = '';
    
    if (!empty($data->userid)) {
        list($in_sql, $in_params) = $DB->get_in_or_equal($data->userid, SQL_PARAMS_NAMED, 'uid_');
        $params = array_merge($in_params, $params);
        $userid_sql = "bag.ctvid $in_sql AND ";
    }

    if ($data->status == 1) {
        $status_sql = "AND bag.status = 1 ";
    } else if ($data->status != 0) {
        $status_sql = "AND bag.status IS NULL ";
    }
	
    $list_data = $DB->get_records_sql(
        "SELECT bag.* FROM {block_th_assign_grading} bag
        WHERE $userid_sql bag.assignment_time BETWEEN :from_date AND :to_date $status_sql
        ORDER BY bag.assignment_time DESC",
        $params
    );

    $table = new html_table();
    $table->head = ['STT', 'Tên CTV', 'Email', 'Tên khóa', 'Tên bài luyện tập', 'Ngày giao bài', 'Trạng thái chấm', 'Ngày hoàn thành', 'Độ lệch', 'Tiến độ'];
    $table->attributes = ['class' => 'table', 'border' => '1'];

    $stt = 0;
    foreach ($list_data as $data) {
        $ctv = $DB->get_record('user', ['id' => $data->ctvid]);
        $quiz_attempt = $DB->get_record_sql(
            "SELECT qa.id, qa.attempt, qa.timefinish, u.firstname, u.lastname, q.name, c.fullname
             FROM {quiz_attempts} qa
             JOIN {user} u ON qa.userid = u.id
             JOIN {company_users} cu ON cu.userid = qa.userid
             JOIN {quiz} q ON qa.quiz = q.id
             JOIN {course} c ON c.id = q.course
             WHERE qa.id = ? AND qa.preview = 0 AND cu.companyid = ?",
            [$data->attemptid, $companyid]
        );
        if (empty($quiz_attempt)) {
            continue;
        }

        $status = $data->status ? '<span style="color:red">Đã chấm</span>' : 'Chưa chấm';

        if (!empty($data->grading_date) && !empty($data->assignment_time)) {
            $diffInSeconds = $data->grading_date - $data->assignment_time;
            $diffInHours = floor($diffInSeconds / 3600);
            $remainingMinutes = floor(($diffInSeconds % 3600) / 60);

            if ($diffInHours < 1) {
                $time = "$remainingMinutes phút";
                $progress = 'Nhanh';
            } else if ($diffInHours < 24) {
                $time = "$diffInHours giờ $remainingMinutes phút";
                $progress = 'Nhanh';
            } else {
                $diffInDays = floor($diffInHours / 24);
                $remainingHours = $diffInHours % 24;
                $time = "$diffInDays ngày, $remainingHours giờ $remainingMinutes phút";
                $progress = $diffInHours == 24 ? 'Vừa' : 'Chậm';
            }
        } else {
            $time = 'Chưa chấm';
            $progress = 'Chưa chấm';
        }

        $table->data[] = [
            ++$stt,
            $ctv->firstname . ' ' . $ctv->lastname,
            $ctv->email,
            $quiz_attempt->fullname,
            $quiz_attempt->name,
            date('d/m/Y H:i:s', $data->assignment_time),
            $status,
            !empty($data->grading_date) ? date('d/m/Y H:i:s', $data->grading_date) : 'Chưa chấm',
            $time,
            $progress
        ];
    }

    $html = html_writer::table($table);
    $lang = current_language();
    $PAGE->requires->js_call_amd('local_thlib/main', 'init', ['.table', "Báo cáo tiến độ chấm bài của CTV", $lang]);

    echo $OUTPUT->header();
    echo "<center><h4>Báo cáo tiến độ chấm bài của CTV</h4></center>";
    if ($editcontrols = block_th_assign_grading_controls(context_system::instance(), new moodle_url('/blocks/th_assign_grading/grading_progress.php'))) {
        echo $OUTPUT->render($editcontrols);
    }
    echo $th_grading_progress_form->display();
    echo $html;
    echo $OUTPUT->footer();
} else {
    echo $OUTPUT->header();
    echo "<center><h4>Báo cáo tiến độ chấm bài của CTV</h4></center>";
    if ($editcontrols = block_th_assign_grading_controls(context_system::instance(), new moodle_url('/blocks/th_assign_grading/grading_progress.php'))) {
        echo $OUTPUT->render($editcontrols);
    }
    echo $th_grading_progress_form->display();
    echo $OUTPUT->footer();
}

<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/thquiz/locallib.php');

global $DB, $PAGE, $OUTPUT;
$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id('thquiz', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$thquiz = $DB->get_record('thquiz', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/thquiz:viewtracking', $context);

$PAGE->set_url('/mod/thquiz/tracking.php', array('cmid' => $cmid));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Quản lý làm bài');
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo '
<style>
    .badge-success { background-color: #28a745; color: white; padding: 5px 10px; border-radius: 4px; display: inline-block; }
    .badge-danger  { background-color: #dc3545; color: white; padding: 5px 10px; border-radius: 4px; display: inline-block; }
</style>
';

echo html_writer::tag('h2', 'Quản lý làm bài: ' . format_string($thquiz->name), 
    array('style' => 'text-align: center; margin-bottom: 20px; font-weight: bold; color: #333;'));

echo '<hr style="margin: 30px 0;">';

// $sql = "SELECT ta.*, u.username 
//         FROM {thquiz_attempt} ta
//         JOIN {user} u ON ta.userid = u.id
//         WHERE ta.thquiz_id = :thquizid
//         ORDER BY ta.id DESC";

// $attempts = $DB->get_records_sql($sql, ['thquizid' => $cmid]);

// if ($attempts) {
//     $table = new html_table();
    
//     $table->attributes = [
//         'class'  => 'generaltable tracking_table',
//         'border' => '1',
//         'style'  => 'width:100%; text-align:center;'
//     ];

//     $table->head = array('id', 'username', 'userid', 'thquiz_id', 'current_quizattempt_id', 'state'); 
//     $table->align = array('center', 'center', 'center', 'center', 'center', 'center');

//     foreach ($attempts as $attempt) {
//         if ($attempt->state == 'finished') {
//             $status = '<span class="badge badge-success">' . $attempt->state . '</span>';
//         } else {
//             $status = '<span class="badge badge-danger">' . $attempt->state . '</span>';
//         }

//         $table->data[] = array(
//             $attempt->id,
//             $attempt->username,
//             $attempt->userid,
//             $attempt->thquiz_id,
//             $attempt->current_quizattempt_id,
//             $status
//         );
//     }
    
//     echo html_writer::table($table);

// } else {
//     echo $OUTPUT->notification('No data found.', 'warning');
// }

// SQL lấy thông tin từ bảng thquiz_attempt, liên kết với bảng user
// SQL lấy thông tin attempt, user và tên của thquiz
// SQL lấy thông tin từ attempt, user, liên kết qua course_modules để lấy tên thquiz
// SQL lấy: 
// 1. Thông tin user
// 2. Tên module thquiz (từ thquiz_id là CMID)
// 3. Tên bài Quiz gốc (từ current_quizattempt_id)
$sql = "SELECT ta.*, 
               u.firstname, u.lastname, u.email, u.username, 
               q_th.name as thquizname,
               q_orig.name as original_quizname
        FROM {thquiz_attempt} ta
        JOIN {user} u ON ta.userid = u.id
        -- Lấy thông tin thquiz từ CMID
        JOIN {course_modules} cm ON ta.thquiz_id = cm.id
        JOIN {thquiz} q_th ON cm.instance = q_th.id
        -- Lấy thông tin Quiz gốc từ current_quizattempt_id (Left join để tránh mất dữ liệu nếu chưa có attempt)
        LEFT JOIN {quiz_attempts} qa ON ta.current_quizattempt_id = qa.id
        LEFT JOIN {quiz} q_orig ON qa.quiz = q_orig.id
        WHERE ta.thquiz_id = :cmid
        ORDER BY ta.id DESC";

$attempts = $DB->get_records_sql($sql, ['cmid' => $cmid]);

if ($attempts) {
    $table = new html_table();

    $table->attributes = [
        'class'  => 'generaltable tracking_table',
        'border' => '1',
        'style'  => 'width:100%; text-align:center;'
    ];

    // 1. Cấu trúc tiêu đề: Thêm get_string cho username
    $table->head = array(
        get_string('id', 'mod_thquiz'),
        get_string('originalquiz', 'mod_thquiz'),
        get_string('th_module', 'mod_thquiz'),
        get_string('fullname'), 
        get_string('username'), // Thêm cột Username ở đây
        get_string('attemptid', 'mod_thquiz'), 
        get_string('status', 'core')
    );
    
    // Cập nhật lại căn lề (thêm 1 cột 'left' cho username)
    $table->align = array('center', 'left', 'left', 'left', 'left', 'center', 'center');

    foreach ($attempts as $attempt) {
        // Sử dụng hàm fullname() chuẩn của Moodle (đã lấy đủ fields từ SQL ở bước trước)
        $userfullname = $attempt->firstname . ' ' . $attempt->lastname;

        // Định dạng trạng thái
        $state = strtolower($attempt->state);
        if ($state == 'finished') {
            $status = html_writer::span(get_string('finished', 'quiz'), 'badge badge-success');
        } else {
            // Sử dụng get_string('unknown', 'mod_thquiz') nếu bạn đã thêm vào file lang
            $status = html_writer::span(ucfirst($state ?: 'unknown'), 'badge badge-secondary');
        }

        // Tên Quiz gốc
        $originalquiz = $attempt->original_quizname ? format_string($attempt->original_quizname) : html_writer::tag('i', 'Not started', ['class' => 'text-muted']);

        // 2. Thêm dữ liệu $attempt->username vào mảng data
        $table->data[] = array(
            $attempt->id,
            $originalquiz,
            format_string($attempt->thquizname),
            $userfullname,
            $attempt->username, // Dữ liệu Username
            $attempt->current_quizattempt_id ?: '-',
            $status
        );
    }
    
    echo html_writer::table($table);

} else {
    echo $OUTPUT->notification('No data found.', 'info');
}

$lang = current_language();
echo '<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css">';
$PAGE->requires->js_call_amd('local_thlib/main', 'init', array('.tracking_table', 'Quan_ly_lam_bai', $lang));

echo $OUTPUT->footer();
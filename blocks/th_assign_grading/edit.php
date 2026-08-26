<?php

require_once '../../config.php';
require_once $CFG->dirroot . '/blocks/th_assign_grading/classes/lib.php';

global $DB, $OUTPUT, $PAGE, $COURSE, $USER;

// Kiểm tra tất cả các biến cần thiết.
$courseid = $COURSE->id;
$returnto = optional_param('returnto', 'course', PARAM_ALPHANUM);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourse', 'block_th_assign_grading', $courseid);
}

require_login($courseid);
require_capability('block/th_assign_grading:view', context_course::instance($COURSE->id));
$config = get_config('block_th_assign_grading');
$th_crm_code = get_config('block_th_assign_grading', 'th_crm_code');

$pageurl = '/blocks/th_assign_grading/edit.php';
$title = get_string('title', 'block_th_assign_grading');
$PAGE->set_url('/blocks/th_assign_grading/edit.php');
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
$data_quiz = $DB->get_records_sql("SELECT DISTINCT qa.id,qa.attempt,q.name as quiz,c.fullname as course, c.id as courseid, c.idnumber as courseIDnumber,u.firstname,u.lastname,c.shortname,
  uid.data AS fieldvalue, qa.timefinish as request_time,  uif.shortname AS fieldname
FROM {quiz_attempts} as qa 
JOIN {quiz} as q ON qa.quiz = q.id 
JOIN {course} as c ON q.course = c.id 
JOIN {course_modules} as cm ON q.id = cm.instance
JOIN {user} AS u ON qa.userid = u.id 
LEFT JOIN {company_course} cc ON cc.courseid = c.id
JOIN {company_users} cu ON cu.userid = qa.userid
LEFT JOIN {company_shared_courses} csc ON csc.courseid = c.id

LEFT JOIN 
  {block_th_scoring_request} sr ON sr.userid = qa.userid AND sr.quizid = qa.quiz AND sr.attemptid = qa.attempt

LEFT JOIN 
{user_info_field} uif ON uif.shortname = :th_crm_code
LEFT JOIN {user_info_data} uid ON uid.userid = u.id AND uid.fieldid = uif.id
WHERE c.visible = 1 
AND qa.state = 'finished' 
AND qa.preview = 0 AND qa.id 
NOT IN (SELECT attemptid FROM {block_th_assign_grading})
AND (cm.idnumber LIKE '%rec%' OR cm.idnumber LIKE '%ess%')
AND (cc.companyid = :companyid1 OR csc.companyid = :companyid2)
AND cu.companyid = :companyid3",
['companyid1' => $companyid, 'companyid2' => $companyid, 'companyid3' => $companyid, 'th_crm_code' => $th_crm_code]
);

$table = new html_table();
$table->head = array(
    '<input type="checkbox" id="select-all" onclick="selectAllRows(this)">',
    'STT',
    'Tên khóa học',
    'Mã khóa',
    'Tên bài kiểm tra',
    'Họ và tên',
    'Mã AUM',
    'Ngày yêu cầu'
);

if ($data_quiz) {
    $items = [];
    foreach ($data_quiz as $data) {
        $fields = \core_course\customfield\course_handler::create()->get_instance_data($data->courseid, true);
        $metadata = [];
        foreach ($fields as $field) {
            if (empty($field->get_value())) {
                continue;
            }
            $cat = $field->get_field()->get_category()->get('name');
            $metadata[$field->get_field()->get('shortname')] = $field->get_value();
        }
        if (isset($metadata['chamchua'])) {
            $get_attempt = $DB->get_record_sql("SELECT attemptid FROM {block_th_scoring_request} WHERE atempt_review = $data->id");
            if ($get_attempt) {
                $items[] = $data;
            }
        } else {
            $items[] = $data;
        }
    }

    $stt = 0;
    foreach ($items as $item) {

        $student_name = $item->firstname . ' ' . $item->lastname;
        $quizz_name = $item->quiz . '- lần làm bài thứ ' . $item->attempt;
        $stt++;
        $row = new html_table_row();
        $row->cells[] = new html_table_cell('<input type="checkbox" name="select_row[]" value="' . $item->id . '">');
        $row->cells[] = new html_table_cell($stt);
        $row->cells[] = new html_table_cell($item->course);
        $row->cells[] = new html_table_cell($item->courseidnumber);
        $row->cells[] = new html_table_cell($quizz_name);
        $row->cells[] = new html_table_cell($student_name);
        $row->cells[] = new html_table_cell($item->fieldvalue);
        $row->cells[] = new html_table_cell(
            (!empty($item->request_time) && $item->request_time > 0)
                ? userdate($item->request_time, '%d/%m/%Y')
                : ''
        );

        $table->data[] = $row;
    }
}

$users = $DB->get_records_sql("
    SELECT DISTINCT u.id, u.firstname, u.lastname, u.username, u.email,
        u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
    FROM {user} AS u
    JOIN {role_assignments} AS ra ON ra.userid = u.id
    JOIN {role} AS r ON r.id = ra.roleid
    WHERE r.shortname = :roleshortname
    AND u.deleted = 0
    AND u.suspended = 0
    ORDER BY u.firstname, u.lastname
", [
    'roleshortname' => get_config('block_th_assign_grading', 'role_grading'),
    'companyid' => $companyid
]);

$select = '<select name="task" id="task">';
foreach ($users as $user) {
    $ctvname = fullname($user) . ', ' . $user->username . ', ' . $user->email;
    $numberctv = $DB->get_record_sql("SELECT COUNT(id) as number FROM {block_th_assign_grading} WHERE ctvid = $user->id");
    $marking = 'Đang chấm: ' . $numberctv->number . ' ';
    $select .= '<option value="' . $user->id . '">' . $ctvname . ' - ' . $marking . '</option>';
}
$select .= '</select>';

echo $OUTPUT->header();
echo "<center><h4>Quản lý phân chia công việc cho CTV</h4></center>";
$baseurl = new moodle_url('/blocks/th_assign_grading/edit.php');
if ($editcontrols = block_th_assign_grading_controls($context, $baseurl)) {
    echo $OUTPUT->render($editcontrols);
}

$table->attributes = array('class' => 'table', 'border' => '1');
$html = html_writer::table($table);
$lang = current_language();
$PAGE->requires->js_call_amd('local_thlib/main', 'init', array('.table', "Báo cáo gán chấm điểm", $lang));
?>
<form method="post" action="process_selection.php" onsubmit="return validateForm()">
    <div>
        <label for="task">Chọn cộng tác viên:</label>
        <?php echo $select; ?>
    </div>
    <br>
    <?php echo $html; ?>
    <input type="submit" value="Gán" style="margin-top:10px">
</form>

<script type="text/javascript">
    let lastChecked = null;

    document.addEventListener('DOMContentLoaded', (event) => {
        const checkboxes = document.querySelectorAll('input[name="select_row[]"]');
        checkboxes.forEach(checkbox => checkbox.addEventListener('click', handleCheck));

        document.getElementById('select-all').addEventListener('click', function() {
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });
    });

    function handleCheck(event) {
        let checkboxes = document.querySelectorAll('input[name="select_row[]"]');
        if (!lastChecked) {
            lastChecked = event.target;
            return;
        }

        if (event.shiftKey) {
            let start = checkboxes.length - 1;
            let end = 0;

            checkboxes.forEach((checkbox, index) => {
                if (checkbox === lastChecked || checkbox === event.target) {
                    if (start > index) start = index;
                    if (end < index) end = index;
                }
            });

            checkboxes.forEach((checkbox, index) => {
                if (index >= start && index <= end) {
                    checkbox.checked = lastChecked.checked;
                }
            });
        }

        lastChecked = event.target;
    }

    function validateForm() {
        const checkboxes = document.querySelectorAll('input[name="select_row[]"]');
        let isChecked = false;
        checkboxes.forEach(checkbox => {
            if (checkbox.checked) {
                isChecked = true;
            }
        });

        if (!isChecked) {
            alert("Vui lòng chọn bài kiểm tra cần gán!");
            return false;
        }
        return true;
    }
</script>

<?php
echo $OUTPUT->footer();
?>

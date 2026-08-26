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

$pageurl = '/blocks/th_assign_grading/index.php';
$title = get_string('title', 'block_th_assign_grading');
$PAGE->set_url('/blocks/th_assign_grading/index.php');
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('th_assign_grading', 'block_th_assign_grading'));
$PAGE->set_title($SITE->fullname . ': ' . get_string('title', 'block_th_assign_grading'));

if ($returnurl) {
    $returnurl = new moodle_url($returnurl);
} else {
    $returnurl = new moodle_url('/blocks/th_assign_grading/index.php');
}

$hascapability = has_capability('block/th_assign_grading:delete', $context);

$companyid = 0;
if (class_exists('iomad')) {
    // Set the companyid
    $companyid = iomad::get_my_companyid($context);
    // Kiểm tra cuối cùng
    if (empty($companyid)) {
        print_error('nocompany', 'block_th_assign_grading');
    }
}

// LỌC DỮ LIỆU THEO COMPANY
$list_data = $DB->get_records_sql("
    SELECT tat.* 
    FROM {th_assign_turns} tat
    LEFT JOIN {company_users} cu ON cu.userid = tat.userid
    WHERE cu.companyid = :companyid
    ORDER BY tat.id
", ['companyid' => $companyid]);

$table = new html_table();
$table->head = array(
    get_string('stt', 'block_th_assign_grading'),
    get_string('fullname', 'block_th_assign_grading'),
    get_string('aummcode', 'block_th_assign_grading'),
    get_string('assigncount', 'block_th_assign_grading'),
    get_string('usedcount', 'block_th_assign_grading')
);

if ($hascapability) {
    $table->head[] = get_string('delete', 'block_th_assign_grading');
    $table->head[] = get_string('update', 'block_th_assign_grading');
}

$stt = 0;
$th_crm_code = get_config('block_th_assign_grading', 'th_crm_code');
if(!$th_crm_code) {
    $th_crm_code = 'th_crm_code';
}

foreach ($list_data as $item) {
    $student = $DB->get_record_sql("
	SELECT u.firstname, u.lastname, ucrm.data
        FROM {user} u
        LEFT JOIN {user_info_data} ucrm ON ucrm.userid = u.id 
		AND ucrm.fieldid = (SELECT id 
                                    FROM {user_info_field} uif 
                                    WHERE uif.shortname = :th_crm_code)
        WHERE u.id = :userid", ['th_crm_code' => $th_crm_code, 'userid' => $item->userid]
    );
    $student_name = $student->firstname . ' ' . $student->lastname;
    $used = $item->used;
    $stt++;
    $row = new html_table_row();
    $row->cells[] = new html_table_cell($stt);
    $row->cells[] = new html_table_cell($student_name);
    $row->cells[] = new html_table_cell($student->data);
    $row->cells[] = new html_table_cell($item->numberturns);
    $row->cells[] = new html_table_cell($used);
    if ($hascapability) {
        $delete = html_writer::link(
            new moodle_url('/blocks/th_assign_grading/action.php?delete_assign_turns=' . $item->id),
            $OUTPUT->pix_icon('t/delete', get_string('delete')),
            array('class' => 'th-delete-link1', 'title' => get_string('delete'))
        );
        $row->cells[] = new html_table_cell($delete);
        $edit = html_writer::link(
            new moodle_url('/blocks/th_assign_grading/update.php?update_assign_turns=' . $item->id),
            $OUTPUT->pix_icon('t/edit', get_string('edit')),
            array('title' => get_string('edit'))
        );
        $row->cells[] = new html_table_cell($edit);
    }
    $table->data[] = $row;
}

$table->attributes = array('class' => 'table', 'border' => '1');
$html = html_writer::table($table);
$lang = current_language();
$PAGE->requires->js_call_amd('local_thlib/main', 'init', array('.table', "Báo cáo số lượt gán học viên", $lang));
echo $OUTPUT->header();
echo "<center><h4>Quản lý phân chia công việc cho CTV</h4></center>";
$baseurl = new moodle_url('/blocks/th_assign_grading/index.php');

if ($editcontrols = block_th_assign_grading_controls($context, $baseurl)) {
    echo $OUTPUT->render($editcontrols);
}
echo $html;

echo $OUTPUT->footer();
?>

<!-- Include JavaScript for delete confirmation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var deleteLinks = document.querySelectorAll('.th-delete-link1');
    deleteLinks.forEach(function(link) {
        link.addEventListener('click', function(event) {
            if (!confirm('Bạn có chắc chắn muốn xóa mục này?')) {
                event.preventDefault();
            }
        });
    });
});
</script>

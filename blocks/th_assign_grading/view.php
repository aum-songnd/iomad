<?php

require_once '../../config.php';
require_once $CFG->dirroot . '/blocks/th_assign_grading/classes/lib.php';
require_once $CFG->dirroot . '/blocks/th_assign_grading/approval_data_filter_form.php';

global $DB, $OUTPUT, $PAGE, $COURSE, $USER, $SESSION;

// Check for all required variables.
$courseid = $COURSE->id;
$returnto = optional_param('returnto', 'course', PARAM_ALPHANUM); // Generic navigation return page switch.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourse', 'block_th_assign_grading', $courseid);
}

require_login($courseid);
require_capability('block/th_assign_grading:approve_grading', context_course::instance($COURSE->id));

$pageurl = new moodle_url('/blocks/th_assign_grading/view.php');
$title = get_string('title', 'block_th_assign_grading');
$context = context_system::instance();
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('th_assign_grading', 'block_th_assign_grading'));
$PAGE->set_title($SITE->fullname . ': ' . get_string('title', 'block_th_assign_grading'));
$settingsnode = $PAGE->navbar->add(get_string('breadcrumb', 'block_th_assign_grading'), $pageurl);
$settingsnode->make_active();

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$companyid = 0;
if (class_exists('iomad')) {

    // Set the companyid
    $companyid = iomad::get_my_companyid($context);
    // Kiểm tra cuối cùng
    if (empty($companyid)) {
        print_error('nocompany', 'block_th_assign_grading');
    }
}
$mform = new approval_data_filter_form(null, ['companyid' => $companyid]);
$wherecondition = '';
$params = [];
$status = 0;
if ($mform->is_cancelled()) {

    redirect($pageurl);
} else if ($mform->is_submitted() && $data = $mform->get_data()) {
    $data->to_date = strtotime('+1 day', $data->to_date);
    $selected_userids = $data->userid ?? [];
    $selected_courseids = $data->coursename ?? [];
    $fromdate = $data->from_date ?? 0;
    $todate = $data->to_date ?? 0;
    $status = $data->status ?? 0;

    // Check filter
    $has_filter = !empty($selected_userids) || !empty($selected_courseids) || !empty($fromdate) || !empty($todate) || ($status != 0);

    // If has filter then assign where condition for sql
    if ($has_filter) {
        $wherecondition = [];

        if (!empty($selected_userids)) {
            list($in_sql, $in_params) = $DB->get_in_or_equal($selected_userids, SQL_PARAMS_NAMED, 'uid_');
            $wherecondition[] = "ctvid $in_sql";
            $params += $in_params;
        }

        if ($status == 1) {

            $wherecondition[] = "status = 1";
        } else if ($status == 2) {

            $wherecondition[] = "(status IS NULL AND grading_date IS NULL)";
        }


        $wherecondition = implode(' AND ', $wherecondition);
    }
}

if ($editcontrols = block_th_assign_grading_controls($context, $pageurl)) {
    echo $OUTPUT->render($editcontrols);
}

$hascapability = has_capability('block/th_assign_grading:delete', $context);

$attemps_sql = "SELECT * FROM {block_th_assign_grading}";

if (!empty($wherecondition)) {
    $attemps_sql .= " WHERE $wherecondition";
}

$list_attempts = $DB->get_records_sql($attemps_sql, $params);

$failed_ids = $SESSION->mass_approval_failed_ids ?? [];
unset($SESSION->mass_approval_failed_ids);

if (!empty($SESSION->mass_approval_success)) {
    echo $OUTPUT->notification($SESSION->mass_approval_success, 'notifysuccess');
    unset($SESSION->mass_approval_success);
}

if (!empty($SESSION->mass_approval_failed_rows)) {
    $rows = implode(', ', $SESSION->mass_approval_failed_rows);
    echo $OUTPUT->notification(get_string('approvalfailedrows', 'block_th_assign_grading', $rows), 'notifyproblem');
    unset($SESSION->mass_approval_failed_rows);
}
echo $mform->display();
$table = new html_table();
$table->head = array(
    '<input type="checkbox" id="select-all" title="' . get_string('selectall', 'block_th_assign_grading') . '">',
    get_string('stt', 'block_th_assign_grading'),
    get_string('gradername', 'block_th_assign_grading'),
    get_string('coursename', 'block_th_assign_grading'),
    get_string('quizname', 'block_th_assign_grading'),
    get_string('submissionlink', 'block_th_assign_grading'),
    get_string('studentname', 'block_th_assign_grading'),
    get_string('submittedat', 'block_th_assign_grading'),
    get_string('status', 'block_th_assign_grading'),
    get_string('approval', 'block_th_assign_grading'),
    get_string('approvedby', 'block_th_assign_grading')
);

if ($hascapability) {
    $table->head[] = get_string('delete', 'block_th_assign_grading');
}

$stt = 0;
foreach ($list_attempts as $attempt) {
    $ctv = $DB->get_record_sql("SELECT * FROM {user} as u WHERE u.id = $attempt->ctvid");
    
    $quiz_attempt = $DB->get_record_sql("SELECT qa.id, qa.attempt,qa.timefinish ,u.firstname, u.lastname, q.name, c.fullname, c.id AS courseid 
                                        FROM {quiz_attempts} as qa JOIN {user} as u ON qa.userid = u.id 
                                        JOIN {quiz} as q ON qa.quiz = q.id 
                                        JOIN {course} as c ON c.id = q.course 
                                        JOIN {company_users} as cu ON cu.userid = qa.userid 
                                        WHERE qa.id = $attempt->attemptid AND qa.preview = 0 AND cu.companyid = :companyid3", 
                                        ['companyid3' => $companyid]);
    if (empty($quiz_attempt)) {
        continue;
    }
    
    if (!empty($selected_courseids) && !in_array($quiz_attempt->courseid, $selected_courseids)) {
        continue;
    }

    if ($status != 2) {
        if (!empty($fromdate) && $quiz_attempt->timefinish < $fromdate) {
            continue;
        }

        if (!empty($todate) && $quiz_attempt->timefinish > $todate) {
            continue;
        }
    }
    $stt++;
    $ctv_name = $ctv->firstname . ' ' . $ctv->lastname;
    $student_name = $quiz_attempt->firstname . ' ' . $quiz_attempt->lastname;
    $quizz_name = $quiz_attempt->name . '- lần làm bài thứ ' . $quiz_attempt->attempt;
    $course_name = $quiz_attempt->fullname;

    $checkbox_disabled = 'disabled';
    $input_stt = '';
    $status = $attempt->status ? '<span style="color:red">Đã chấm bài</span>' : 'Chưa chấm bài';

    if ($attempt->confirm_grading) {
        $user_confirm = $DB->get_record_sql("SELECT * FROM {user} as u WHERE u.id = $attempt->userid_confirm");
        $user_confirm_name = $user_confirm->firstname . ' ' . $user_confirm->lastname;
        $href = $CFG->wwwroot . '/blocks/th_scoring_collaborators/review.php?attempt=' . $attempt->attemptid . '&confirm=1';
        $button = '<a class="th_link_attempt_confirm" href="' . $href . '">Xem bài chấm</a>
            <a class="th_success">Đã duyệt</a>';
    } else {
        if ($attempt->status) {
            $confirm_grading = 1;
            $href =   $CFG->wwwroot . '/blocks/th_scoring_collaborators/review.php?attempt=' . $attempt->attemptid . '&id=' . $attempt->id . '&confirm=1&status=' . $confirm_grading;
            $button = '<a class="th_link_attempt_confirm" href="' . $href . '">Xem bài chấm</a>    
            <a class="th_wait">Chưa duyệt</a>';
            $checkbox_disabled = '';
            $input_stt = '<input type="hidden" name="row_stt[' . $attempt->id . ']" value="' . $stt . '">';
        } else {
            $href =  $CFG->wwwroot . '/blocks/th_scoring_collaborators/review.php?attempt=' . $attempt->attemptid . '&id=' . $attempt->id . '&confirm=1';
            $button = '<a class="th_link_attempt_confirm" href="' . $href . '">Xem bài chấm</a>';
        }

        $user_confirm_name = 'Chưa có người phê duyệt';
    }

    $row = new html_table_row();
    $is_failed = in_array($attempt->id, $failed_ids);
    $row->attributes['class'] = $is_failed ? 'th_row_error' : '';
    $row->cells[] = new html_table_cell('<input type="checkbox" name="select_row[]" value="' . $attempt->id . '" ' . $checkbox_disabled . '>' . $input_stt);
    $row->cells[] = new html_table_cell($stt);
    $row->cells[] = new html_table_cell($ctv_name);
    $row->cells[] = new html_table_cell($course_name);
    $row->cells[] = new html_table_cell($quizz_name);
    $row->cells[] = new html_table_cell(html_writer::link($href, $href, array('class' => 'th_link')));
    $row->cells[] = new html_table_cell($student_name);
    $row->cells[] = new html_table_cell(date('d/m/Y', $quiz_attempt->timefinish));
    $row->cells[] = new html_table_cell($status);
    $row->cells[] = new html_table_cell($button);
    $row->cells[] = new html_table_cell($user_confirm_name);
    if ($hascapability) {
        $delete = html_writer::link(
            new moodle_url('/blocks/th_assign_grading/action.php?delete_attempt=' . $attempt->id),
            $OUTPUT->pix_icon('t/delete', get_string('delete')),
            array('class' => 'th-delete-link', 'title' => get_string('delete'))
        );
        $row->cells[] = new html_table_cell($delete);
    }
    $table->data[] = $row;
}

$table->attributes = array('class' => 'table', 'border' => '1');
$html = html_writer::table($table);
$lang = current_language();
$PAGE->requires->js_call_amd('local_thlib/main', 'init', array('.table', "Báo cáo gán chấm điểm", $lang));
$PAGE->requires->js_call_amd('block_th_assign_grading/validateForm', 'init');
// echo $html;

?>
<form id="mass_approval" method="post" action="mass_approval.php" onsubmit="">

    <?php echo $html; ?>
    <input type="submit" value="<?php echo get_string('approvesubmit', 'block_th_assign_grading'); ?>" style="margin-top:10px">
</form>
<!-- Include JavaScript for delete confirmation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var deleteLinks = document.querySelectorAll('.th-delete-link');
    deleteLinks.forEach(function(link) {
        link.addEventListener('click', function(event) {
            if (!confirm('Bạn có chắc chắn muốn xóa mục này?')) {
                event.preventDefault();
            }
        });
    });
});

let lastChecked = null;

document.addEventListener('DOMContentLoaded', (event) => {
    const checkboxes = document.querySelectorAll('input[name="select_row[]"]');
    checkboxes.forEach(checkbox => checkbox.addEventListener('click', handleCheck));

    document.getElementById('select-all').addEventListener('click', function () {
        checkboxes.forEach(checkbox => {
            if (!checkbox.disabled) {
                checkbox.checked = this.checked;
            }
        });
    });
});

function handleCheck(event) {
    let checkboxes = Array.from(document.querySelectorAll('input[name="select_row[]"]'))
        .filter(cb => !cb.disabled);

    if (!lastChecked) {
        lastChecked = event.target;
        return;
    }

    if (event.shiftKey) {
        let start = checkboxes.indexOf(lastChecked);
        let end = checkboxes.indexOf(event.target);

        if (start > -1 && end > -1) {
            let [min, max] = [Math.min(start, end), Math.max(start, end)];
            for (let i = min; i <= max; i++) {
                checkboxes[i].checked = lastChecked.checked;
            }
        }
    }

    lastChecked = event.target;
}
</script>

<?php
echo $OUTPUT->footer();
?>

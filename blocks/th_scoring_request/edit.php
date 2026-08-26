<?php

require_once '../../config.php';

global $DB, $OUTPUT, $PAGE, $COURSE, $USER;

// Check for all required variables.
$courseid = $COURSE->id;
$returnto = optional_param('returnto', 'course', PARAM_ALPHANUM); // Generic navigation return page switch.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourse', 'block_th_scoring_request', $courseid);
}

require_login($courseid);

$pageurl = '/blocks/th_scoring_request/edit.php';
$title = get_string('title', 'block_th_scoring_request');
$PAGE->set_url('/blocks/th_scoring_request/edit.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('th_scoring_request', 'block_th_scoring_request'));
$PAGE->set_title($SITE->fullname . ': ' . get_string('title', 'block_th_scoring_request'));

$quizid = optional_param('quizid', -1, PARAM_INT);
$course = optional_param('course', -1, PARAM_INT);
$attempt = optional_param('attempt', -1, PARAM_INT);
$atempt_review = optional_param('atempt_review', -1, PARAM_INT);
$redirect = optional_param('redirect', -1, PARAM_INT);
$delete = optional_param('delete', -1, PARAM_INT);

if ($quizid != -1 && !empty($quizid) && $course != -1 && !empty($course) && $attempt != -1 && !empty($attempt) && $atempt_review != -1 && !empty($atempt_review)) {
    $scoring_request = $DB->get_record_sql("SELECT * FROM {block_th_scoring_request} WHERE atempt_review = ?", array($atempt_review));

    if (empty($scoring_request)) {
        $newData = array(
            'quizid' => $quizid,
            'userid' => $USER->id,
            'attemptid' => $attempt,
            'atempt_review' => $atempt_review,
            'status' => 1,
            'time' => time(),
        );
        $insert_success = $DB->insert_record('block_th_scoring_request', (object)$newData);
        if ($insert_success) {
            $attemp_data = $DB->get_record_sql("SELECT * FROM {th_assign_turns} WHERE userid = ?", array($USER->id));
            if ($attemp_data) {
                if ($attemp_data->unused == 0) {
                    $newData = array(
                        'id' => $attemp_data->id,
                        'unused' => 0,
                    );
                } else {
                    $newData = array(
                        'id' => $attemp_data->id,
                        'used' => $attemp_data->used + 1,
                        'unused' => $attemp_data->unused - 1,
                    );
                }
                $newData = array(
                    'id' => $attemp_data->id,
                    'used' => $attemp_data->used + 1,
                    'unused' => $attemp_data->unused - 1,
                );
                $DB->update_record('th_assign_turns', (object)$newData);
            }
        }
        if ($redirect == 1) {
            redirect($CFG->wwwroot . '/course/view.php?id=' . $course, 'Thành công', null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($CFG->wwwroot . '/blocks/th_scoring_request/view.php?courseid=' . $course, 'Thành công', null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } else {
        $newData = array(
            'id' => $scoring_request->id,
            'quizid' => $quizid,
            'userid' => $USER->id,
            'attemptid' => $attempt,
            'atempt_review' => $atempt_review,
            'status' => 1,
            'time' => time(),
        );
        $update_success = $DB->update_record('block_th_scoring_request', (object)$newData);
        if ($update_success) {
            $attemp_data = $DB->get_record_sql("SELECT * FROM {th_assign_turns} WHERE userid = ?", array($USER->id));
            if ($attemp_data) {
                if ($attemp_data->unused == 0) {
                    $newData = array(
                        'id' => $attemp_data->id,
                        'unused' => 0,
                    );
                } else {
                    $newData = array(
                        'id' => $attemp_data->id,
                        'used' => $attemp_data->used + 1,
                        'unused' => $attemp_data->unused - 1,
                    );
                }
                $newData = array(
                    'id' => $attemp_data->id,
                    'used' => $attemp_data->used + 1,
                    'unused' => $attemp_data->unused - 1,
                );
                $DB->update_record('th_assign_turns', (object)$newData);
            }
        }
        if ($redirect == 1) {
            redirect($CFG->wwwroot . '/course/view.php?id=' . $course, get_string('success', 'block_th_scoring_request'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($CFG->wwwroot . '/blocks/th_scoring_request/view.php?courseid=' . $course, get_string('success', 'block_th_scoring_request'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
} else if ($delete == 1 && $atempt_review) {
    $delete_success = $DB->delete_records('block_th_scoring_request', array('atempt_review' => $atempt_review));
    if ($delete_success) {

        $attemp_data = $DB->get_record_sql("SELECT * FROM {th_assign_turns} WHERE userid = ?", array($USER->id));
        if ($attemp_data) {
            $newData = array(
                'id' => $attemp_data->id,
                'used' => $attemp_data->used - 1,
                'unused' => $attemp_data->unused + 1,
            );
            $DB->update_record('th_assign_turns', (object)$newData);
        }
        if ($redirect == 1) {
            redirect($CFG->wwwroot . '/course/view.php?id=' . $course, get_string('success', 'block_th_scoring_request'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($CFG->wwwroot . '/blocks/th_scoring_request/view.php?courseid=' . $course, get_string('success', 'block_th_scoring_request'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->footer();

?>

<!-- Include JavaScript for delete confirmation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var deleteLinks = document.querySelectorAll('.delete-link');
    deleteLinks.forEach(function(link) {
        link.addEventListener('click', function(event) {
            if (!confirm('<?php echo get_string('confirm_delete', 'block_th_scoring_request'); ?>')) {
                event.preventDefault();
            }
        });
    });
});
</script>

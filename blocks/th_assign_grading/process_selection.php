<?php
require_once '../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedTask = isset($_POST['task']) ? $_POST['task'] : null;
    if (isset($_POST['select_row'])) {
        $selectedRows = $_POST['select_row'];
        foreach ($selectedRows as $rowId) {
            $attemp_data = $DB->get_record_sql("SELECT * FROM {block_th_assign_grading} as ag WHERE ag.attemptid = $rowId");
            if(empty($attemp_data)) { 
                $ctvdata = array(
                    'ctvid' => $selectedTask,
                    'attemptid' => $rowId,
                    'assignment_time' => time()

                );
                $insert_success = $DB->insert_record('block_th_assign_grading', (object)$ctvdata);
            }
        }
        if ($insert_success) {
            redirect($CFG->wwwroot . '/blocks/th_assign_grading/view.php', 'Thành công', null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($CFG->wwwroot . '/blocks/th_assign_grading/view.php', 'Thất bại', null, \core\output\notification::NOTIFY_ERROR);
        }
    } else {
        echo "No rows selected.";
    }
}

?>
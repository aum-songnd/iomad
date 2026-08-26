<?php
require_once '../../config.php';

require_login();

global $DB, $USER, $SESSION;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $failedRows = [];
    $failedIds = [];
    $successCount = 0;
    $totalRows = 0;
    if (isset($_POST['select_row'])) {
        $selectedRows = $_POST['select_row'];

        foreach ($selectedRows as $rowId) {
            $totalRows ++;
            $stt = $_POST['row_stt'][$rowId] ?? '??';
            try {
                $data = $DB->get_record('block_th_assign_grading', array('id' => $rowId), '*', MUST_EXIST);

                $data->confirm_grading = 1;
                $data->userid_confirm = $USER->id;
                $data->confirm_date = time();

                $update_success = $DB->update_record('block_th_assign_grading', $data);

                if (!$update_success) {
                    $failedRows[] = $stt;
                    $failedIds[] = $rowId;
                    continue;
                }

                $successCount++;
            } catch (Exception $e) {
                $failedRows[] = $stt;
                $failedIds[] = $rowId;
            }
            
        }
        
        if (!empty($failedRows)) {
            $SESSION->mass_approval_failed_rows = $failedRows;
            $SESSION->mass_approval_failed_ids = $failedIds;

        }
        if ($successCount > 0) {
            $SESSION->mass_approval_success = get_string('massapprovalsuccess', 'block_th_assign_grading', [
                'success' => $successCount,
                'total' => $totalRows
            ]);
        }

        // Trigger event log
        \block_th_assign_grading\event\approval_completed::create([
            'objectid' => 0,
            'context' => context_system::instance(),
            'userid' => $USER->id,
            'other' => [
                'approved_count' => $successCount,
                'approved_ids' => array_diff($selectedRows, $failedIds),
                'mode' => 'bulk'
            ]
        ])->trigger();

        // Redirect
        redirect(new moodle_url('/blocks/th_assign_grading/view.php'));
        
    } else {
        echo "No rows selected.";
    }
}

?>
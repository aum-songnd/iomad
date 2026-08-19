<?php

function th_bundleassign_api_clean_special_characters($string) {
	$string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
	$string = preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.

	return preg_replace('/-+/', '', $string); // Replaces multiple hyphens with single one.
}

function th_bundleassign_api_check_phone($phone) {

	$phone2 = th_bundleassign_api_clean_special_characters($phone);
	if (is_numeric($phone2)) {
		if (preg_match('/^[0-9]+$/', $phone2)) {
			if (substr($phone2, 0, 2) == 84) {
				if (substr($phone2, 2, 1) == 0) {
					$phone2 = substr($phone2, 2);
				} else {
					$phone2 = 0 . substr($phone2, 2);
				}
			}
		} else {
			if (substr($phone2, 0, 3) == "+84") {
				if (substr($phone2, 3, 1) == 0) {
					$phone2 = substr($phone2, 3);
				} else {
					$phone2 = 0 . substr($phone2, 3);
				}
			} else if (substr($phone2, 0, 1) == "+") {
				$phone2 = substr($phone2, 1);
			} else {
				return false;
			}
		}
	} else {
		return false;
	}
	return $phone2;
}

// function th_bundleassign_api_sendmail_success($userid, $a) {

// 	$data = array(
// 		'coursefullname' => $a->coursefullname,
//         'totalprice' => $a->totalprice,
//         'userfullname' => $a->userfullname,
//         'userid' => $userid,
//     );

//     $task = new local_th_bundleassign_api\task\sendmail_success_adhoc_task();
//     $task->set_custom_data($data);
//     \core\task\manager::reschedule_or_queue_adhoc_task($task);
// }

// function th_bundleassign_api_sendmail_error() {

// 	$data = array('userid' => 2);

//     $task = new local_th_bundleassign_api\task\sendmail_error_adhoc_task();
//     $task->set_custom_data($data);
//     \core\task\manager::reschedule_or_queue_adhoc_task($task);
// }


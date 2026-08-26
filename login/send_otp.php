<?php
define('AJAX_SCRIPT', true);
require_once '../config.php';
require_once('classes/logins.php');

global $DB, $PAGE, $CFG;
$PAGE->set_context(context_system::instance());

if (isset($_POST['typesend'])) {
	$typesend = trim($_POST['typesend']);
} else {
	logins::response_error();
}

if (!in_array($typesend, [logins::$login_use_email, logins::$fg_pw_use_email])) {
    logins::response_error();
}

if (isset($_POST['email']) and !empty($_POST['email'])) {
	$email = trim($_POST['email']);
	$email = core_text::strtolower($email);
} else {
	logins::response_error();
}

if ($typesend == logins::$login_use_email) {
	if (isset($_POST['username']) AND !empty($_POST['username'])) {
		$username = trim($_POST['username']);
		$username = core_text::strtolower($username);
		$sql = "SELECT COUNT(id) FROM {user} WHERE (username = ? AND email = ?) OR (username = ? AND email = ?)";
		if ($DB->count_records_sql($sql, array($username, $email, $email, $email)) > 0) {
			logins::send_otp($typesend, $username, $email);
		} else {
			logins::response_error();
		}
	} else {
		logins::response_error();
	}
} else {
	if (isset($_POST['username']) AND !empty($_POST['username'])) {
		$username = trim($_POST['username']);
		$username = core_text::strtolower($username);
		if ($DB->record_exists('user', ['email' => $email, 'username' => $username])) {
			logins::send_otp($typesend, $username, $email);
		} else {
			logins::response_error();
		}
	} else {
		if ($DB->record_exists('user', ['email' => $email])) {
			logins::send_otp($typesend, '', $email);
		} else {
			logins::response_error();
		}
	}
}
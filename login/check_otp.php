<?php
define('AJAX_SCRIPT', true);
require_once('../config.php');
require_once('classes/logins.php');

global $DB, $CFG;
if (isset($_POST['typesend'])) {
	$typesend = trim($_POST['typesend']);
} else {
	logins::response_error(get_string('error', 'local_th_config_login'));
}

if (isset($_POST['email']) and !empty($_POST['email'])) {
	$email = trim($_POST['email']);
	$email = core_text::strtolower($email);
} else {
	logins::response_error(get_string('email_empty', 'local_th_config_login'));
}

if (!in_array($typesend, [logins::$login_use_email, logins::$fg_pw_use_email])) {
    logins::response_error(get_string('error', 'local_th_config_login'));
}

if (isset($_POST['username']) and !empty($_POST['username'])) {
	$username = trim($_POST['username']);
	$username = core_text::strtolower($username);
}

if (isset($_POST['otp']) and !empty($_POST['otp'])) {
	$otp = trim($_POST['otp']);
} else {
	logins::response_error(get_string('otp_empty', 'local_th_config_login'));
}
$sql = "SELECT u.*, upr.otp, upr.timerequested, upr.id as otpid, upr.code
	FROM {user} u
	JOIN {local_th_user_login} upr ON upr.userid = u.id
	WHERE upr.otp = :otp AND upr.typesend=:typesend
	and upr.userid=(SELECT id FROM {user} WHERE username = :username AND email = :email AND deleted = 0 AND suspended = 0)";
$params = [
	'otp' => $otp,
	'email' => $email,
	'typesend' => $typesend
];

if (isset($username) and !empty($username)) { 
	$params['username'] = $username;
} else {
	$sql = "SELECT u.*, upr.otp, upr.timerequested, upr.id as otpid, upr.code
		FROM {user} u
		JOIN {local_th_user_login} upr ON upr.userid = u.id
		WHERE upr.otp=:otp AND upr.typesend=:typesend
		and upr.userid=(SELECT id FROM {user} WHERE email = :email AND deleted = 0 AND suspended = 0)";
}
$user = $DB->get_record_sql($sql, $params);
$config_login = get_config('local_th_config_login');
$otplogintime =  $config_login->timeexpiry ? $config_login->timeexpiry : 1800;
if (empty($user) or ($user->timerequested < (time() - $otplogintime - DAYSECS))) {
	logins::response_error(get_string('no_otp', 'local_th_config_login'));
} else if ($user->timerequested < (time() - $otplogintime)){
	logins::response_error(get_string('expiry_otp', 'local_th_config_login'));
} else {
	if (password_verify($user->timerequested, $user->code)) {
		logins::response_success();
	} else {
		logins::response_error(get_string('otp_incorrect', 'local_th_config_login'));
	}
}
<?php

class logins {

    public static $login_use_email = 0; // login use email otp
    public static $fg_pw_use_email = 1; //forgot password use email otp

    public static function response_error($message='') {
        echo json_encode([
            'status' => false,
            'message' => $message
        ]);
        exit;
    }

    public static function response_success($otp='') {
        echo json_encode([
            'status' => true,
            'otp' => $otp
        ]);
        exit;
    }
    public static function send_otp($typesend, $username, $email) {
        
        global $DB;
        $time = time();
        $timeexpiry = get_config('local_th_config_login', 'timeexpiry');
        $timeexpiry = $timeexpiry ? (int)$timeexpiry : 1800;
        if ($typesend == logins::$login_use_email) {
            $user = $DB->get_record('user', array('username' => $username));
        } else {
            $user = $DB->get_record('user', array('email' => $email));
        }
        
        $params = array (
            'typesend' => $typesend,
            'userid' => $user->id
        );

        if (!$DB->record_exists_select('local_th_user_login', "timerequested > ($time-$timeexpiry) AND typesend = :typesend AND userid = :userid", $params)) {
            $time = time();
            $userfrom = \core_user::get_support_user();
            $r = new stdClass();
            $r->userid = $user->id;
            $r->timerequested = $time;
            $r->typesend = $typesend;
            $r->otp = random_int(100000, 999999);
            $r->code = password_hash($time, PASSWORD_BCRYPT);

            $data = [];
            $data['otp'] = $r->otp;
            $data['timeexpiry'] = floor($timeexpiry / MINSECS);

            $site = get_site();
            if ($typesend == logins::$login_use_email) {
                $subject = format_string($site->fullname) . ': ' . get_string('otp_login', 'local_th_config_login');
                $message = get_string('message_send_otp', 'local_th_config_login', $data);
            } else {
                $subject = format_string($site->fullname) . ': ' . get_string('otp_forgot', 'local_th_config_login');
                $message = get_string('message_send_otp_forgot', 'local_th_config_login', $data);
            }
            $send_ok = email_to_user($user, $userfrom, $subject, $message);
            if ($send_ok) {
                $DB->insert_record('local_th_user_login', $r);
                logins::response_success();
            } else {
                logins::response_error();
            }
        } else {
            logins::response_error();
        }
    }
}
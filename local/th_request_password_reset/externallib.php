<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/externallib.php');
require_once($CFG->dirroot . '/login/lib.php');

/**
 * External functions for TH Request Password Reset (bỏ qua forgottenpasswordurl)
 */
class local_th_request_password_reset_external extends external_api {

    /**
     * Returns description of method parameters
     */
    public static function th_request_password_reset_parameters()
    {
        return new external_function_parameters(
            array(
                'username' => new external_value(core_user::get_property_type('username'), 'User name', VALUE_DEFAULT, ''),
                'email' => new external_value(core_user::get_property_type('email'), 'User email', VALUE_DEFAULT, ''),
            )
        );
    }

    /**
     * Requests a password reset - Bản tùy chỉnh bỏ qua kiểm tra forgottenpasswordurl.
     *
     * @param  string $username user name
     * @param  string $email    user email
     * @return array warnings and success status
     */
    public static function th_request_password_reset($username = '', $email = '')
    {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/login/lib.php');

        $warnings = array();
        $params = self::validate_parameters(
            self::th_request_password_reset_parameters(),
            array(
                'username' => $username,
                'email' => $email,
            )
        );

        $context = context_system::instance();
        $PAGE->set_context($context);   // Needed by format_string calls.

        // $CFG->forgottenpasswordurl = '';
        // Check if an alternate forgotten password method is set.
        // if (!empty($CFG->forgottenpasswordurl)) {
        //     throw new moodle_exception('cannotmailconfirm');
        // }

        $errors = core_login_validate_forgot_password_data($params);
        if (!empty($errors)) {
            $status = 'dataerror';
            $notice = '';

            foreach ($errors as $itemname => $message) {
                $warnings[] = array(
                    'item' => $itemname,
                    'itemid' => 0,
                    'warningcode' => 'fielderror',
                    'message' => s($message)
                );
            }
        } else {
            list($status, $notice, $url) = self::core_login_process_password_reset($params['username'], $params['email']);
        }

        return array(
            'status' => $status,
            'notice' => $notice,
            'warnings' => $warnings,
        );
    }

    /**
     * Describes the th_request_password_reset return value.
     *
     * @return external_single_structure
     */
    public static function th_request_password_reset_returns()
    {

        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_ALPHANUMEXT, 'The returned status of the process:
                    dataerror: Error in the sent data (username or email). More information in warnings field.
                    emailpasswordconfirmmaybesent: Email sent or not (depends on user found in database).
                    emailpasswordconfirmnotsent: Failure, user not found.
                    emailpasswordconfirmnoemail: Failure, email not found.
                    emailalreadysent: Email already sent.
                    emailpasswordconfirmsent: User pending confirmation.
                    emailresetconfirmsent: Email sent.
                '),
                'notice' => new external_value(PARAM_RAW, 'Important information for the user about the process.'),
                'warnings'  => new external_warnings(),
            )
        );
    }

    public static function core_login_process_password_reset($username, $email) {
        global $CFG, $DB;

        if (empty($username) && empty($email)) {
            throw new \moodle_exception('cannotmailconfirm');
        }

        // Next find the user account in the database which the requesting user claims to own.
        if (!empty($username)) {
            // Username has been specified - load the user record based on that.
            $username = core_text::strtolower($username); // Mimic the login page process.
            $userparams = array('username' => $username, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0, 'suspended' => 0);
            $user = $DB->get_record('user', $userparams);
        } else {
            // Try to load the user record based on email address.
            // This is tricky because:
            // 1/ the email is not guaranteed to be unique - TODO: send email with all usernames to select the account for pw reset
            // 2/ mailbox may be case sensitive, the email domain is case insensitive - let's pretend it is all case-insensitive.
            //
            // The case-insensitive + accent-sensitive search may be expensive as some DBs such as MySQL cannot use the
            // index in that case. For that reason, we first perform accent-insensitive search in a subselect for potential
            // candidates (which can use the index) and only then perform the additional accent-sensitive search on this
            // limited set of records in the outer select.
            $sql = "SELECT *
                      FROM {user}
                     WHERE " . $DB->sql_equal('email', ':email1', false, true) . "
                       AND id IN (SELECT id
                                    FROM {user}
                                   WHERE mnethostid = :mnethostid
                                     AND deleted = 0
                                     AND suspended = 0
                                     AND " . $DB->sql_equal('email', ':email2', false, false) . ")";

            $params = array(
                'email1' => $email,
                'email2' => $email,
                'mnethostid' => $CFG->mnet_localhost_id,
            );

            $user = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
        }

        // Target user details have now been identified, or we know that there is no such account.
        // Send email address to account's email address if appropriate.
        $pwresetstatus = PWRESET_STATUS_NOEMAILSENT;
        if ($user and !empty($user->confirmed)) {
            $systemcontext = context_system::instance();

            $userauth = get_auth_plugin($user->auth);
            if (!$userauth->can_reset_password() or !is_enabled_auth($user->auth)
              or !has_capability('moodle/user:changeownpassword', $systemcontext, $user->id)) {
                if (send_password_change_info($user)) {
                    $pwresetstatus = PWRESET_STATUS_OTHEREMAILSENT;
                } else {
                    throw new \moodle_exception('cannotmailconfirm');
                }
            } else {
                // The account the requesting user claims to be is entitled to change their password.
                // Next, check if they have an existing password reset in progress.
                $resetinprogress = $DB->get_record('user_password_resets', array('userid' => $user->id));
                if (empty($resetinprogress)) {
                    // Completely new reset request - common case.
                    $resetrecord = core_login_generate_password_reset($user);
                    $sendemail = true;
                } else if ($resetinprogress->timerequested < (time() - $CFG->pwresettime)) {
                    // Preexisting, but expired request - delete old record & create new one.
                    // Uncommon case - expired requests are cleaned up by cron.
                    $DB->delete_records('user_password_resets', array('id' => $resetinprogress->id));
                    $resetrecord = core_login_generate_password_reset($user);
                    $sendemail = true;
                } else if (empty($resetinprogress->timererequested)) {
                    // Preexisting, valid request. This is the first time user has re-requested the reset.
                    // Re-sending the same email once can actually help in certain circumstances
                    // eg by reducing the delay caused by greylisting.
                    $resetinprogress->timererequested = time();
                    $DB->update_record('user_password_resets', $resetinprogress);
                    $resetrecord = $resetinprogress;
                    $sendemail = true;
                } else {
                    // Preexisting, valid request. User has already re-requested email.
                    $pwresetstatus = PWRESET_STATUS_ALREADYSENT;
                    $sendemail = false;
                }

                if ($sendemail) {
                    $sendresult = self::send_password_change_confirmation_email($user, $resetrecord);
                    if ($sendresult) {
                        $pwresetstatus = PWRESET_STATUS_TOKENSENT;
                    } else {
                        throw new \moodle_exception('cannotmailconfirm');
                    }
                }
            }
        }

        $url = $CFG->wwwroot.'/index.php';
        if (!empty($CFG->protectusernames)) {
            // Neither confirm, nor deny existance of any username or email address in database.
            // Print general (non-commital) message.
            $status = 'emailpasswordconfirmmaybesent';
            $notice = get_string($status);
        } else if (empty($user)) {
            // Protect usernames is off, and we couldn't find the user with details specified.
            // Print failure advice.
            $status = 'emailpasswordconfirmnotsent';
            $notice = get_string($status);
            $url = $CFG->wwwroot.'/forgot_password.php';
        } else if (empty($user->email)) {
            // User doesn't have an email set - can't send a password change confimation email.
            $status = 'emailpasswordconfirmnoemail';
            $notice = get_string($status);
        } else if ($pwresetstatus == PWRESET_STATUS_ALREADYSENT) {
            // User found, protectusernames is off, but user has already (re) requested a reset.
            // Don't send a 3rd reset email.
            $status = 'emailalreadysent';
            $notice = get_string($status);
        } else if ($pwresetstatus == PWRESET_STATUS_NOEMAILSENT) {
            // User found, protectusernames is off, but user is not confirmed.
            // Pretend we sent them an email.
            // This is a big usability problem - need to tell users why we didn't send them an email.
            // Obfuscate email address to protect privacy.
            $protectedemail = preg_replace('/([^@]*)@(.*)/', '******@$2', $user->email);
            $status = 'emailpasswordconfirmsent';
            $notice = get_string($status, '', $protectedemail);
        } else {
            // Confirm email sent. (Obfuscate email address to protect privacy).
            $protectedemail = preg_replace('/([^@]*)@(.*)/', '******@$2', $user->email);
            // This is a small usability problem - may be obfuscating the email address which the user has just supplied.
            $status = 'emailresetconfirmsent';
            $notice = get_string($status, '', $protectedemail);
        }
        return array($status, $notice, $url);
    }

    public static function send_password_change_confirmation_email($user, $resetrecord) {
        global $CFG, $DB;

        $site = get_site();
        $supportuser = core_user::get_support_user();
        $pwresetmins = isset($CFG->pwresettime) ? floor($CFG->pwresettime / MINSECS) : 30;

        $data = new stdClass();
        $data->firstname = $user->firstname;
        $data->lastname  = $user->lastname;
        $data->username  = $user->username;
        $data->sitename  = format_string($site->fullname);
        $dir = self::get_th_login_dir();
        if (!empty($CFG->forgottenpasswordurl)) {
            $data->link      = $CFG->wwwroot . '/' . $dir . '/forgot_password.php?token='. $resetrecord->token;
        } else {
            $data->link      = $CFG->wwwroot .'/login/forgot_password.php?token='. $resetrecord->token;
        }
        $data->admin     = generate_email_signoff();
        $data->resetminutes = $pwresetmins;

        $message = get_string('emailresetconfirmation', '', $data);
        $subject = get_string('emailresetconfirmationsubject', '', format_string($site->fullname));

        // Directly email rather than using the messaging system to ensure its not routed to a popup or jabber.
        return email_to_user($user, $supportuser, $subject, $message);

    }

    public static function get_th_login_dir() {

        $dir = trim(str_replace(str_split(':\*?"<>|/'),
                '',
                get_config('local_th_config_login', 'directory')),
                "/\\");

        return $dir;
    }

}

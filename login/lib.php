<?php

defined('MOODLE_INTERNAL') || die();
// require_once('../../config.php');

define('CLOUDFLARE_API_URL', 'https://challenges.cloudflare.com/turnstile/v0/api.js');
define('CLOUDFLARE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify');

function th_login_attempt_failed($user) {
    global $CFG;

    if ($user->mnethostid != $CFG->mnet_localhost_id) {
        return;
    }
    if (isguestuser($user)) {
        return;
    }

    // Force user preferences cache reload to ensure the most up-to-date login_failed_count is fetched.
    // This is perhaps overzealous but is the documented way of reloading the cache, as per the test method
    // 'test_check_user_preferences_loaded'.
    unset($user->preference);

    $resource = 'user:' . $user->id;
    $lockfactory = \core\lock\lock_config::get_lock_factory('core_failed_login_count_lock');

    // Get a new lock for the resource, waiting for it for a maximum of 10 seconds.
    if ($lock = $lockfactory->get_lock($resource, 10)) {
        try {
            $count = get_user_preferences('login_failed_count', 0, $user);
            $last = get_user_preferences('login_failed_last', 0, $user);
            $sincescuccess = get_user_preferences('login_failed_count_since_success', $count, $user);
            $sincescuccess = $sincescuccess + 1;
            set_user_preference('login_failed_count_since_success', $sincescuccess, $user);

            if (empty($CFG->lockoutthreshold)) {
                // No threshold means no lockout.
                // Always unlock here, there might be some race conditions or leftovers when switching threshold.
                login_unlock_account($user);
                $lock->release();
                return;
            }

            if (!empty($CFG->lockoutwindow) and time() - $last > $CFG->lockoutwindow) {
                $count = 0;
            }

            $count = $count + 1;

            set_user_preference('login_failed_count', $count, $user);
            set_user_preference('login_failed_last', time(), $user);

            if ($count >= $CFG->lockoutthreshold) {
                th_login_lock_account($user);
            }

            // Release locks when we're done.
            $lock->release();
        } catch (Exception $e) {
            // Always release the lock on a failure.
            $lock->release();
        }
    } else {
        // We did not get access to the resource in time, give up.
        throw new moodle_exception('locktimeout');
    }
}

function th_login_lock_account($user) {
    global $CFG, $DB;

    if ($user->mnethostid != $CFG->mnet_localhost_id) {
        return;
    }
    if (isguestuser($user)) {
        return;
    }

    if (get_user_preferences('login_lockout_ignored', 0, $user)) {
        // This user can not be locked out.
        return;
    }

    $alreadylockedout = get_user_preferences('login_lockout', 0, $user);

    set_user_preference('login_lockout', time(), $user);

    if ($alreadylockedout == 0) {
        $secret = random_string(15);
        set_user_preference('login_lockout_secret', $secret, $user);

        $oldforcelang = force_current_language($user->lang);

        $site = get_site();
        $supportuser = core_user::get_support_user();

        $sitename = $site->fullname;
        $hostname = $CFG->config_php_settings['wwwroot'];
        $http = '';
        $sql = "SELECT c.hostname, c.shortname, c.name
                FROM {company_users} cu
                JOIN {company} c ON c.id = cu.companyid
                WHERE cu.userid = :id";
        $params = ['id' => $user->id];
        if ($DB->record_exists_sql($sql, $params)) {
            $record = $DB->get_record_sql($sql, $params);
            
            if ((isset($record->hostname) && !empty($record->hostname))) {
                $hostname = $record->hostname;
                $http = 'http://';
                if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                    $http = 'https://';
                }
            }
            $sitename = $record->name;
        }

        $data = new stdClass();
        $data->firstname = $user->firstname;
        $data->lastname  = $user->lastname;
        $data->username  = $user->username;
        $data->sitename  = format_string($sitename);
        $data->link      = $http.$hostname.'/login/unlock_account.php?u='.$user->id.'&s='.$secret;
        $data->admin     = generate_email_signoff();

        $message = get_string('lockoutemailbody', 'admin', $data);
        $subject = get_string('lockoutemailsubject', 'admin', format_string($sitename));

        if ($message) {
            // Directly email rather than using the messaging system to ensure its not routed to a popup or jabber.
            email_to_user($user, $supportuser, $subject, $message);
        }

        force_current_language($oldforcelang);
    }
}


function set_th_option_cookie($option) {
    global $CFG;

    if (NO_MOODLE_COOKIES) {
        return;
    }

    $cookiename = 'option';
    // if(!isset($_COOKIE[$cookiename])) {
    $cookiesecure = is_moodle_cookie_secure();

    // Delete old cookie.
    setcookie($cookiename, '', time() - HOURSECS, $CFG->sessioncookiepath, $CFG->sessioncookiedomain, $cookiesecure, $CFG->cookiehttponly);

    if ($option !== '') {
        // Set username cookie for 60 days.
        setcookie($cookiename, $option, time() + (DAYSECS * 60), $CFG->sessioncookiepath, $CFG->sessioncookiedomain, $cookiesecure, $CFG->cookiehttponly);
    }
}

function get_th_option_cookie() {
    global $CFG;

    if (NO_MOODLE_COOKIES) {
        return '';
    }

    $cookiename = 'option';

        // print_object('$option');
        // print_object($_COOKIE[$cookiename]);
    if (empty($_COOKIE[$cookiename])) {
        return '';
    } else {
        $option = $_COOKIE[$cookiename];
        if ($option === '' or $option === null) {
            // backwards compatibility - we do not set these cookies any more
            $option = '';
        }
        return $option;
    }
}
/**
 * recaptcha type form element
 *
 * Contains HTML class for a recaptcha type element
 *
 * @package   core_form
 * @copyright 2008 Nicolas Connault <nicolasconnault@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../lib/pear/HTML/QuickForm/element.php');
require_once('../lib/pear/HTML/QuickForm/input.php');
require_once('../lib/form/templatable_form_element.php');
require_once('../lib/recaptchalib_v2.php');

/**
 * recaptcha type form element MoodleQuickForm_recaptcha
 *
 * HTML class for a recaptcha type element MoodleQuickForm_recaptcha
 *
 * @package   core_form
 * @category  form
 * @copyright 2008 Nicolas Connault <nicolasconnault@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class MoodleQuickForm_cloudflare extends HTML_QuickForm_input implements templatable {
    use templatable_form_element {
        export_for_template as export_for_template_base;
    }

    /** @var string html for help button, if empty then no help */
    var $_helpbutton='';

    /**
     * constructor
     *
     * @param string $elementName (optional) name of the recaptcha element
     * @param string $elementLabel (optional) label for recaptcha element
     * @param mixed $attributes (optional) Either a typical HTML attribute string
     *              or an associative array
     */
    public function __construct($elementName = null, $elementLabel = null, $attributes = null) {
        parent::__construct($elementName, $elementLabel, $attributes);
        $this->_type = 'cloudflare';
    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function MoodleQuickForm_cloudflare($elementName = null, $elementLabel = null, $attributes = null) {
        debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
        self::__construct($elementName, $elementLabel, $attributes);
    }

    /**
     * Returns the reCAPTCHA element in HTML
     *
     * @return string The HTML to render
     */
    public function toHtml() {
        global $CFG;
        // require_once($CFG->libdir . '/recaptchalib_v2.php');
        $sitekey = get_config('local_th_config_login', 'sitekey');

        return cloudflare_get_challenge_html(CLOUDFLARE_API_URL, $sitekey);
    }

    /**
     * get html for help button
     *
     * @return string html for help button
     */
    function getHelpButton(){
        return $this->_helpbutton;
    }

    /**
     * Checks recaptcha response with Google.
     *
     * @param string $responsestr
     * @return bool
     */
    public function verify($responsestr) {
        global $CFG;
        require_once($CFG->libdir . '/recaptchalib_v2.php');
        // $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $secretkey = get_config('local_th_config_login', 'secretkey');

        $response = cloudflare_check_response(CLOUDFLARE_VERIFY_URL, $secretkey,
                                           getremoteaddr(), $responsestr);
        if (!$response['isvalid']) {
            $attributes = $this->getAttributes();
            $attributes['error_message'] = $response['error'];
            $this->setAttributes($attributes);
            return $response['error'];
        }
        return true;
    }

    public function export_for_template(renderer_base $output) {
        $context = $this->export_for_template_base($output);
        $context['html'] = $this->toHtml();
        return $context;
    }

    /**
     * Get force LTR option.
     *
     * @return bool
     */
    public function get_force_ltr() {
        return true;
    }

}

class MoodleQuickForm_th_recaptcha extends HTML_QuickForm_input implements templatable {
    use templatable_form_element {
        export_for_template as export_for_template_base;
    }

    /** @var string html for help button, if empty then no help */
    var $_helpbutton='';

    /**
     * constructor
     *
     * @param string $elementName (optional) name of the recaptcha element
     * @param string $elementLabel (optional) label for recaptcha element
     * @param mixed $attributes (optional) Either a typical HTML attribute string
     *              or an associative array
     */
    public function __construct($elementName = null, $elementLabel = null, $attributes = null) {
        parent::__construct($elementName, $elementLabel, $attributes);
        $this->_type = 'th_recaptcha';
    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function MoodleQuickForm_recaptcha($elementName = null, $elementLabel = null, $attributes = null) {
        debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
        self::__construct($elementName, $elementLabel, $attributes);
    }

    /**
     * Returns the reCAPTCHA element in HTML
     *
     * @return string The HTML to render
     */
    public function toHtml() {
        global $CFG;
        require_once($CFG->libdir . '/recaptchalib_v2.php');

        return th_recaptcha_get_challenge_html(RECAPTCHA_API_URL, $CFG->recaptchapublickey);
    }

    /**
     * get html for help button
     *
     * @return string html for help button
     */
    function getHelpButton(){
        return $this->_helpbutton;
    }

    /**
     * Checks recaptcha response with Google.
     *
     * @param string $responsestr
     * @return bool
     */
    public function verify($responsestr) {
        global $CFG;
        require_once($CFG->libdir . '/recaptchalib_v2.php');

        $response = recaptcha_check_response(RECAPTCHA_VERIFY_URL, $CFG->recaptchaprivatekey,
                                           getremoteaddr(), $responsestr);
        if (!$response['isvalid']) {
            $attributes = $this->getAttributes();
            $attributes['error_message'] = $response['error'];
            $this->setAttributes($attributes);
            return $response['error'];
        }
        return true;
    }

    public function export_for_template(renderer_base $output) {
        $context = $this->export_for_template_base($output);
        $context['html'] = $this->toHtml();
        return $context;
    }

    /**
     * Get force LTR option.
     *
     * @return bool
     */
    public function get_force_ltr() {
        return true;
    }

}

function th_recaptcha_get_challenge_html($apiurl, $pubkey, $lang = null) {
    global $CFG, $PAGE;

    // To use reCAPTCHA you must have an API key.
    if ($pubkey === null || $pubkey === '') {
        return get_string('getrecaptchaapi', 'auth');
    }

    $jscode = "
        var recaptchacallback = function() {
            grecaptcha.render('th_recaptcha_element', {
              'sitekey' : '$pubkey',
              'callback': function () {
                    console.log('callback');
                    if (document.getElementById('id_submitbutton')) {
                        document.getElementById('id_submitbutton').disabled = false;
                        // document.getElementById('id_submitbutton').focus();
                    }
                    if (document.getElementById('loginbtn')) {
                        document.getElementById('loginbtn').disabled = false;
                        // document.getElementById('loginbtn').focus();
                    }
                },
            });
        }";

    $lang = recaptcha_lang($lang);
    $apicode = "\n<script type=\"text/javascript\" ";
    $apicode .= "src=\"$apiurl?onload=recaptchacallback&render=explicit&hl=$lang\" async defer>";
    $apicode .= "</script>\n";

    $return = html_writer::script($jscode, '');
    $return .= html_writer::div('', 'recaptcha_element', array('id' => 'th_recaptcha_element'));
    $return .= $apicode;

    return $return;
}


function get_th_login_dir() {

    $dir = trim(str_replace(str_split(':\*?"<>|/'),
            '',
            get_config('local_th_config_login', 'directory')),
            "/\\");

    return $dir;
}

function cloudflare_get_challenge_html($apiurl, $pubkey, $lang = null) {
    global $CFG, $PAGE;

    // To use reCAPTCHA you must have an API key.
    if ($pubkey === null || $pubkey === '') {
        return get_string('getrecaptchaapi', 'auth');
    }

    $lang = recaptcha_lang($lang);
    $jscode = "
        var onloadTurnstileCallback = function() {
            turnstile.render('#cloudflare_element', {
                'sitekey' : '$pubkey',
                'language': '$lang',
                'callback': function () {
                    console.log('callback');
                    if (document.getElementById('id_submitbutton')) {
                        document.getElementById('id_submitbutton').disabled = false;
                        // document.getElementById('id_submitbutton').focus();
                    }
                    if (document.getElementById('loginbtn')) {
                        document.getElementById('loginbtn').disabled = false;
                        // document.getElementById('loginbtn').focus();
                    }
                },
            });
        }";

    $apicode = "\n<script type=\"text/javascript\" ";
    $apicode .= "src=\"$apiurl?render=explicit&onload=onloadTurnstileCallback\" async defer>";
    $apicode .= "</script>\n";

    $return = html_writer::script($jscode, '');
    $return .= html_writer::div('', 'cloudflare_element', array('id' => 'cloudflare_element'));
    $return .= $apicode;

    return $return;
}


function email_get_challenge_html() {

    $return = '<input type="text" name="email" id="email" placeholder="Email" class="form-control" style="float: left" >
            <button type="button" class="btn btn-primary" id="sendotp">'.get_string('receive_otp', 'local_th_config_login').'</button>';
    return $return;
}

function th_validate_login_email($userid, $otp): bool {
    global $CFG;
    if ($otp === false) {
        // The authenticate_user_login() is a core function was extended to validate captcha.
        // For existing uses other than the login form it does not need to validate the captcha.
        // Example: login/change_password_form.php or login/token.php.
        return true;
    }

    // require_once($CFG->libdir . '/recaptchalib_v2.php');
    $response = email_check_otp($userid, $otp);
    return $response['isvalid'];
}

function email_check_otp($userid, $otp) {
    global $DB, $USER;
    // require_once($CFG->libdir.'/filelib.php');

    // Check response - isvalid boolean, error string.
    $checkresponse = array('isvalid' => false, 'error' => 'check-not-started');

    // Discard spam submissions.
    if ($userid === null || strlen($otp) === 0) {
        $checkresponse['isvalid'] = false;
        $checkresponse['error'] = 'incorrect-captcha-sol';
        return $checkresponse;
    }

    $sql = "SELECT u.*, upr.otp, upr.timerequested, upr.id as otpid, upr.code
                  FROM {user} u
                  JOIN {local_th_user_login} upr ON upr.userid = u.id
                 WHERE upr.otp = ? and upr.typesend=0 and upr.userid=$userid";
    $user = $DB->get_record_sql($sql, array($otp));
    $config_login = get_config('local_th_config_login');
    $otplogintime =  $config_login->timeexpiry ? $config_login->timeexpiry : 1800;
    if (empty($user) or ($user->timerequested < (time() - $otplogintime - DAYSECS))) {
        $checkresponse['isvalid'] = false;
        $checkresponse['error'] = 'check-failed';
    } else if ($user->timerequested < (time() - $otplogintime)){
        // het han
         $checkresponse['isvalid'] = false;
         $checkresponse['error'] = 'check-failed';
    } else {
        if (password_verify($user->timerequested, $user->code)) {
            $checkresponse['isvalid'] = true;
            $checkresponse['error'] = '';
        }
    }
    return $checkresponse;
}

function th_validate_login_captcha($captcha): bool {
    global $CFG;

    if ($captcha === false) {
        // The authenticate_user_login() is a core function was extended to validate captcha.
        // For existing uses other than the login form it does not need to validate the captcha.
        // Example: login/change_password_form.php or login/token.php.
        return true;
    }

    require_once($CFG->libdir . '/recaptchalib_v2.php');
    $response = recaptcha_check_response(RECAPTCHA_VERIFY_URL, $CFG->recaptchaprivatekey, getremoteaddr(), $captcha);
    return $response['isvalid'];
}

function th_validate_login_cloudflare($captcha): bool {
    global $CFG;

    if ($captcha === false) {
        // The authenticate_user_login() is a core function was extended to validate captcha.
        // For existing uses other than the login form it does not need to validate the captcha.
        // Example: login/change_password_form.php or login/token.php.
        return true;
    }

    $secretkey = get_config('local_th_config_login', 'secretkey');
    $response = cloudflare_check_response(CLOUDFLARE_VERIFY_URL, $secretkey, getremoteaddr(), $captcha);
    return $response['isvalid'];
}

function cloudflare_check_response($verifyurl, $privkey, $remoteip, $response) {
    global $CFG;
    require_once($CFG->libdir.'/filelib.php');

    // Check response - isvalid boolean, error string.
    $checkresponse = array('isvalid' => false, 'error' => 'check-not-started');

    // To use reCAPTCHA you must have an API key.
    if ($privkey === null || $privkey === '') {
        $checkresponse['isvalid'] = false;
        $checkresponse['error'] = 'no-apikey';
        return $checkresponse;
    }

    // For security reasons, you must pass the remote ip to reCAPTCHA.
    if ($remoteip === null || $remoteip === '') {
        $checkresponse['isvalid'] = false;
        $checkresponse['error'] = 'no-remoteip';
        return $checkresponse;
    }

    // Discard spam submissions.
    if ($response === null || strlen($response) === 0) {
        $checkresponse['isvalid'] = false;
        $checkresponse['error'] = 'incorrect-captcha-sol';
        return $checkresponse;
    }

    $params = array('secret' => $privkey, 'remoteip' => $remoteip, 'response' => $response);
    $curl = new curl();
    $curlresponse = $curl->post($verifyurl, $params);

    if ($curl->get_errno() === 0) {
        $curldata = json_decode($curlresponse);

        if (isset($curldata->success) && $curldata->success === true) {
            $checkresponse['isvalid'] = true;
            $checkresponse['error'] = '';
        } else {
            $checkresponse['isvalid'] = false;
            $checkresponse['error'] = $curldata->{'error-codes'};
        }
    } else {
        $checkresponse['isvalid'] = false;
        $checkresponse['error'] = 'check-failed';
    }
    return $checkresponse;
}

function th_authenticate_user_login(
    $username,
    $password,
    $ignorelockout = false,
    &$failurereason = null,
    $logintoken = false,
    $loginrecaptcha = false,
    $option=''
) {
    global $CFG, $DB, $PAGE;
    require_once("$CFG->libdir/authlib.php");

    if ($user = get_complete_user_data('username', $username, $CFG->mnet_localhost_id)) {
        // we have found the user

    } else if (!empty($CFG->authloginviaemail)) {
        if ($email = clean_param($username, PARAM_EMAIL)) {
            $select = "mnethostid = :mnethostid AND LOWER(email) = LOWER(:email) AND deleted = 0";
            $params = array('mnethostid' => $CFG->mnet_localhost_id, 'email' => $email);
            $users = $DB->get_records_select('user', $select, $params, 'id', 'id', 0, 2);
            if (count($users) === 1) {
                // Use email for login only if unique.
                $user = reset($users);
                $user = get_complete_user_data('id', $user->id);
                $username = $user->username;
            }
            unset($users);
        }
    }

    if (!\core\session\manager::validate_login_token($logintoken)) {
        $failurereason = AUTH_LOGIN_FAILED;

        // Trigger login failed event (specifying the ID of the found user, if available).
        \core\event\user_login_failed::create([
            'userid' => ($user->id ?? 0),
            'other' => [
                'username' => $username,
                'reason' => $failurereason,
            ],
        ])->trigger();

        error_log('[client '.getremoteaddr()."]  $CFG->wwwroot  Invalid Login Token:  $username  ".$_SERVER['HTTP_USER_AGENT']);
        return false;
    }
    if ($option == 'cloudflare') {
        if (!empty($config_login->sitekey) && !empty($config_login->secretkey) && !th_validate_login_cloudflare($loginrecaptcha)) {
            $failurereason = 6;
            // Trigger login failed event (specifying the ID of the found user, if available).
            \core\event\user_login_failed::create([
                'userid' => ($user->id ?? 0),
                'other' => [
                    'username' => $username,
                    'reason' => $failurereason,
                ],
            ])->trigger();
            return false;
        }
    } else if ($option == 'recaptcha') {
        // Login reCaptcha.
        if (!empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey) && !th_validate_login_captcha($loginrecaptcha)) {
            $failurereason = 6;
            // Trigger login failed event (specifying the ID of the found user, if available).
            \core\event\user_login_failed::create([
                'userid' => ($user->id ?? 0),
                'other' => [
                    'username' => $username,
                    'reason' => $failurereason,
                ],
            ])->trigger();
            return false;
        }
    } else if ($option == 'email') {
         if (!th_validate_login_email($user->id, $loginrecaptcha)) {
            $failurereason = 6;
            // Trigger login failed event (specifying the ID of the found user, if available).
            \core\event\user_login_failed::create([
                'userid' => ($user->id ?? 0),
                'other' => [
                    'username' => $username,
                    'reason' => $failurereason,
                ],
            ])->trigger();
            return false;
        }
    } else {
        // return false;
    }
    $authsenabled = get_enabled_auth_plugins();

    if ($user) {
        // Use manual if auth not set.
        $auth = empty($user->auth) ? 'manual' : $user->auth;

        if (in_array($user->auth, $authsenabled)) {
            $authplugin = get_auth_plugin($user->auth);
            $authplugin->pre_user_login_hook($user);
        }

        if (!empty($user->suspended)) {
            $failurereason = AUTH_LOGIN_SUSPENDED;

            // Trigger login failed event.
            $event = \core\event\user_login_failed::create(array('userid' => $user->id,
                    'other' => array('username' => $username, 'reason' => $failurereason)));
            $event->trigger();
            error_log('[client '.getremoteaddr()."]  $CFG->wwwroot  Suspended Login:  $username  ".$_SERVER['HTTP_USER_AGENT']);
            return false;
        }
        if ($auth=='nologin' or !is_enabled_auth($auth)) {
            // Legacy way to suspend user.
            $failurereason = AUTH_LOGIN_SUSPENDED;

            // Trigger login failed event.
            $event = \core\event\user_login_failed::create(array('userid' => $user->id,
                    'other' => array('username' => $username, 'reason' => $failurereason)));
            $event->trigger();
            error_log('[client '.getremoteaddr()."]  $CFG->wwwroot  Disabled Login:  $username  ".$_SERVER['HTTP_USER_AGENT']);
            return false;
        }
        $auths = array($auth);

    } else {
        // Check if there's a deleted record (cheaply), this should not happen because we mangle usernames in delete_user().
        if ($DB->get_field('user', 'id', array('username' => $username, 'mnethostid' => $CFG->mnet_localhost_id,  'deleted' => 1))) {
            $failurereason = AUTH_LOGIN_NOUSER;

            // Trigger login failed event.
            $event = \core\event\user_login_failed::create(array('other' => array('username' => $username,
                    'reason' => $failurereason)));
            $event->trigger();
            error_log('[client '.getremoteaddr()."]  $CFG->wwwroot  Deleted Login:  $username  ".$_SERVER['HTTP_USER_AGENT']);
            return false;
        }

        // User does not exist.
        $auths = $authsenabled;
        $user = new stdClass();
        $user->id = 0;
    }

    if ($ignorelockout) {
        // Some other mechanism protects against brute force password guessing, for example login form might include reCAPTCHA
        // or this function is called from a SSO script.
    } else if ($user->id) {
        // Verify login lockout after other ways that may prevent user login.
        if (login_is_lockedout($user)) {
            $failurereason = AUTH_LOGIN_LOCKOUT;

            // Trigger login failed event.
            $event = \core\event\user_login_failed::create(array('userid' => $user->id,
                    'other' => array('username' => $username, 'reason' => $failurereason)));
            $event->trigger();

            error_log('[client '.getremoteaddr()."]  $CFG->wwwroot  Login lockout:  $username  ".$_SERVER['HTTP_USER_AGENT']);
            // $SESSION->loginerrormsg = get_string('accountlocked', 'admin');

            return false;
        }
    } else {
        // We can not lockout non-existing accounts.
    }

    foreach ($auths as $auth) {
        $authplugin = get_auth_plugin($auth);

        // On auth fail fall through to the next plugin.
        if (!$authplugin->user_login($username, $password)) {
            continue;
        }

        // Before performing login actions, check if user still passes password policy, if admin setting is enabled.
        if (!empty($CFG->passwordpolicycheckonlogin)) {
            $errmsg = '';
            $passed = check_password_policy($password, $errmsg, $user);
            if (!$passed) {
                // First trigger event for failure.
                $failedevent = \core\event\user_password_policy_failed::create_from_user($user);
                $failedevent->trigger();

                // If able to change password, set flag and move on.
                if ($authplugin->can_change_password()) {
                    // Check if we are on internal change password page, or service is external, don't show notification.
                    $internalchangeurl = new moodle_url('/login/change_password.php');
                    if (!($PAGE->has_set_url() && $internalchangeurl->compare($PAGE->url)) && $authplugin->is_internal()) {
                        \core\notification::error(get_string('passwordpolicynomatch', '', $errmsg));
                    }
                    set_user_preference('auth_forcepasswordchange', 1, $user);
                } else if ($authplugin->can_reset_password()) {
                    // Else force a reset if possible.
                    \core\notification::error(get_string('forcepasswordresetnotice', '', $errmsg));
                    redirect(new moodle_url('/' . get_th_login_dir() . '/forgot_password.php'));
                } else {
                    $notifymsg = get_string('forcepasswordresetfailurenotice', '', $errmsg);
                    // If support page is set, add link for help.
                    if (!empty($CFG->supportpage)) {
                        $link = \html_writer::link($CFG->supportpage, $CFG->supportpage);
                        $link = \html_writer::tag('p', $link);
                        $notifymsg .= $link;
                    }

                    // If no change or reset is possible, add a notification for user.
                    \core\notification::error($notifymsg);
                }
            }
        }

        // Successful authentication.
        if ($user->id) {
            // User already exists in database.
            if (empty($user->auth)) {
                // For some reason auth isn't set yet.
                $DB->set_field('user', 'auth', $auth, array('id' => $user->id));
                $user->auth = $auth;
            }

            // If the existing hash is using an out-of-date algorithm (or the legacy md5 algorithm), then we should update to
            // the current hash algorithm while we have access to the user's password.
            update_internal_user_password($user, $password);

            if ($authplugin->is_synchronised_with_external()) {
                // Update user record from external DB.
                $user = update_user_record_by_id($user->id);
            }
        } else {
            // The user is authenticated but user creation may be disabled.
            if (!empty($CFG->authpreventaccountcreation)) {
                $failurereason = AUTH_LOGIN_UNAUTHORISED;

                // Trigger login failed event.
                $event = \core\event\user_login_failed::create(array('other' => array('username' => $username,
                        'reason' => $failurereason)));
                $event->trigger();

                error_log('[client '.getremoteaddr()."]  $CFG->wwwroot  Unknown user, can not create new accounts:  $username  ".
                        $_SERVER['HTTP_USER_AGENT']);
                return false;
            } else {
                $user = create_user_record($username, $password, $auth);
            }
        }

        $authplugin->sync_roles($user);

        foreach ($authsenabled as $hau) {
            $hauth = get_auth_plugin($hau);
            $hauth->user_authenticated_hook($user, $username, $password);
        }

        if (empty($user->id)) {
            $failurereason = AUTH_LOGIN_NOUSER;
            // Trigger login failed event.
            $event = \core\event\user_login_failed::create(array('other' => array('username' => $username,
                    'reason' => $failurereason)));
            $event->trigger();
            return false;
        }

        if (!empty($user->suspended)) {
            // Just in case some auth plugin suspended account.
            $failurereason = AUTH_LOGIN_SUSPENDED;
            // Trigger login failed event.
            $event = \core\event\user_login_failed::create(array('userid' => $user->id,
                    'other' => array('username' => $username, 'reason' => $failurereason)));
            $event->trigger();
            error_log('[client '.getremoteaddr()."]  $CFG->wwwroot  Suspended Login:  $username  ".$_SERVER['HTTP_USER_AGENT']);
            return false;
        }

        login_attempt_valid($user);
        $failurereason = AUTH_LOGIN_OK;
        $DB->delete_records('local_th_user_login', array('userid' => $user->id, 'typesend' => 0));
        return $user;
    }

    // Failed if all the plugins have failed.
    if (debugging('', DEBUG_ALL)) {
        error_log('[client '.getremoteaddr()."]  $CFG->wwwroot  Failed Login:  $username  ".$_SERVER['HTTP_USER_AGENT']);
    }

    if ($user->id) {
        th_login_attempt_failed($user);
        $failurereason = AUTH_LOGIN_FAILED;
        // Trigger login failed event.
        $event = \core\event\user_login_failed::create(array('userid' => $user->id,
                'other' => array('username' => $username, 'reason' => $failurereason)));
        $event->trigger();
    } else {
        $failurereason = AUTH_LOGIN_NOUSER;
        // Trigger login failed event.
        $event = \core\event\user_login_failed::create(array('other' => array('username' => $username,
                'reason' => $failurereason)));
        $event->trigger();
    }

    return false;
}
function th_verify($responsestr) {
    global $CFG;
    require_once($CFG->libdir . '/recaptchalib_v2.php');

    $response = recaptcha_check_response(RECAPTCHA_VERIFY_URL, $CFG->recaptchaprivatekey,
                                       getremoteaddr(), $responsestr);
    if (!$response['isvalid']) {
        return $response['error'];
    }
    return true;
}
/**
 *  Processes a user's request to set a new password in the event they forgot the old one.
 *  If no user identifier has been supplied, it displays a form where they can submit their identifier.
 *  Where they have supplied identifier, the function will check their status, and send email as appropriate.
 */
function th_core_login_process_password_reset_request() {
    global $OUTPUT, $PAGE;
    $mform = new th_login_forgot_password_form();

    if ($mform->is_cancelled()) {
        redirect(get_login_url());

    } else if ($data = $mform->get_data()) {
        
        $username = $email = '';
        if (!empty($data->username)) {
            $username = $data->username;
        } else {
            $email = $data->email;
        }
        // print_object();exit;
        list($status, $notice, $url) = th_core_login_process_password_reset($username, $email);

        // Plugins can perform post forgot password actions once data has been validated.
        core_login_post_forgot_password_requests($data);

        // Any email has now been sent.
        // Next display results to requesting user if settings permit.
        echo $OUTPUT->header();
        notice($notice, $url);
        die; // Never reached.
    }

    // DISPLAY FORM.

    echo $OUTPUT->header();
    echo $OUTPUT->box(get_string('passwordforgotteninstructions2'), 'generalbox boxwidthnormal boxaligncenter');
    $mform->display();
    // exit;

    echo $OUTPUT->footer();
}

function th_core_login_process_password_reset($username, $email) {
    global $CFG, $DB;

    if (empty($username) && empty($email)) {
        print_error('cannotmailconfirm');
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
                print_error('cannotmailconfirm');
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
                $sendresult = th_send_password_change_confirmation_email($user, $resetrecord);
                if ($sendresult) {
                    $pwresetstatus = PWRESET_STATUS_TOKENSENT;
                } else {
                    print_error('cannotmailconfirm');
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

function th_send_password_change_confirmation_email($user, $resetrecord) {
    global $CFG, $DB;

    $site = get_site();
    $supportuser = core_user::get_support_user();
    $pwresetmins = isset($CFG->pwresettime) ? floor($CFG->pwresettime / MINSECS) : 30;
    $hostname = $CFG->config_php_settings['wwwroot'];
    $sitename = $site->fullname;
    // print_object($hostname);
    $http = '';
    $sql = "SELECT c.hostname, c.shortname, c.name
            FROM {company_users} cu
            JOIN {company} c ON c.id = cu.companyid
            WHERE cu.userid = :id";
    $params = ['id' => $user->id];
    if ($DB->record_exists_sql($sql, $params)) {
        $record = $DB->get_record_sql($sql, $params);
        
        if ((isset($record->hostname) && !empty($record->hostname))) {
            $hostname = $record->hostname;
            $http = 'http://';
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                $http = 'https://';
            }
        }
        $sitename = $record->name;
    }
    // print_object($hostname);exit;

    $data = new stdClass();
    $data->firstname = $user->firstname;
    $data->lastname  = $user->lastname;
    $data->username  = $user->username;
    $data->sitename  = format_string($sitename);
    $data->link      = $http . $hostname . '/' . get_th_login_dir() .'/forgot_password.php?token='. $resetrecord->token;
    $data->admin     = generate_email_signoff();
    $data->resetminutes = $pwresetmins;

    $message = get_string('emailresetconfirmation', '', $data);
    $subject = get_string('emailresetconfirmationsubject', '', format_string($sitename));

    // Directly email rather than using the messaging system to ensure its not routed to a popup or jabber.
    return email_to_user($user, $supportuser, $subject, $message);

}


/**
 * Validates the forgot password form data.
 *
 * This is used by the forgot_password_form and by the core_auth_request_password_rest WS.
 * @param  array $data array containing the data to be validated (email and username)
 * @return array array of errors compatible with mform
 * @since  Moodle 3.4
 */
function th_core_login_validate_forgot_password_data($data) {
    global $CFG, $DB;

    $errors = array();

    if ((!empty($data['username']) and !empty($data['email'])) or (empty($data['username']) and empty($data['email']))) {
        $errors['username'] = get_string('usernameoremail');
        $errors['email']    = get_string('usernameoremail');

    } else if (!empty($data['email'])) {
        if (!validate_email($data['email'])) {
            $errors['email'] = get_string('invalidemail');

        } else {
            try {
                $user = get_complete_user_data('email', $data['email'], null, true);
                if (empty($user->confirmed)) {
                    send_confirmation_email($user);
                    if (empty($CFG->protectusernames)) {
                        $errors['email'] = get_string('confirmednot');
                    }
                }
            } catch (dml_missing_record_exception $missingexception) {
                // User not found. Show error when $CFG->protectusernames is turned off.
                if (empty($CFG->protectusernames)) {
                    $errors['email'] = get_string('emailnotfound');
                }
            } catch (dml_multiple_records_exception $multipleexception) {
                // Multiple records found. Ask the user to enter a username instead.
                if (empty($CFG->protectusernames)) {
                    $errors['email'] = get_string('forgottenduplicate');
                }
            }
        }

    } else {
        if ($user = get_complete_user_data('username', $data['username'])) {
            if (empty($user->confirmed)) {
                send_confirmation_email($user);
                if (empty($CFG->protectusernames)) {
                    $errors['username'] = get_string('confirmednot');
                }
            }
        }
        if (!$user and empty($CFG->protectusernames)) {
            $errors['username'] = get_string('usernamenotfound');
        }
    }

    return $errors;
}

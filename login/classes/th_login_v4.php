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

/**
 * Login renderable.
 *
 * @package    core_auth
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_auth\output;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once('lib.php');

use context_system;
use help_icon;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

class th_login_v4 implements renderable, templatable {

    /** @var bool Whether to auto focus the form fields. */
    public $autofocusform;
    /** @var bool Whether we can login as guest. */
    public $canloginasguest;
    /** @var bool Whether we can login by e-mail. */
    public $canloginbyemail;
    /** @var bool Whether we can sign-up. */
    public $cansignup;
    /** @var help_icon The cookies help icon. */
    public $cookieshelpicon;
    /** @var string The error message, if any. */
    public $error;
    /** @var moodle_url Forgot password URL. */
    public $forgotpasswordurl;
    /** @var array Additional identify providers, contains the keys 'url', 'name' and 'icon'. */
    public $identityproviders;
    /** @var string Login instructions, if any. */
    public $instructions;
    /** @var moodle_url The form action login URL. */
    public $loginurl;
    /** @var moodle_url The sign-up URL. */
    public $signupurl;
    /** @var string The user name to pre-fill the form with. */
    public $username;
    /** @var string The language selector menu. */
    public $languagemenu;
    /** @var string The csrf token to limit login to requests that come from the login form. */
    public $logintoken;
    /** @var string Maintenance message, if Maintenance is enabled. */
    public $maintenance;

    public $recaptcha;
    public $cloudflare;
    public $email;
    public $sms;
    public $usesecret = 0;
    public $secret = [];
    public $countdown = 0;
    public $ajaxurl;
    public $check_otp_url;

    public $cloudflare_default = 0;
    public $recaptcha_default = 0;
    public $email_default = 0;

    /**
     * Constructor.
     *
     * @param array $authsequence The enabled sequence of authentication plugins.
     * @param string $username The username to display.
     */
    public function __construct(array $authsequence, $option, $username = '') {
        global $CFG, $OUTPUT, $PAGE, $SITE;

        $this->username = $username;

        $languagedata = new \core\output\language_menu($PAGE);

        $this->languagemenu = $languagedata->export_for_action_menu($OUTPUT);
        $this->canloginasguest = $CFG->guestloginbutton && !isguestuser();
        $this->canloginbyemail = !empty($CFG->authloginviaemail);
        $this->cansignup = $CFG->registerauth == 'email' || !empty($CFG->registerauth);
        if ($CFG->rememberusername == 0) {
            $this->cookieshelpicon = new help_icon('cookiesenabledonlysession', 'core');
        } else {
            $this->cookieshelpicon = new help_icon('cookiesenabled', 'core');
        }

        $this->autofocusform = !empty($CFG->loginpageautofocus);

        $this->forgotpasswordurl = new moodle_url('/' . get_th_login_dir() . '/forgot_password.php');
        $this->loginurl = new moodle_url('/' . get_th_login_dir() . '/index.php');
        $this->signupurl = new moodle_url('/login/signup.php');

        // Authentication instructions.
        $this->instructions = $CFG->auth_instructions;
        if (is_enabled_auth('none')) {
            $this->instructions = get_string('loginstepsnone');
        } else if ($CFG->registerauth == 'email' && empty($this->instructions)) {
            $this->instructions = get_string('loginsteps', 'core', 'signup.php');
        }

        if ($CFG->maintenance_enabled == true) {
            if (!empty($CFG->maintenance_message)) {
                $this->maintenance = $CFG->maintenance_message;
            } else {
                $this->maintenance = get_string('sitemaintenance', 'admin');
            }
        }

        // Identity providers.
        $this->identityproviders = \auth_plugin_base::get_identity_providers($authsequence);
        $this->logintoken = \core\session\manager::get_login_token();
        $this->sitename = format_string($SITE->fullname, true,
                ['context' => \context_course::instance(SITEID), "escape" => false]);

        $url = $this->get_logo_url();
        if ($url) {
            $url = $url->out(false);
        }
        $this->logourl = $url;
        $this->errorformatted = $this->error_text($this->error);
        $this->ajaxurl = $CFG->wwwroot . '/' . get_th_login_dir() . '/' . 'send_otp.php';
        $this->check_otp_url = $CFG->wwwroot . '/' . get_th_login_dir() . '/' . 'check_otp.php';

        $config_login = get_config('local_th_config_login');
        $this->expire_option  = $config_login->expire_option;
        $this->countdown = $config_login->timeexpiry ? $config_login->timeexpiry : 1800;
        if ($config_login->selectsecret != '') {

            $selectsecret = explode(",", $config_login->selectsecret);
            $secret = [];
            if ($option == 'cloudflare' && !empty($config_login->sitekey) && !empty($config_login->secretkey)) {
                $this->cloudflare_default = 1;
            } else if ($option == 'recaptcha' && !empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey)) {
                $this->recaptcha_default = 1;
            } else if ($option == 'email') {
                $this->email_default = 1;
            }

            foreach ($selectsecret as $key => $value) {

                if ($value == 'cloudflare') {
                    if (!empty($config_login->sitekey) && !empty($config_login->secretkey)) {
                        require_once('lib.php');
                        $secret[] = ['value' => $value, 'name' => ucfirst($value)];
                        $this->cloudflare = cloudflare_get_challenge_html(CLOUDFLARE_API_URL, $config_login->sitekey);
                    }
                }

                if ($value == 'recaptcha') {
                    if (!empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey)) {
                        require_once($CFG->libdir . '/recaptchalib_v2.php');
                        $secret[] = ['value' => $value, 'name' => 'Google reCaptcha'];
                        $this->recaptcha = th_recaptcha_get_challenge_html(RECAPTCHA_API_URL, $CFG->recaptchapublickey);
                    }
                }

                if ($value == 'email') {
                    $secret[] = ['value' => $value, 'name' => ucfirst($value)];
                    $this->email = email_get_challenge_html();
                }
            }
            if (count($secret) <= 1) {
                $this->usesecret = 0;
            } else {
                $this->usesecret = 1;
            }
            $this->secret = $secret;
        }
    }

    public function get_logo_url($maxwidth = null, $maxheight = 200) {
        global $CFG;
        $logo = get_config('core_admin', 'logo');
        if (empty($logo)) {
            return false;
        }

        // 200px high is the default image size which should be displayed at 100px in the page to account for retina displays.
        // It's not worth the overhead of detecting and serving 2 different images based on the device.

        // Hide the requested size in the file path.
        $filepath = ((int) $maxwidth . 'x' . (int) $maxheight) . '/';

        // Use $CFG->themerev to prevent browser caching when the file changes.
        return moodle_url::make_pluginfile_url(context_system::instance()->id, 'core_admin', 'logo', $filepath,
            theme_get_revision(), $logo);
    }

    public function error_text($message) {
        if (empty($message)) {
            return '';
        }
        // $message = $this->pix_icon('i/warning', get_string('error'), '', array('class' => 'icon icon-pre', 'title'=>'')) . $message;
        return html_writer::tag('span', $message, array('class' => 'error'));
    }

    /**
     * Set the error message.
     *
     * @param string $error The error message.
     */
    public function set_error($error) {
        $this->error = $error;
    }

    public function export_for_template(renderer_base $output) {

        $identityproviders = \auth_plugin_base::prepare_identity_providers_for_output($this->identityproviders, $output);

        $data = new stdClass();
        $data->autofocusform = $this->autofocusform;
        $data->canloginasguest = $this->canloginasguest;
        $data->canloginbyemail = $this->canloginbyemail;
        $data->cansignup = $this->cansignup;
        $data->cookieshelpicon = $this->cookieshelpicon->export_for_template($output);
        $data->error = $this->error;
        $data->forgotpasswordurl = $this->forgotpasswordurl->out(false);
        $data->hasidentityproviders = !empty($this->identityproviders);
        $data->hasinstructions = !empty($this->instructions) || $this->cansignup;
        $data->identityproviders = $identityproviders;
        list($data->instructions, $data->instructionsformat) = external_format_text($this->instructions, FORMAT_MOODLE,
            context_system::instance()->id);
        $data->loginurl = $this->loginurl->out(false);
        $data->signupurl = $this->signupurl->out(false);
        $data->username = $this->username;
        $data->logintoken = $this->logintoken;
        $data->maintenance = format_text($this->maintenance, FORMAT_MOODLE);
        $data->languagemenu = $this->languagemenu;
        $data->recaptcha = $this->recaptcha;
        $data->sitename = $this->sitename;
        $data->logourl = $this->logourl;
        $data->errorformatted = $this->errorformatted;
        $data->secret = $this->secret;
        $data->recaptcha = $this->recaptcha;
        $data->cloudflare = $this->cloudflare;
        $data->email = $this->email;
        $data->usesecret = $this->usesecret;
        $data->countdown = $this->countdown;
        $data->ajaxurl = $this->ajaxurl;
        $data->expire_option = $this->expire_option;

        $data->cloudflare_default =  $this->cloudflare_default;
        $data->recaptcha_default =  $this->recaptcha_default;
        $data->email_default =  $this->email_default;
        $data->check_otp_url =  $this->check_otp_url;

        // print_object($data);
    
        return $data;
    }
}


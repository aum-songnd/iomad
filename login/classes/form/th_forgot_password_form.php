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
 * Forgot password page.
 *
 * @package    core
 * @subpackage auth
 * @copyright  2006 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once('lib.php');

/**
 * Reset forgotten password form definition.
 *
 * @package    core
 * @subpackage auth
 * @copyright  2006 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class th_login_forgot_password_form extends moodleform {

    /**
     * Define the forgot password form.
     */
    function definition() {
        global $USER, $CFG, $PAGE;

        $mform    = $this->_form;
        $mform->setDisableShortforms(true);
        $this->_form->disable_form_change_checker();
        // $mform->setAttributes(array('id' => 'th_login_forgot_password'));
        $mform->addElement('static', 'th_alert', '', '<div class="col-md-3" style="float: left"> </div>
                    <div id="th_alert" class="col-md-9" style="max-width: 500px;"></div>');
        // Hook for plugins to extend form definition.
        core_login_extend_forgot_password_form($mform);
        $radioarray = array();
        $radioarray[] = $mform->createElement('radio', 'show_option', '', get_string('username'), '0');
        $radioarray[] = $mform->createElement('radio', 'show_option', '', get_string('email'), '1');
        $mform->addGroup($radioarray, 'radioar', '', array(''), false);
        $mform->setDefault('show_option', '0');

        // $mform->addElement('header', 'searchbyusername', get_string('searchbyusername'), '');

        $purpose = user_edit_map_field_purpose($USER->id, 'username');
        $mform->addElement('text', 'username', get_string('username'), 'size="20"' . $purpose);
        $mform->setType('username', PARAM_RAW);

        $submitlabel = get_string('search');
        // $mform->addElement('submit', 'submitbuttonusername', $submitlabel);

        // $mform->addElement('header', 'searchbyemail', get_string('searchbyemail'), '');

        $purpose = user_edit_map_field_purpose($USER->id, 'email');
        $mform->addElement('text', 'email', get_string('email'), 'maxlength="100" size="30"' . $purpose);
        $mform->setType('email', PARAM_RAW_TRIMMED);

         // Register a custom form element.core_login_process_password_reset_request
        MoodleQuickForm::registerElementType(
            // The custom element is named `course_competency_rule`.
            // This is the element name used in the `addElement()` function.
            'cloudflare',

            // This is where it's definition is defined.
            // This does not currently support class auto-loading.
            "$CFG->dirroot/" . get_th_login_dir() . "/lib.php",

            // The class name of the element.
            'MoodleQuickForm_cloudflare'
        );

        MoodleQuickForm::registerElementType(
            // The custom element is named `course_competency_rule`.
            // This is the element name used in the `addElement()` function.
            'th_recaptcha',

            // This is where it's definition is defined.
            // This does not currently support class auto-loading.
            "$CFG->dirroot/" . get_th_login_dir() . "/lib.php",

            // The class name of the element.
            'MoodleQuickForm_th_recaptcha'
        );

        //  // Register a custom form element.core_login_process_password_reset_request
        // MoodleQuickForm::registerElementType(
        //     // The custom element is named `course_competency_rule`.
        //     // This is the element name used in the `addElement()` function.
        //     'security',

        //     // This is where it's definition is defined.
        //     // This does not currently support class auto-loading.
        //     "$CFG->dirroot/" . get_th_login_dir() . "/lib.php",

        //     // The class name of the element.
        //     'MoodleQuickForm_security'
        // );
        // $mform->addElement('security', 'security', get_string('security_question', 'auth'));
        $config_login = get_config('local_th_config_login');
        $selectsecret = explode(",", $config_login->selectsecret);
        $cloudflare_default = 0;
        $recaptcha_default = 0;
        $email_default = 0;
        $default = get_th_option_cookie();
        $secret = [];

        // print_object($default);
        
        if ($default == 'cloudflare' && !empty($config_login->sitekey) && !empty($config_login->secretkey)) {
             // $cloudflare_default = 0;
        } else if($default == 'recaptcha' && !empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey)) {

        } else if ($default == 'email') {

        } else {
            $default = $selectsecret[0];
        }

        if ($config_login->selectsecret != '') {
            $selectsecret = explode(",", $config_login->selectsecret);
            if (!in_array($default, $selectsecret)) {
                $default = $selectsecret[0];
                set_th_option_cookie($default);
            }
        }

        foreach ($selectsecret as $key => $value) {
            if ($value == 'cloudflare') {
                if (!empty($config_login->sitekey) && !empty($config_login->secretkey)) {
                    $mform->addElement('cloudflare', 
                        'cloudflare_element', 
                        get_string('security_question', 'auth'),
                        ($default != 'cloudflare') ? array('class' => 'th-my-custom-class') : array()
                    );
                    $mform->addHelpButton('cloudflare_element', 'cloudflare', 'local_th_config_login');
                    $mform->closeHeaderBefore('cloudflare_element');
                    $secret[] = $value;
                }
            } else if ($value == 'recaptcha'){
                if (!empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey)) {
                    $mform->addElement('th_recaptcha', 
                        'th_recaptcha_element',
                        get_string('security_question', 'auth'),
                        ($default != 'recaptcha') ? array('class' => 'th-my-custom-class') : array()
                    );
                    $mform->addHelpButton('th_recaptcha_element', 'recaptcha', 'auth');
                    $mform->closeHeaderBefore('th_recaptcha_element');
                    $secret[] = 'recaptcha';
                }
            } else if ($value == 'email') {
                $buttonarray=array();
                $buttonarray[] =& $mform->createElement('text', 'email2', get_string('email'));
                $mform->setType('email2', PARAM_RAW_TRIMMED);
                $buttonarray[] =& $mform->createElement('button', 'sendotp', get_string('receive_otp', 'local_th_config_login'));
                $mform->addGroup($buttonarray, 'button_email', get_string('email'), array(' '), false);
                $secret[] = $value;
            }
        }
        $btn_status = [];

        if (count($secret) > 1) {
            $html = '<select id="th123" name="option">';
            foreach ($secret as $key => $value) {
                if ($key == 0) {
                    $html .= "<option value='$value' selected>" . ucfirst($value) . "</option>";
                } else if ($value == 'recaptcha') {
                    $html .= "<option value='$value'>Google reCaptcha</option>";
                } else {
                    $html .= "<option value='$value'>" . ucfirst($value) . "</option>";
                }
            }
            $html .= '</select>';
            $mform->addElement('static', 'description', '', $html);
            if ($default == 'cloudflare' or $default == 'recaptcha') {
                $btn_status = ['disabled' => true];
            }
        }

        $submitlabel = get_string('search');
        $mform->addElement('button', 'submitbutton', $submitlabel, $btn_status);
        // $mform->addElement('submit', 'submitbuttonemail', $submitlabel);

        $mform->disabledIf('username', 'show_option', 'eq', '1');
        $mform->disabledIf('email', 'show_option', 'eq', '0');

        $mform->hideif('username', 'show_option', 'eq', '1');
        $mform->hideif('email', 'show_option', 'eq', '0');
    }

    /**
     * Validate user input from the forgot password form.
     * @param array $data array of submitted form fields.
     * @param array $files submitted with the form.
     * @return array errors occuring during validation.
     */
    function validation($data, $files) {

        global $CFG;

        $errors = parent::validation($data, $files);

        // $config_login = get_config('local_th_config_login');

        // $option = $this->_form->_submitValues['option'];

        // if ($option == 'cloudflare') {
        //     if (!empty($config_login->sitekey) && !empty($config_login->secretkey)) {
        //         $recaptchaelement = $this->_form->getElement('cloudflare_element');
        //         if (!empty($this->_form->_submitValues['cf-turnstile-response'])) {
        //             $response = $this->_form->_submitValues['cf-turnstile-response'];
        //             if (!$recaptchaelement->verify($response)) {
        //                 $errors['cloudflare_element'] = get_string('incorrectpleasetryagain', 'local_th_config_login');
        //             }
        //         } else {
        //             $errors['cloudflare_element'] = get_string('missingcloudflarechallengefield', 'local_th_config_login');
        //         }
        //     }
        // } else if ($option == 'recaptcha') {
        //     if (!empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey)) {
        //         $recaptchaelement = $this->_form->getElement('recaptcha_element');
        //         if (!empty($this->_form->_submitValues['g-recaptcha-response'])) {
        //             $response = $this->_form->_submitValues['g-recaptcha-response'];
        //             if (!$recaptchaelement->verify($response)) {
        //                 $errors['recaptcha_element'] = get_string('incorrectpleasetryagain', 'auth');
        //             }
        //         } else {
        //             $errors['recaptcha_element'] = get_string('missingrecaptchachallengefield');
        //         }
        //     }
        // } else if ($option == 'email') {
        //     // $errors['security'] = get_string('missingrecaptchachallengefield');
        //     // $secret[] = ['value' => $value, 'name' => ucfirst($value)];
        //     // if ($cloudflare_default != 1 && $recaptcha_default != 1) {
        //     //     $email_default = 1;
        //     // }
        // }
        // Extend validation for any form extensions from plugins.
        $errors = array_merge($errors, core_login_validate_extend_forgot_password_form($data));

        $errors += th_core_login_validate_forgot_password_data($data);

        return $errors;
    }

}

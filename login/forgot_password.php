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
 * Forgot password routine.
 *
 * Finds the user and calls the appropriate routine for their authentication type.
 *
 * There are several pathways to/through this page, summarised below:
 * 1. User clicks the 'forgotten your username or password?' link on the login page.
 *  - No token is received, render the username/email search form.
 * 2. User clicks the link in the forgot password email
 *  - Token received as GET param, store the token in session, redirect to self
 * 3. Redirected from (2)
 *  - Fetch token from session, and continue to run the reset routine defined in 'core_login_process_password_set()'.
 *
 * @package    core
 * @subpackage auth
 * @copyright  1999 onwards Martin Dougiamas  http://dougiamas.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php');
require_once($CFG->libdir.'/authlib.php');
require_once(__DIR__ . '/lib.php');
require_once('classes/form/th_forgot_password_form.php');
require_once($CFG->dirroot.'/login/set_password_form.php');
require_once($CFG->dirroot.'/login/lib.php');

$token = optional_param('token', false, PARAM_ALPHANUM);

$PAGE->set_url('/' . get_th_login_dir() .'/forgot_password.php');
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);

// setup text strings
$strforgotten = get_string('passwordforgotten');
$strlogin     = get_string('login');

$PAGE->navbar->add($strlogin, get_login_url());
$PAGE->navbar->add($strforgotten);
$PAGE->set_title($strforgotten);
$PAGE->set_heading($COURSE->fullname);
$PAGE->requires->js_call_amd('local_th_config_login/dropdown');
$config_login = get_config('local_th_config_login');
$count = $config_login->timeexpiry ? $config_login->timeexpiry : 1800;

// if alternatepasswordurl is defined, then we'll just head there
if (empty($CFG->forgottenpasswordurl)) {
    redirect('/login/forgot_password.php');
}

// if you are logged in then you shouldn't be here!
if (isloggedin() and !isguestuser()) {
    redirect($CFG->wwwroot.'/index.php', get_string('loginalready'), 5);
}

// Fetch the token from the session, if present, and unset the session var immediately.
$tokeninsession = false;
if (!empty($SESSION->password_reset_token)) {
    $token = $SESSION->password_reset_token;
    unset($SESSION->password_reset_token);
    $tokeninsession = true;
}

if (empty($token)) {
    
    // This is a new password reset request.
    // Process the request; identify the user & send confirmation email.
    th_core_login_process_password_reset_request();
} else {
    // A token has been found, but not in the session, and not from a form post.
    // This must be the user following the original rest link, so store the reset token in the session and redirect to self.
    // The session var is intentionally used only during the lifespan of one request (the redirect) and is unset above.
    if (!$tokeninsession && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $SESSION->password_reset_token = $token;
        redirect($CFG->wwwroot . '/' .get_th_login_dir() . '/forgot_password.php');
    } else {
        // Continue with the password reset process.
        core_login_process_password_set($token);
    }
}
?>
<script language="javascript">
   
    $(document).ready(function() {

        document.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
                document.getElementById('id_submitbutton').click();
            }
        });

        function getCookie(name) {
            const decodedCookies = decodeURIComponent(document.cookie);
            const cookies = decodedCookies.split(';');
            name = name + "=";
            for (let i = 0; i < cookies.length; i++) {
                let cookie = cookies[i].trim();
                if (cookie.indexOf(name) === 0) {
                    return cookie.substring(name.length);
                }
            }
          return null; // Trả về null nếu không tìm thấy
        }
        function cookieExists(name) {
            return document.cookie.split(';').some(cookie => cookie.trim().startsWith(name + '='));
        }
        function set_cookies(name) {
          var now = new Date();
          var time = now.getTime();
          var expireTime = time + 604800 * 60;
          now.setTime(expireTime);
          document.cookie = 'option='+name+';expires='+now.toUTCString()+';path=/';
        }

        var option = '';
        if (option = getCookie('option')) {
            $('#th123').val(option);
        } else {

            var option = $('#th123').find('option:selected').val();
        }

        if (option != 'email') {
            $('#fgroup_id_button_email').hide();
            // document.getElementById("id_submitbutton").disabled = true;
        }

        // $(".dropdown-list").css('max-width', '500px');

        // $("div.dropdown-list").css('max-width', '500px');

        $("#id_email2").css("margin-bottom", "0px");
        $("input#id_email2").parent().parent().css({"width": "100%"});
        $('#fgroup_id_button_email').css(
            {
                "margin-bottom": "0px",
                "margin-top": "0px", 
                "background-color": "#fff",
                "border-top": "none",
                "padding-left": "0px",
                "padding-top": "0px",
            }
        );

        function print_error(a, b) {
            if (a == 'email2') {
                if ($("div#id_error_email2").length) {
                    $("div#id_error_email2").empty();
                    $("div#id_error_email2").text(b);
                } else {
                    $("div#id_error_email2").text(b);
                }
                $("div#id_error_email2").css('display', 'block');
            } else if (a == 'username') {
                if ($("div#id_error_username").length) {
                    $("div#id_error_username").empty();
                    $("div#id_error_username").text(b);
                } else {
                    $("div#id_error_username").text(b);
                }
                $("div#id_error_username").css('display', 'block');
            } else if (a == 'sendotp'){
                if ($("div#id_error_sendotp").length) {
                    $("div#id_error_sendotp").empty();
                    $("div#id_error_sendotp").text(b);
                } else {
                    $("div#id_error_sendotp").text(b);
                }
                $("div#id_error_sendotp").css('display', 'block');
            } else if (a == 'otp'){
                if ($("div#id_error_otp").length) {
                    $("div#id_error_otp").empty();
                    $("div#id_error_otp").text(b);
                } else {
                    $("div#id_error_otp").text(b);
                }
                $("div#id_error_otp").css('display', 'block');
            } else if (a == 'email') {
                if ($("div#id_error_email").length) {
                    $("div#id_error_email").empty();
                    $("div#id_error_email").text(b);
                } else {
                    $("div#id_error_email").text(b);
                }
                $("div#id_error_email").css('display', 'block');
            } else if (a == 'cloudflare') {
                if ($("span.error").length) {
                    $("span.error").empty();
                    $("span.error").text(b);
                } else {
                    $("span.error").text(b);
                }
                // $("div.felement.fcloudflare.error span").css('display', 'block');id_error_recaptcha_element
            } else if (a == 'recaptcha') {
                if ($("div#id_error_recaptcha_element").length) {
                    $("div#id_error_recaptcha_element").empty();
                    $("div#id_error_recaptcha_element").text(b);
                } else {
                    $("div#id_error_recaptcha_element").text(b);
                }
                $("div#id_error_recaptcha_element").css('display', 'block');
            } else if (a == 'chung'){
                $("div#id_error_username").empty();
                $("div#id_error_email2").empty();
                $("div#id_error_email").empty();
                if ($('div#th_alert').length) {
                    $("div#th_alert").empty();
                    $("div#th_alert").prepend('<div class="alert alert-error">' + b +'</div>');
                } else {
                    $("div#th_alert").prepend('<div class="alert alert-error">' + b +'</div>');
                }
            }
        }

        function print_success(a) {
            $("div#id_error_username").empty();
            $("div#id_error_email2").empty();
            $("div#id_error_email").empty();
            if ($('div#th_alert').length) {
                $("div#th_alert").empty();
                $("div#th_alert").prepend('<div class="alert alert-success">' + a +'</div>');
            } else {
                $("div#th_alert").prepend('<div class="alert alert-success">' + a +'</div>');
            }
        }

        $('#id_sendotp').click(function() {
            
            var username = $('#id_username').val();
            var email2 = $('#id_email2').val().trim();
            var testEmail = /^[A-Z0-9._%+-]+@([A-Z0-9-]+\.)+[A-Z]{2,4}$/i;
            var show_option = $('input[name="show_option"]:checked').val();
            if (show_option == 0) {
                if (!username) {
                    print_error('username', "<?php echo get_string('please_username', 'local_th_config_login');?>");
                } else {
                    $("div#id_error_username").empty();
                }
            } else {
                var email = $('#id_email').val().trim();
                if (email === '') {
                    print_error('email', "<?php echo get_string('please_email', 'local_th_config_login'); ?>");

                } else {
                    $("div#id_error_email").empty();
                }
            }
            
            if (!email2) {
                print_error('email2', "<?php echo get_string('please_email', 'local_th_config_login'); ?>");
                return;
            } if (show_option == 1 && email2 !== email){
                print_error('email2', "<?php echo get_string('email_not_match', 'local_th_config_login'); ?>");
                return;
            } else {
                $("div#id_error_email2").empty();
                if (testEmail.test(email2)) {

                    const button = document.querySelector('#id_sendotp');
                    let abc = <?php echo $count ?>;
                    let countdown = null;

                    function updateButton() {

                        button.innerHTML = "<?php echo get_string('resends', 'local_th_config_login');?>" + ' '+abc+ ' s';
                        if (abc === 0) {
                          clearInterval(countdown);
                          abc = <?php echo $count ?>;

                          button.innerHTML = "<?php echo get_string('receive_otp', 'local_th_config_login'); ?>";
                        
                          button.disabled = false;
                          return;
                        }
                        abc--;
                    };
                    button.disabled = true;
                    updateButton();
                    countdown = setInterval(function() {
                    updateButton();
                    }, 1000);
                    if (!$("#id_otp").length) {
                        $("#fgroup_id_button_email").after('<div id="fitem_id_otp" class="form-group row fitem">' +
                            '<div class="col-md-3 col-form-label d-flex pb-0 pr-md-0">'+
                                '<label class="d-inline word-break " for="id_otp">OTP</label>'+
                                    '<div class="form-label-addon d-flex align-items-center align-self-start"></div></div><div class="col-md-9 form-inline align-items-start felement" data-fieldtype="text"><input type="text" class="form-control "name="otp" id="id_otp" value="" size="20" autocomplete="otp"><div class="form-control-feedback invalid-feedback" id="id_error_otp"></div></div></div>');

                        
                    }
                    $.ajax({
                        type: "POST",
                        url:  "<?php echo new moodle_url('/' . get_th_login_dir() . '/send_otp.php'); ?>", 
                        data: {
                            username: username,
                            email: email2,
                            type: 0,
                            typesend: 1
                        },
                        success: function(result) {
                            console.log(result);
                            print_success("<?php echo get_string('send_otp', 'local_th_config_login'); ?>");
                        },
                        error: function (xhr, ajaxOptions, thrownError) {
                            
                        }
                    });
                } else {
                    print_error('email2', "<?php echo get_string('vali_email', 'local_th_config_login'); ?>");
                }
            }
        });
        
        $("#id_submitbutton").click(function() {
            var show_option = $('input[name="show_option"]:checked').val();

            if (show_option == 0) {
                var username = $('#id_username').val().trim();
                if (username === '') {
                    print_error('username', "<?php echo get_string('please_username', 'local_th_config_login');?>");
                    return;
                } else {
                    $("div#id_error_username").empty();
                }

            } else if (show_option == 1) {
                var email = $('#id_email').val().trim();
                if (email === '') {
                    print_error('email',"<?php echo get_string('please_email', 'local_th_config_login'); ?>");
                    return;
                } else {
                    $("div#id_error_email").empty();
                }
            }

            const select = document.querySelector('#th123');
            var option = '';
            if (select != null) {
                option = $('#th123').find('option:selected').val();
                set_cookies(option);
                // var option_recaptcha = document.querySelector('#recaptcha_element');
                // var option_cloudflare = document.querySelector('.cloudflare_element');
                // var option_email = document.querySelector('#id_email2');
                // if (option_cloudflare) {
                //     option = 'cloudflare';
                // } else if (option_recaptcha) {
                //     option = 'recaptcha';
                // } else if (option_email) {
                //     option = 'email';
                // }
                // option = '';
                // console.log(option_email);
                // console.log(option);
            }
            // console.log(option);
            if (option == 'cloudflare') {
                if (typeof turnstile !== 'undefined') {
                    var response = turnstile.getResponse();
                    if(typeof response !== 'undefined') { 
                        $("form.mform").submit();
                    } else {
                        if (!$("div.felement.fcloudflare span.error").length){
                            var newChildElement = $('<span class="error" tabindex="0">'+ "<?php echo get_string('missingcloudflarechallengefield', 'local_th_config_login'); ?>"+'</span>');
                            $("div.felement.fcloudflare").prepend(newChildElement);
                        } else {
                            print_error('cloudflare', "<?php echo get_string('verified_cloudflare', 'local_th_config_login'); ?>");
                        }
                    }
                } else {
                    print_error('cloudflare', "<?php echo get_string('no_cloudflare', 'local_th_config_login'); ?>");
                }
            } else if (option == 'recaptcha') {
                  var response = grecaptcha.getResponse();
                if (typeof grecaptcha !== 'undefined') {
                    var response = grecaptcha.getResponse();
                    if(response.length != 0) {
                        $("form.mform").submit();
                    } else {
                        print_error('recaptcha',"<?php echo get_string('verified_recaptcha', 'local_th_config_login'); ?>");
                    }
                } else {
                    print_error('recaptcha', "<?php echo get_string('no_recaptcha', 'local_th_config_login'); ?>");
                }
            } else if (option == 'email') {
                var email2 = $('#id_email2').val().trim();

                if (email2 === '') {
                    print_error('email2', "<?php echo get_string('please_email', 'local_th_config_login'); ?>");
                } else {
                    $("div#id_error_email2").empty();
                }
                if ($('#id_otp').length) {
                    var otp = $('#id_otp').val().trim();
                    if (otp === '') {
                        print_error('otp', "<?php echo get_string('please_otp', 'local_th_config_login'); ?>");
                    }
                } else {
                    print_error('email2', "<?php echo get_string('please_email', 'local_th_config_login'); ?>");
                }

                if ($('#id_otp').length && $('#id_otp').val()) {
                    const data = {email: email2, otp: otp, typesend: 1};
                    if (show_option == 0) {
                        data.username = $('#id_username').val();
                    } else {
                        data.username = '';
                    }
                    
                    $.ajax({
                        type: "POST",
                        url:  "<?php echo new moodle_url('/' . get_th_login_dir() . '/check_otp.php'); ?>", 
                        data: data,
                        success: function(result) {

                            if (result.status) {
                                $("form.mform").submit();
                            } else {
                                console.log(result.message);
                                print_error('chung', result.message);
                            }
                        }
                    });
                } else {
                    print_error('otp', "<?php echo get_string('please_otp', 'local_th_config_login'); ?>");
                }
            } else {
                 $("form.mform").submit();
            }
        });
    });
</script>

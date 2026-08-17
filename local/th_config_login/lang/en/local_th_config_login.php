<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     local_th_config_login
 * @category    string
 * @copyright   2024 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'TH Config login';
$string['invailpassword'] = 'Your account has been locked. Please wait {$a} minutes or use the forgot password function.';
$string['selectsecret'] = 'Select secret';
$string['selectsecretdesc'] = 'select Google reCaptcha or Cloudflare Turnstile';
$string['configcloudflaresecretkey'] = 'String of characters (secret key) used to communicate between your Moodle server and the Cloudflare Turnstile server. Cloudflare Turnstile keys can be obtained from <a target="_blank" href="hhttps://www.cloudflare.com/es-es/application-services/products/turnstile/">Cloudflare Turnstile</a>.';
$string['configcloudflaresitekey'] = 'String of characters (site key) used to display the Cloudflare Turnstile element in the signup form. Cloudflare Turnstile keys can be obtained from <a target="_blank" href="https://www.cloudflare.com/es-es/application-services/products/turnstile/">Cloudflare Turnstile</a>.';
$string['cloudflareprivatekey'] = 'Cloudflare Turnstile secret key';
$string['cloudflarepublickey'] = 'Cloudflare Turnstile site key';

$string['vali_user'] = 'Incorrect username or password';
$string['noresetrecord'] = 'There is no record of the login request. Please create a new login request.';
$string['incorrectpleasetryagain'] = 'Incorrect. Please try again.';
$string['missingcloudflarechallengefield'] = 'Failed Cloudflare challenge, try again.';
$string['cloudflare'] = 'Cloudflare Turnstile';
$string['cloudflare_help'] = 'The Cloudflare Turnstile is for preventing abuse from automated programs. Follow the instructions to verify you are a person. This could be a box to check, characters presented in an image you must enter or a set of images to select from.';
$string['error_verify'] = 'Incorrect security question';
$string['send_otp'] = 'Send OTP';
$string['resend'] = 'Resend {a} s';
$string['resends'] = 'Resend';
$string['receive_otp'] = 'Receive OTP';
$string['confirm_security_question'] = 'Confirm Security Question';
$string['username_empty'] = 'Empty username';
$string['email_empty'] = 'Empty email';
$string['password_empty'] = 'Empty password';
$string['taskname'] = 'Clean OTP login expiry';
$string['timeexpiry'] = 'Time expiry';
$string['timeexpiry_desc'] = 'OTP expires from the time of request';
$string['option_expire'] = 'Thời gian hết hạn lựa chọn xác minh';
$string['option_expire_desc'] = 'Khi 1 lựa chọn xác minh được chọn thì sau khoảng thời gian này sẽ hết hạn.';
$string['directory'] = 'Tên thư mục lưu mã nguồn';
$string['directory_desc'] = 'Tên thư mục lưu mã nguồn phần đăng nhập mới';
$string['confirm_security'] = 'Confirm security';
$string['otp_login'] = 'OTP Đăng nhập';
$string['otp_forgot'] = 'OTP Lấy lại mật khẩu';
$string['message_send_otp'] = '
Mã OTP dùng để đăng nhập của Anh/Chị là {$a->otp}
Mã này chỉ dùng được trong {$a->timeexpiry} phút kể từ thời điểm yêu cầu.
Nếu không đăng nhập được, có thể mã đã hết hạn. Vui lòng gửi lại yêu cầu.

Chúc Anh/Chị học tập tốt!';

$string['vali_email'] = 'Email is not in correct format.';
$string['no_otp'] = 'OTP is wrong.';
$string['expiry_otp'] = 'OTP expiry.';
$string['otp_incorrect'] = 'OTP is incorrect.';
$string['otp_empty'] = 'OTP empty.';
$string['error'] = 'Error';
$string['please_username'] = 'Please enter username.';
$string['please_email'] = 'Please enter email.';

$string['email_not_match'] = 'Email do not match.';
$string['verified_cloudflare'] = 'Cloudflare not verified.';
$string['no_cloudflare'] = 'No Cloudflare.';
$string['verified_recaptcha'] = 'reCaptcha not verified.';
$string['no_recaptcha'] = 'No reCaptcha.';
$string['please_otp'] = 'Please enter OTP.';

$string['message_send_otp_forgot'] = '
Mã OTP dùng để đặt lại mật khẩu của Anh/Chị là {$a->otp}
Mã này chỉ dùng được trong {$a->timeexpiry} phút kể từ thời điểm yêu cầu.
Nếu không được, có thể mã đã hết hạn. Vui lòng gửi lại yêu cầu.

Chúc Anh/Chị học tập tốt!';
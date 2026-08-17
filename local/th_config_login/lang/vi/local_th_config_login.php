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
$string['selectsecret'] = 'Select secret';
$string['invailpassword'] = 'Tài khoản của bạn đã bị khóa. Bạn vui lòng đợi {$a} phút hoặc sử dụng chức năng quên mật khẩu.';
$string['selectsecretdesc'] = 'select Google reCaptcha or Cloudflare Turnstile';
$string['configcloudflaresecretkey'] = 'String of characters (secret key) used to communicate between your Moodle server and the Cloudflare Turnstile server. Cloudflare Turnstile keys can be obtained from <a target="_blank" href="hhttps://www.cloudflare.com/es-es/application-services/products/turnstile/">Cloudflare Turnstile</a>.';
$string['configcloudflaresitekey'] = 'String of characters (site key) used to display the Cloudflare Turnstile element in the login form. Cloudflare Turnstile keys can be obtained from <a target="_blank" href="https://www.cloudflare.com/es-es/application-services/products/turnstile/">Cloudflare Turnstile</a>.';
$string['cloudflareprivatekey'] = 'Cloudflare Turnstile secret key';
$string['cloudflarepublickey'] = 'Cloudflare Turnstile site key';

$string['noresetrecord'] = 'Không có hồ sơ nào về yêu cầu đăng nhập. Vui lòng tạo yêu cầu đăng nhập mới.';
$string['vali_user'] = 'Tên đăng nhập hoặc mật khẩu không chính xác';
$string['incorrectpleasetryagain'] = 'Không đúng. Vui lòng thử lại.';
$string['missingcloudflarechallengefield'] = 'Lỗi Cloudflare, hãy thử lại.';

$string['cloudflare'] = 'Cloudflare Turnstile';
$string['cloudflare_help'] = 'The Cloudflare Turnstile is for preventing abuse from automated programs. Follow the instructions to verify you are a person. This could be a box to check, characters presented in an image you must enter or a set of images to select from.';
$string['error_verify'] = 'Câu hỏi bảo mật chưa đúng';
$string['send_otp'] = 'Gửi OTP';
$string['receive_otp'] = 'Nhận OTP';
$string['resend'] = 'Gửi lại {a} s';
$string['resends'] = 'Gửi lại';
$string['confirm_security_question'] = 'Xác nhận Câu hỏi bảo mật';
$string['username_empty'] = 'Tài khoản trống';
$string['email_empty'] = 'Thư điện tử trống';
$string['password_empty'] = 'Mật khẩu trống';
$string['taskname'] = 'Dọn dẹp OTP đăng nhập hết hạn';
$string['timeexpiry'] = 'Thời gian hết hạn';
$string['option_expire'] = 'Thời gian hết hạn lựa chọn xác minh';
$string['option_expire_desc'] = 'Khi 1 lựa chọn xác minh được chọn thì sau khoảng thời gian này sẽ hết hạn.';
$string['directory'] = 'Tên thư mục lưu mã nguồn';
$string['directory_desc'] = 'Tên thư mục lưu mã nguồn phần đăng nhập mới';
$string['confirm_security'] = 'Chưa xác nhận Câu hỏi bảo mật';
$string['otp_login'] = 'OTP Đăng nhập';
$string['otp_forgot'] = 'OTP Lấy lại mật khẩu';
$string['message_send_otp'] = '
Mã OTP dùng để đăng nhập của Anh/Chị là {$a->otp}
Mã này chỉ dùng được trong {$a->timeexpiry} phút kể từ thời điểm yêu cầu.
Nếu không đăng nhập được, có thể mã đã hết hạn. Vui lòng gửi lại yêu cầu.

Chúc Anh/Chị học tập tốt!';

$string['vali_email'] = 'Email không đúng định dạng.';
$string['no_otp'] = 'OTP không đúng.';
$string['expiry_otp'] = 'OTP hết hạn.';
$string['otp_incorrect'] = 'OTP không đúng.';
$string['otp_empty'] = 'OTP trống.';
$string['error'] = 'Lỗi xác minh';
$string['please_username'] = 'Vui lòng nhập tên đăng nhập.';
$string['please_email'] = 'Vui lòng nhập thư điện tử.';
$string['email_not_match'] = 'Thư điện tử chưa trùng khớp.';
$string['verified_cloudflare'] = 'Chưa xác nhận Cloudflare.';
$string['no_cloudflare'] = 'Không có Cloudflare.';
$string['verified_recaptcha'] = 'Chưa xác nhận reCaptcha.';
$string['no_recaptcha'] = 'Không có reCaptcha.';
$string['please_otp'] = 'Vui lòng nhập OTP.';

$string['message_send_otp_forgot'] = '
Mã OTP dùng để đặt lại mật khẩu của Anh/Chị là {$a->otp}
Mã này chỉ dùng được trong {$a->timeexpiry} phút kể từ thời điểm yêu cầu.
Nếu không được, có thể mã đã hết hạn. Vui lòng gửi lại yêu cầu.

Chúc Anh/Chị học tập tốt!';


Gồm 2 phần:

Phần 1: Tạo khóa 
1. Cloudflare Turnstile:
	+ Truy cập: https://www.cloudflare.com/application-services/products/turnstile/ và Đăng nhập.
	+ Chọn Turnstile trên Dashboard, rồi chọn Add widget.
	+ Nhập Widget name và Chọn Add Hostnames.
	+ Nhập các Hostname -> chọn Add
	+ Chọn các Hostname muốn dùng Cloudflare Turnstile bằng cách chọn vào các checkbox tương ứng và Chọn Add.
	+ Chọn Create.
	+ Nhận được 2 khóa: Site key và Secret key.
2. Google Recaptcha:
	+ Truy cập: https://www.google.com/recaptcha/admin/create
	+ Nhập nhãn (Label): ví dụ: example.com
	+ reCAPTCHA type: chọn Challenge (v2) và chọn "I'm not a robot" Checkbox
	+ Nhập Domains muốn dùng Recaptcha.
	+ Chọn Submit
	+ Nhận được 2 khóa: Site key và Secret key

Phần 2: Cài đặt và cấu hình
1. Cài đặt các plugin trên github:
	+ th_lambda (version: 2025022000) ở thư mục theme.
    + th_config_login (version: 2025021100) ở thư mục local.
    + logins ở ngoài thư mục gốc. (cùng cấp với tệp config.php).
- Thực hiện nâng cấp các plugin.
2. Cấu hình
- Cấu hình dùng Cloudflare Turnstile hoặc Google Recaptcha đều cần 2 khóa mới hoạt động.
	+ Cloudflare Turnstile: 
		+ Truy cập: Dashboard > Site administration > Plugins > Local plugins > TH Config login.
		+ Chọn Cloudflare Turnstile và Nhập 2 khóa: Site key và Secret key.
	+ Google Recaptcha:
		+ Truy cập: Dashboard > Site administration > Plugins > Local plugins > TH Config login.
		+ Chọn Google Recaptcha
		+ Nhập 2 khóa: truy cập: Dashboard > Site administration > Plugins > Authentication > Manage authentication
			+ Tìm ReCAPTCHA site key và ReCAPTCHA secret key rồi nhập các giá trị tương ứng.
 - Cấu hình liên kết Đăng nhập và Quên mật khẩu mới: Dashboard > Site administration > Plugins > Authentication > Manage authentication. Chú ý: Domain của bạn.
	+ Đặt liên kết đăng nhập mới (tìm kiếm Alternate login URL): https://example.com/logins/index.php
	+ Đặt liên kết Quên mật khẩu mới (tìm kiếm Forgotten password URL): https://example.com/logins/forgot_password.php
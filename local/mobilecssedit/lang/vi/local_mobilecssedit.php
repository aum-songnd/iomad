<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'Trình sửa CSS Mobile app (inline editor)';

// Tên setting hiển thị trên trang Mobile app appearance.
$string['cssfilecontent']   = 'Nội dung file CSS (mobilecssurl)';
$string['editingfile']      = 'Đang sửa file: {$a}';
$string['description']      = 'Trình sửa CSS Mobile app (inline editor)';

// Tính năng tự tạo file CSS mới.
$string['createcssfile']       = 'Tạo file CSS mới';
$string['createcssfile_desc']  = 'Nhập URL / đường dẫn của file CSS muốn tạo, hệ thống sẽ tự tạo file trên mã nguồn Moodle và lưu vào cấu hình mobilecssurl của company hiện tại.';
$string['createcssfilehint']   = 'Ví dụ: /local/mobilecssedit/style/tencongty.css hoặc URL đầy đủ cùng domain với site. Chỉ chấp nhận file .css nằm trong mã nguồn Moodle. Để trống nếu chưa muốn tạo.';
$string['urlrequired']         = 'Vui lòng nhập URL / đường dẫn file CSS cần tạo.';
$string['invalidurl']          = 'URL không hợp lệ: phải là file .css, nằm trong mã nguồn Moodle (cùng domain với site hoặc đường dẫn tương đối), thư mục chứa file phải đã tồn tại và không được chứa "..".';
$string['nocompany']           = 'Không xác định được company hiện đang chỉnh sửa nên không thể tạo file.';
$string['dirnotwritable']      = 'Thư mục chứa file tồn tại nhưng webserver không có quyền ghi (kiểm tra quyền thư mục trên ổ đĩa).';
$string['filecreated']         = 'Đã tạo file CSS mới thành công.';
$string['filealreadyexists']   = 'File đã tồn tại sẵn - đã cập nhật cấu hình để trỏ tới file này (nội dung file không bị thay đổi).';
$string['autocreatedcomment']  = 'File được tự động tạo bởi local_mobilecssedit';

// Trang tổng quan danh sách company (managecss.php).
$string['managecss']       = 'Quản lý file CSS Mobile app theo Company';
$string['companyname']     = 'Tên Company';
$string['cssfileexists']   = 'File đã tồn tại - không thể tạo lại, hãy vào trang cấu hình theme để sửa nội dung.';
$string['nocompanies']     = 'Không có company nào để hiển thị.';

// Thông báo trạng thái.
$string['nolocalfile']      = 'Chưa cấu hình "Mobile custom CSS" (mobilecssurl), hoặc URL đó không trỏ tới file nằm trong mã nguồn Moodle nên không thể sửa trực tiếp tại đây.';
$string['cannotwrite']      = 'Không xác định được file CSS cục bộ để ghi, hoặc file không tồn tại.';
$string['notwritable']      = 'File tồn tại nhưng webserver không có quyền ghi (kiểm tra quyền file trên ổ đĩa).';
$string['writefailed']      = 'Ghi file thất bại.';
$string['nopermission']     = 'Bạn không có quyền xem hoặc chỉnh sửa file CSS này. Cần có capability "local/mobilecssedit:manage" (mặc định chỉ cấp cho Manager / site admin).';

// Capability (hiển thị trong Site administration > Users > Permissions > Define roles).
$string['mobilecssedit:manage'] = 'Quản lý (xem và sửa) file CSS của Mobile app qua local_mobilecssedit';

// Privacy API (plugin không lưu dữ liệu cá nhân nào).
$string['privacy:metadata']     = 'Plugin local_mobilecssedit không lưu trữ bất kỳ dữ liệu cá nhân nào của người dùng.';
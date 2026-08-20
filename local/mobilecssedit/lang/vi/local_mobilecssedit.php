<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'Trình sửa CSS Mobile app (inline editor)';

// Tên setting hiển thị trên trang Mobile app appearance.
$string['cssfilecontent']   = 'Nội dung file CSS (mobilecssurl)';
$string['editingfile']      = 'Đang sửa file: {$a}';
$string['description']      = 'Trình sửa CSS Mobile app (inline editor)';

// Trang xem/sửa nội dung (editcss.php).
$string['editcssfile']      = 'Xem/Sửa nội dung';
$string['backtomanagecss']  = '&laquo; Quay lại danh sách company';
$string['contentsaved']     = 'Đã lưu nội dung file thành công.';

// Tính năng tự tạo file CSS mới.
$string['createcssfile']       = 'Tạo file CSS mới';
$string['createcssfile_desc']  = 'Nhập TÊN FILE muốn tạo (vd: tencongty.css). File sẽ luôn được tạo trong thư mục cố định local/mobilecssedit/style/ của mã nguồn Moodle, và setting mobilecssurl của company hiện tại sẽ được cập nhật để trỏ tới file đó.';
$string['createcssfilehint']   = 'Ví dụ: tencongty.css. Chỉ nhập tên file (không có thư mục, không phải URL) - file sẽ được tạo trong local/mobilecssedit/style/. Chỉ chấp nhận file .css. Để trống nếu chưa muốn tạo.';
$string['urlrequired']         = 'Vui lòng nhập tên file CSS cần tạo.';
$string['invalidurl']          = 'Tên file không hợp lệ: phải là tên file thuần tuý kết thúc bằng ".css" (chỉ gồm chữ, số, "-", "_", "."), không được chứa thư mục, URL hay "..". File sẽ luôn được tạo trong local/mobilecssedit/style/.';
$string['nocompany']           = 'Không xác định được company hiện đang chỉnh sửa nên không thể tạo file.';
$string['dirnotwritable']      = 'Thư mục local/mobilecssedit/style/ không tồn tại hoặc webserver không có quyền ghi (kiểm tra quyền thư mục trên ổ đĩa).';
$string['filecreated']         = 'Đã tạo file CSS mới thành công.';
$string['filealreadyexists']   = 'File đã tồn tại sẵn - đã cập nhật cấu hình để trỏ tới file này (nội dung file không bị thay đổi).';
$string['autocreatedcomment']  = 'File được tự động tạo bởi local_mobilecssedit';

// Trang tổng quan danh sách company (managecss.php).
$string['managecss']       = 'Quản lý file CSS Mobile app theo Company';
$string['companyname']     = 'Tên Company';
$string['cssfileexists']   = 'File đã tồn tại - dùng nút "Xem/Sửa nội dung" bên dưới để xem hoặc sửa.';
$string['nocompanies']     = 'Không có company nào để hiển thị.';

// Tính năng đổi tên file CSS đã tồn tại.
$string['renamecssfile']       = 'Đổi tên';
$string['newfilenamerequired'] = 'Vui lòng nhập tên file mới.';
$string['nocurrentfile']       = 'Company này chưa có file CSS nào được cấu hình để đổi tên.';
$string['targetfileexists']    = 'Đã có 1 file khác trùng tên đó trong local/mobilecssedit/style/ - hãy chọn tên khác.';
$string['filerenamed']         = 'Đã đổi tên file thành công.';
$string['samefilename']        = 'Tên file mới trùng với tên hiện tại - không có gì thay đổi.';

// Thông báo trạng thái.
$string['nolocalfile']      = 'Chưa cấu hình "Mobile custom CSS" (mobilecssurl), hoặc giá trị đó không trỏ tới file nằm trong local/mobilecssedit/style/ nên không thể sửa trực tiếp tại đây.';
$string['cannotwrite']      = 'Không xác định được file CSS cục bộ để ghi, hoặc file không tồn tại.';
$string['notwritable']      = 'File tồn tại nhưng webserver không có quyền ghi (kiểm tra quyền file trên ổ đĩa).';
$string['writefailed']      = 'Ghi file thất bại.';
$string['nopermission']     = 'Bạn không có quyền xem hoặc chỉnh sửa file CSS này. Cần có capability "local/mobilecssedit:manage" (mặc định chỉ cấp cho Manager / site admin).';

// Capability (hiển thị trong Site administration > Users > Permissions > Define roles).
$string['mobilecssedit:manage'] = 'Quản lý (xem và sửa) file CSS của Mobile app qua local_mobilecssedit';

// Privacy API (plugin không lưu dữ liệu cá nhân nào).
$string['privacy:metadata']     = 'Plugin local_mobilecssedit không lưu trữ bất kỳ dữ liệu cá nhân nào của người dùng.';

$string['mobilecssurl_notset']     = 'Chưa cấu hình.';
$string['mobilecssurl_managelink'] = 'Quản lý file CSS';
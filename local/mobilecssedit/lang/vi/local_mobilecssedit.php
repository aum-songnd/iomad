<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'Trình sửa CSS Mobile app (inline editor)';

// Tên setting hiển thị trên trang Mobile app appearance.
$string['cssfilecontent']   = 'Nội dung file CSS (mobilecssurl)';
$string['editingfile']      = 'Đang sửa file: {$a}';
$string['description']      = 'Trình sửa CSS Mobile app (inline editor)';

// Trang xem/sửa nội dung (editcss.php).
$string['editcssfile']      = 'Xem/Sửa nội dung';
$string['closecssfile']     = 'Đóng lại';
$string['backtomanagecss']  = '&laquo; Quay lại danh sách company';
$string['contentsaved']     = 'Đã lưu nội dung file thành công.';

// Tính năng tự tạo file CSS mới. Tên file không còn do admin gõ tay nữa -
// luôn được suy ra tự động từ shortname của company (vd shortname "abc"
// -> "abc.css").
$string['createcssfile']       = 'Tạo file CSS mới';
$string['createcssfile_desc']  = 'File CSS được đặt tên theo shortname của company (vd tencongty.css) sẽ luôn được tạo trong thư mục cố định local/mobilecssedit/style/ của mã nguồn Moodle, và setting mobilecssurl của company hiện tại sẽ được cập nhật để trỏ tới file đó.';
$string['createcssfilehint']   = 'Tên file được tự động sinh ra từ shortname của company. File sẽ luôn được tạo trong local/mobilecssedit/style/.';
$string['createcssfilefor']    = 'Tạo {$a}';
$string['urlrequired']         = 'Vui lòng nhập tên file CSS cần tạo.';
$string['invalidurl']          = 'Tên file không hợp lệ: phải là tên file thuần tuý kết thúc bằng ".css" (chỉ gồm chữ, số, "-", "_", "."), không được chứa thư mục, URL hay "..". File sẽ luôn được tạo trong local/mobilecssedit/style/.';
$string['invalidshortname']    = 'Shortname của company này không thể chuyển thành tên file CSS hợp lệ (rỗng hoặc không còn ký tự nào dùng được). Vui lòng sửa lại shortname của company trước.';
$string['nocompany']           = 'Không xác định được company hiện đang chỉnh sửa nên không thể tạo file.';
$string['dirnotwritable']      = 'Thư mục local/mobilecssedit/style/ không tồn tại hoặc webserver không có quyền ghi (kiểm tra quyền thư mục trên ổ đĩa).';
$string['filecreated']         = 'Đã tạo file CSS mới thành công.';
$string['filealreadyexists']   = 'File đã tồn tại sẵn - đã cập nhật cấu hình để trỏ tới file này (nội dung file không bị thay đổi).';
$string['autocreatedcomment']  = 'File được tự động tạo bởi local_mobilecssedit';

// Trang tổng quan danh sách company (managecss.php).
$string['managecss']       = 'Quản lý file CSS Mobile app theo Company';
$string['companyname']     = 'Tên Company';
$string['companyshortname'] = 'Shortname Company';
$string['nocompanies']     = 'Không có company nào để hiển thị.';

$string['renamecssfile']       = 'Đổi tên';
$string['updatefilenamefromshortname'] = 'Cập nhập filename';
$string['newfilenamerequired'] = 'Vui lòng nhập tên file mới.';
$string['nocurrentfile']       = 'Company này chưa có file CSS nào được cấu hình để đổi tên.';
$string['targetfileexists']    = 'Đã có 1 file khác trùng tên đó trong local/mobilecssedit/style/ - hãy chọn tên khác.';
$string['filerenamed']         = 'Đã cập nhật tên file khớp với shortname của company.';
$string['samefilename']        = 'Tên file đã khớp với shortname của company - không có gì thay đổi.';

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
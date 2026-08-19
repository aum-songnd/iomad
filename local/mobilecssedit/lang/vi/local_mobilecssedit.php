<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'Trình sửa CSS Mobile app (inline editor)';

// Tên setting hiển thị trên trang Mobile app appearance.
$string['cssfilecontent']   = 'Nội dung file CSS (mobilecssurl)';
$string['editingfile']      = 'Đang sửa file: {$a}';
$string['description']      = 'Trình sửa CSS Mobile app (inline editor)';

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
<?php
// Every file should have GPL and copyright in the header - we skip it in tutorials but you should not skip it for real.

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

// A description shown in the admin theme selector.
$string['choosereadme'] = 'Theme th_lambda_st is a child theme of lambda.';
// The name of our plugin.
$string['pluginname'] = 'TH Lambda ST';
// We need to include a lang string for each block region.
$string['region-side-pre'] = 'Right';

$string['feedback_text'] = 'Error Report';
$string['feedback_type'] = 'Error type';
$string['select_feedback_type'] = '&lt;Select error report type&gt;';
$string['issue_description_placeholder'] = 'Describe the issue ...';
$string['btn_feedback'] = 'Submit';
$string['title_log_feedback_report'] = 'Error Report';
$string['completion_todo'] = 'Todo';
$string['completion_done'] = 'Done';
$string['grade_ielts'] = 'Grade Ielts';
$string['loginheading'] = 'Log in to {$a}';
$string['text_mobile_color'] = 'Text color for mobile devices';
$string['text_mobile_color_desc'] = 'Set the text color for mobile applications.';

$string['mobilecssurl'] = 'Mobile App CSS URL';
$string['mobilecssurl_desc'] = 'Nhập URL file CSS riêng dùng cho ứng dụng di động (Moodle App) của công ty này. '
    . 'Nếu để trống, hệ thống sẽ dùng CSS mặc định (Site administration > Mobile app > Appearance).';
 
$string['mobilecssheading'] = 'Mobile App CSS';
$string['cssfilecontent']    = 'Nội dung file CSS (Mobile App)';
$string['cssfilecontent_desc'] = 'Xem và chỉnh sửa trực tiếp nội dung file CSS ứng với URL đã nhập ở trên. '
    . 'Chỉ khả dụng khi URL trỏ tới 1 file nằm trong mã nguồn Moodle (cùng domain wwwroot hoặc đường dẫn tương đối).';
$string['editingcssfile']    = 'Đang sửa file: {$a}';
$string['nolocalcssfile']    = 'URL đã nhập không trỏ tới file nằm trong mã nguồn Moodle (ví dụ URL ở domain/CDN khác) nên không thể sửa trực tiếp nội dung tại đây.';
$string['cannotwritecss']    = 'Không tìm thấy file CSS để ghi.';
$string['notwritablecss']    = 'File tồn tại nhưng webserver không có quyền ghi (kiểm tra quyền file trên ổ đĩa).';
$string['writefailedcss']    = 'Ghi file thất bại.';
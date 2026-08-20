<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    require_once($CFG->dirroot . '/local/mobilecssedit/locallib.php');

    // Trang tổng quan: danh sách company + link/tạo/sửa/đổi tên file CSS
    // cho từng company - đây là nơi DUY NHẤT để xem/sửa nội dung CSS,
    // không còn nhúng editor vào trang theme_th_lambda_st_company_colors
    // như trước nữa.
    // Xuất hiện tại Site administration > Plugins > Local plugins.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_mobilecssedit_managecss',
        get_string('managecss', 'local_mobilecssedit'),
        new moodle_url('/local/mobilecssedit/managecss.php'),
        'local/mobilecssedit:manage'
    ));
}
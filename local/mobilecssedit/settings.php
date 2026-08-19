<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    require_once($CFG->dirroot . '/local/mobilecssedit/classes/admin_setting_cssfile.php');
    require_once($CFG->dirroot . '/local/mobilecssedit/classes/admin_setting_createcssfile.php');
    require_once($CFG->dirroot . '/local/mobilecssedit/locallib.php');

    // Trang tổng quan: danh sách company + link/tạo file CSS cho từng company.
    // Xuất hiện tại Site administration > Plugins > Local plugins.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_mobilecssedit_managecss',
        get_string('managecss', 'local_mobilecssedit'),
        new moodle_url('/local/mobilecssedit/managecss.php'),
        'local/mobilecssedit:manage'
    ));

    $page = $ADMIN->locate('theme_th_lambda_st_company_colors');

    $context = context_system::instance();
    $cancss  = has_capability('local/mobilecssedit:manage', $context);

    if ($page && $cancss) {
        $csspath   = local_mobilecssedit_get_css_path();
        $companyid = local_mobilecssedit_get_current_companyid();

        if ($csspath) {
            $page->add(new local_mobilecssedit_admin_setting_cssfile(
                'local_mobilecssedit/cssfilecontent',
                get_string('cssfilecontent', 'local_mobilecssedit'),
                get_string('description', 'local_mobilecssedit'),
                $csspath
            ));
        } else if ($companyid) {
            // Chưa có file cục bộ hợp lệ (chưa cấu hình mobilecssurl, hoặc có
            // cấu hình nhưng file chưa tồn tại trên đĩa) -> cho phép nhập URL
            // để tự tạo file mới.
            $page->add(new admin_setting_heading(
                'local_mobilecssedit/nofile',
                '',
                get_string('nolocalfile', 'local_mobilecssedit')
            ));

            $page->add(new local_mobilecssedit_admin_setting_createcssfile(
                'local_mobilecssedit/createcssfile',
                get_string('createcssfile', 'local_mobilecssedit'),
                get_string('createcssfile_desc', 'local_mobilecssedit'),
                $companyid
            ));
        } else {
            // Không xác định được company đang chỉnh sửa -> không có gì để tạo.
            $page->add(new admin_setting_heading(
                'local_mobilecssedit/nofile',
                '',
                get_string('nolocalfile', 'local_mobilecssedit')
            ));
        }
    } else if ($page) {
        // Có trang company nhưng KHÔNG có capability riêng của plugin
        // -> chỉ hiển thị thông báo, không cho thao tác.
        $page->add(new admin_setting_heading(
            'local_mobilecssedit/nopermission',
            '',
            get_string('nopermission', 'local_mobilecssedit')
        ));
    }
}
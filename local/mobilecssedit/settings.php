<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    require_once($CFG->dirroot . '/local/mobilecssedit/classes/admin_setting_cssfile.php');
    require_once($CFG->dirroot . '/local/mobilecssedit/locallib.php');

    $page = $ADMIN->locate('theme_th_lambda_st_company_colors');

    $context = context_system::instance();
    $cancss  = has_capability('local/mobilecssedit:manage', $context);

    if ($page && $cancss) {
        $csspath = local_mobilecssedit_get_css_path();

        if ($csspath) {
            $page->add(new local_mobilecssedit_admin_setting_cssfile(
                'local_mobilecssedit/cssfilecontent',
                get_string('cssfilecontent', 'local_mobilecssedit'),
                get_string('description', 'local_mobilecssedit'),
                $csspath
            ));
        } else {
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
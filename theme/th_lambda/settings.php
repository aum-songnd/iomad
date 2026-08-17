<?php

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // Trang cài đặt chính của theme.
    $settings = new admin_settingpage('themesettingth_lambda',
        get_string('configtitle', 'theme_th_lambda'));

    // === Nhóm: Cài đặt Quiz ===.
    $settings->add(new admin_setting_heading(
        'theme_th_lambda_quizheading',
        get_string('quizsettings', 'theme_th_lambda'),
        ''
    ));

    // Checkbox: Cho phép copy trong câu hỏi shortanswer.
    $name = 'theme_th_lambda/allowcopyshortanswer';
    $title = get_string('allowcopyshortanswer', 'theme_th_lambda');
    $description = get_string('allowcopyshortanswer_desc', 'theme_th_lambda');
    $default = 0; // 0 = không cho phép (mặc định); 1 = cho phép.

    $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
    $settings->add($setting);
}

<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Thêm link cấu hình vào khối "Khu vực quản trị" của Bài kiểm tra.
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 */
function local_th_quizcluster_extend_settings_navigation(settings_navigation $settingsnav, context $context): void {
    global $CFG;

    // Chỉ làm việc ở cấp context module.
    if (!$context instanceof context_module) {
        return;
    }

    // Lấy thông tin course module từ context.
    require_once($CFG->dirroot . '/course/lib.php');

    // context->instanceid chính là cmid.
    $cmid = $context->instanceid;

    // Chỉ áp dụng cho module quiz.
    $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING);
    if (!$cm) {
        return;
    }

    // Tìm node "Cài đặt" của module trong khối Khu vực quản trị.
    $quiznode = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$quiznode) {
        return;
    }

    // URL tới trang cấu hình của plugin cho bài quiz này.
    $url = new moodle_url('/local/th_quizcluster/quizsettings.php', ['id' => $cmid]);

    // Thêm node con.
    $quiznode->add(
        get_string('clustergradesettings', 'local_th_quizcluster'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_th_quizcluster',
        new pix_icon('i/settings', '')
    );

    // 2) ✅ Link cấu hình random theo tag (mới).
    $randomurl = new moodle_url('/local/th_quizcluster/randomsettings.php', ['id' => $cmid]);
    $quiznode->add(
        get_string('clusterrandomsettings', 'local_th_quizcluster'),
        $randomurl,
        navigation_node::TYPE_SETTING,
        null,
        'local_th_quizcluster_randomsettings',
        new pix_icon('i/filter', '') // hoặc 'i/settings'
    );
}

<?php

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_th_customstappapi', get_string('pluginname', 'local_th_customstappapi'));
	$ADMIN->add('localplugins', $settings);

    $configs = array();

    $configs[] = new admin_setting_configtext('local_th_customstappapi/config_averagescore',
    new lang_string('averagescore_shortname_setting', 'local_th_customstappapi'),
    new lang_string('averagescore_shortname_setting_description', 'local_th_customstappapi'), 'trinhdotrungbinh', PARAM_TEXT);

    $configs[] = new admin_setting_configtext('local_th_customstappapi/idletime',
    new lang_string('idletime_setting', 'local_th_customstappapi'),
    new lang_string('des_idletime_setting', 'local_th_customstappapi'), 600, PARAM_INT);

    $configs[] = new admin_setting_configtext('local_th_customstappapi/entrance_cate',
    new lang_string('entrance_cate_setting', 'local_th_customstappapi'),
    new lang_string('des_entrance_cate_setting', 'local_th_customstappapi'), 'thidauvao', PARAM_TEXT);

    $configs[] = new admin_setting_configtext('local_th_customstappapi/trial_test_cate',
    new lang_string('trial_test_cate_setting', 'local_th_customstappapi'),
    new lang_string('des_trial_test_cate_setting', 'local_th_customstappapi'), 'phongtrainghiem', PARAM_TEXT);

    //add config
	foreach ($configs as $config) {
		$settings->add($config);
	}
}
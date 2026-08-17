<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     local_th_config_login
 * @category    admin
 * @copyright   2024 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_th_config_login', get_string('pluginname', 'local_th_config_login'));
    $ADMIN->add('localplugins', $settings);

    $configs = array();

    $configs[] = new admin_setting_configmultiselect('local_th_config_login/selectsecret',
        get_string('selectsecret', 'local_th_config_login'),
        get_string('selectsecretdesc', 'local_th_config_login'),
        ['recaptcha', 'cloudflare', 'email'], 
        [
            'cloudflare' => 'Cloudflare Turnstile', 
            'recaptcha' => 'Google reCaptcha', 
            'email' => 'Email', 
            // 'sms' => 'SMS'
        ]
    );
    $configs[] = new admin_setting_configtext('directory', new lang_string('directory', 'local_th_config_login'), new lang_string('directory_desc', 'local_th_config_login'), 'logins');

    $configs[] = new admin_setting_configduration('local_th_config_login/timeexpiry',
                get_string('timeexpiry', 'local_th_config_login'),
                get_string('timeexpiry_desc', 'local_th_config_login'),
                30 * MINSECS);
    $configs[] = new admin_setting_configduration('local_th_config_login/expire_option',
                get_string('option_expire', 'local_th_config_login'),
                get_string('option_expire_desc', 'local_th_config_login'),
                WEEKSECS);
    $configs[] = new admin_setting_configtext('sitekey', new lang_string('cloudflarepublickey', 'local_th_config_login'), new lang_string('configcloudflaresitekey', 'local_th_config_login'), '', PARAM_NOTAGS, 40);

    $configs[] = new admin_setting_configtext('secretkey', new lang_string('cloudflareprivatekey', 'local_th_config_login'), new lang_string('configcloudflaresecretkey', 'local_th_config_login'), '', PARAM_NOTAGS, 40);


    foreach ($configs as $config) {
        $config->plugin = 'local_th_config_login';
        $settings->add($config);
    }
}

<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Global configuration settings for the thquizaccess_seb plugin.
 *
 * @package    thquizaccess_seb
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $ADMIN;

if ($hassiteconfig) {

    $settings->add(new admin_setting_heading(
        'thquizaccess_seb/supportedversions',
        '',
        $OUTPUT->notification(get_string('setting:supportedversions', 'thquizaccess_seb'), 'warning')));

    $settings->add(new admin_setting_configcheckbox('thquizaccess_seb/autoreconfigureseb',
        get_string('setting:autoreconfigureseb', 'thquizaccess_seb'),
        get_string('setting:autoreconfigureseb_desc', 'thquizaccess_seb'),
        '1'));

    $links = [
        'seb' => get_string('setting:showseblink', 'thquizaccess_seb'),
        'http' => get_string('setting:showhttplink', 'thquizaccess_seb')
    ];
    $settings->add(new admin_setting_configmulticheckbox('thquizaccess_seb/showseblinks',
        get_string('setting:showseblinks', 'thquizaccess_seb'),
        get_string('setting:showseblinks_desc', 'thquizaccess_seb'),
        $links, $links));

    $settings->add(new admin_setting_configtext('thquizaccess_seb/downloadlink',
        get_string('setting:downloadlink', 'thquizaccess_seb'),
        get_string('setting:downloadlink_desc', 'thquizaccess_seb'),
        'https://safeexambrowser.org/download_en.html',
        PARAM_URL));

    $settings->add(new admin_setting_configcheckbox('thquizaccess_seb/thquizpasswordrequired',
        get_string('setting:thquizpasswordrequired', 'thquizaccess_seb'),
        get_string('setting:thquizpasswordrequired_desc', 'thquizaccess_seb'),
        '0'));

    $settings->add(new admin_setting_configcheckbox('thquizaccess_seb/displayblocksbeforestart',
        get_string('setting:displayblocksbeforestart', 'thquizaccess_seb'),
        get_string('setting:displayblocksbeforestart_desc', 'thquizaccess_seb'),
        '0'));

    $settings->add(new admin_setting_configcheckbox('thquizaccess_seb/displayblockswhenfinished',
        get_string('setting:displayblockswhenfinished', 'thquizaccess_seb'),
        get_string('setting:displayblockswhenfinished_desc', 'thquizaccess_seb'),
        '1'));
}

if (has_capability('thquizaccess/seb:managetemplates', context_system::instance())) {
    $ADMIN->add('modsettingsthquizcat',
        new admin_externalpage(
            'thquizaccess_seb/template',
            get_string('manage_templates', 'thquizaccess_seb'),
            new moodle_url('/mod/thquiz/accessrule/seb/template.php'),
            'thquizaccess/seb:managetemplates'
        )
    );
}

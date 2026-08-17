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
 * @package     qtype_thvstepcluster
 * @category    admin
 * @copyright   phamleminh1812@gmail.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('qtype_thvstepcluster_settings', new lang_string('pluginname', 'qtype_thvstepcluster'));

    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        // TODO: Define actual plugin settings page and add it to the tree - {@link https://docs.moodle.org/dev/Admin_settings}.
        $settings->add(
        new admin_setting_configtext(
            'qtype_thvstepcluster/th_question',
            get_string('shortname'),
            get_string('shortname'),
            'th_question',
            PARAM_TEXT,
            20)
        );

        $settings->add(new admin_setting_heading('headingurldrm', get_string('setting_drm', 'qtype_thvstepcluster'), ''));
        $options = array(
            '0' => get_string('no_drm', 'qtype_thvstepcluster'),
            '1' => get_string('use_drm', 'qtype_thvstepcluster') 
        );
        $settings->add(new admin_setting_configselect('qtype_thvstepcluster/globalusedrm',
            get_string('use_drm_setting', 'qtype_thvstepcluster'), get_string('drm_desc', 'qtype_thvstepcluster'), 0, $options));
        $settings->add(new admin_setting_configtext('qtype_thvstepcluster/urlDRM', get_string('drm_url', 'qtype_thvstepcluster'), get_string('drm_url', 'qtype_thvstepcluster'), '', PARAM_URL, 30));
    }
}

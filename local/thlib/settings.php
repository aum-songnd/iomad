<?php
// This file is part of local_thlib for Moodle - http://moodle.org/
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
 * Settings page
 *
 * @package       local_thlib
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Simeon Naydenov (moniNaydenov@gmail.com)
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig/*$ADMIN->fulltree*/) {

	$settings = new admin_settingpage('local_thlib', get_string('pluginname', 'local_thlib'));
	$ADMIN->add('localplugins', $settings);

	$configs = array();

	$values = array();
	$values[] = get_string('lastname');
	$values[] = get_string('firstname');

	$configs[] = new admin_setting_configselect('local_thlib/sortorder',
		get_string('sortby', 'local_thlib'),
		get_string('sortbydesc', 'local_thlib'),
		$values[0],
		$values
	);

	$configs[] = new admin_setting_configtext('local_thlib/enrollmentcourseshortname',
		get_string('enrollmentcourseshortname', 'local_thlib'),
		get_string('enrollmentcoursedesc', 'local_thlib'),
		'', PARAM_TEXT);

	$configs[] = new admin_setting_configtext('local_thlib/classcodeshortname',
		get_string('classshortname', 'local_thlib'),
		get_string('classshortnamedesc', 'local_thlib'),
		'', PARAM_TEXT);

	$configs[] = new admin_setting_configtext('local_thlib/studentcodeshortname',
		get_string('studentcodeshortname', 'local_thlib'),
		get_string('studentcodeshortnamedesc', 'local_thlib'),
		'', PARAM_TEXT);

	$configs[] = new admin_setting_configtext('local_thlib/custom_fields1',
		get_string('customfields1', 'local_thlib'),
		get_string('customfieldsdesc1', 'local_thlib'),
		'', PARAM_TEXT);

	$configs[] = new admin_setting_configtext('local_thlib/custom_fields2',
		get_string('customfields2', 'local_thlib'),
		get_string('customfieldsdesc2', 'local_thlib'),
		'', PARAM_TEXT);

	$configs[] = new admin_setting_configtext('local_thlib/technical_hotline',
		get_string('technical_hotline', 'local_thlib'),
		get_string('technical_hotlinedesc', 'local_thlib'),
		'', PARAM_TEXT);

	$configs[] = new admin_setting_configtext('local_thlib/viewcourseinfo_shortname',
		get_string('viewcourseinfoshortname', 'local_thlib'),
		get_string('viewcourseinfoshortname', 'local_thlib'),
		'', PARAM_TEXT);

	foreach ($configs as $config) {
		$config->plugin = 'local_thlib';
		$settings->add($config);
	}

}

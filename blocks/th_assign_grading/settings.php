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
 * Settings for the th_course_unenrollment_report block
 *
 * @package    block_th_activatecourses
 * @copyright  2019 Tom Dickman <tomdickman@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {  
	$settings = new admin_settingpage('block_th_assign_grading', new lang_string('pluginname', 'block_th_assign_grading')); 

	if ($ADMIN->fulltree) {

		$configs[] = new admin_setting_configtext('block_th_assign_grading/th_crm_code', 
		new lang_string('crm', 'block_th_assign_grading'), 
		new lang_string('des_crm', 'block_th_assign_grading'), 'th_crm_code', PARAM_TEXT, 15);

		$configs[] = new admin_setting_configtext('block_th_assign_grading/role_grading', 
		new lang_string('role_grading', 'block_th_assign_grading'),
		new lang_string('des_role_grading', 'block_th_assign_grading'), 'ctv_marking', PARAM_TEXT, 30);
		
		//add config 
		foreach ($configs as $config) { 
			$settings->add($config); 
		} 
	} 
	
}

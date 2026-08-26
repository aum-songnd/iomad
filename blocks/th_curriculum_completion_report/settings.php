<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {  
	$settings = new admin_settingpage('block_th_curriculum_completion_report', new lang_string('pluginname', 'block_th_curriculum_completion_report')); 
	
	if ($ADMIN->fulltree) {

		$configs[] = new admin_setting_configtext('block_th_curriculum_completion_report/th_crm_code', 
		new lang_string('crm', 'block_th_curriculum_completion_report'), 
		new lang_string('des_crm', 'block_th_curriculum_completion_report'), 'th_crm_code', PARAM_TEXT, 15); 
				
		$configs[] = new admin_setting_configtext('block_th_curriculum_completion_report/th_student_cohort', 
		new lang_string('student_cohort_setting', 'block_th_curriculum_completion_report'), 
		new lang_string('des_student_cohort_setting', 'block_th_curriculum_completion_report'), 'th_student_cohort', PARAM_TEXT, 15);

		$configs[] = new admin_setting_configtext('block_th_curriculum_completion_report/th_student_class', 
		new lang_string('student_class_setting', 'block_th_curriculum_completion_report'), 
		new lang_string('des_student_class_setting', 'block_th_curriculum_completion_report'), 'th_student_class', PARAM_TEXT, 15);

		$configs[] = new admin_setting_configtext('block_th_curriculum_completion_report/th_dob', 
		new lang_string('dob_setting', 'block_th_curriculum_completion_report'), 
		new lang_string('des_dob_setting', 'block_th_curriculum_completion_report'), 'th_dob', PARAM_TEXT, 15);

		$configs[] = new admin_setting_configtext('block_th_curriculum_completion_report/config_ktck',
		new lang_string('ktck_shortname_setting', 'block_th_curriculum_completion_report'),
		new lang_string('ktck_shortname_setting_description', 'block_th_curriculum_completion_report'), 'ktck', PARAM_TEXT);
		
		$configs[] = new admin_setting_configtext('block_th_curriculum_completion_report/config_point',
		new lang_string('point_setting', 'block_th_curriculum_completion_report'),
		new lang_string('point_setting_description', 'block_th_curriculum_completion_report'), 5, PARAM_INT);
		
		//add config 
		foreach ($configs as $config) { 
			$settings->add($config); 
		} 
	} 
	
}

?>

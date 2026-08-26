<?php

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/local/thlib/lib.php';


class th_assign_turns_form extends moodleform {
	public function definition() {
		global $CFG, $DB;

		$mform = $this->_form;
        $companyid = $this->_customdata['companyid'] ?? 0;

		$mform->addElement('hidden', 'id');
		$mform->setType('id', PARAM_RAW);

		$users = $DB->get_records_sql("
			SELECT u.* 
			FROM {user} u
			JOIN {company_users} cu ON cu.userid = u.id
			WHERE 
				u.id <> 1 AND 
				u.deleted = 0 AND 
				u.suspended = 0 AND 
				u.username != 'admin' 
				AND cu.companyid = :companyid
			 ORDER BY u.firstname, u.lastname",
			['companyid' => $companyid]
		);
		$user_arr = array();
		$user_arr = ["" => ""];
		foreach ($users as $userid => $user) {
			$user_arr[$userid] = fullname($user) . ', ' . $user->username . ', ' . $user->email;
		}

		$options_user = array(
			'multiple' => false,
			'noselectionstring' => 'Chọn một học viên',
		);
		$mform->addElement('autocomplete', 'userid',  'Chọn học viên', $user_arr, $options_user);
		$mform->addRule('userid', '', 'required', null, 'client', false, false);

		$sql = "SELECT id,fullname,shortname 
			FROM {course} 
			WHERE summaryformat=1 AND visible =1";

		$courses = $DB->get_records_sql($sql);
		$course_arr = array('' => '');
		foreach ($courses as $course) {
			$course_arr[$course->id] = $course->fullname . ', ' . $course->shortname;
		}
		
        $mform->addElement('text', 'count', 'Nhập số lượt chấm');
        $mform->setDefault('count', '');
        $mform->setType('count', PARAM_INT);
        $mform->addRule('count', 'Nhập số lượt chấm là số nguyên', 'numeric', null, 'client');
        $mform->addRule('count', '', 'required', null, 'client', false, false);
        $mform->addRule('count', 'Nhập số lượt chấm là số nguyên', 'regex', '/^[0-9]+$/', 'client');

		$this->add_action_buttons(true, get_string('submit'));
	}
}

<?php

require_once "{$CFG->libdir}/formslib.php";
require_once $CFG->dirroot . '/local/thlib/lib.php';

function get_profile_data($shortname, $companyid = 0) {

	global $DB;

	$shortnamearr = explode(",", $shortname);
	if (count($shortnamearr) > 0) {
		list($insql, $inparams) = $DB->get_in_or_equal($shortnamearr);
	} else {
		list($insql, $inparams) = $DB->get_in_or_equal(['']);
	}
	if($companyid > 0){
		$sql = "SELECT distinct {user_info_data}.*
				from {user}
				inner join {user_info_data}
				on {user_info_data}.userid = {user}.id
				inner join {user_info_field}
				on {user_info_data}.fieldid = {user_info_field}.id
				join {company_users} cu on cu.userid = {user}.id
				where {user_info_field}.shortname $insql
				and cu.companyid = $companyid
				and {user_info_data}.data <> ''
				group by {user_info_data}.data";
	}else{
		$sql = "SELECT distinct {user_info_data}.*
				from {user}
				inner join {user_info_data}
				on {user_info_data}.userid = {user}.id
				inner join {user_info_field}
				on {user_info_data}.fieldid = {user_info_field}.id
				where {user_info_field}.shortname $insql
				and {user_info_data}.data <> ''
				group by {user_info_data}.data";
	}

	return $DB->get_records_sql($sql, $inparams);
}

class th_form extends moodleform {

	function definition() {
		global $DB, $COURSE;
		$mform = $this->_form;

		$mform->addElement('header', 'displayinfo', get_string('textfields', 'local_thlib'));
		$mform = $this->_form;

		$this->add_show_option_radio();

		$this->add_makhoa_malop_user_filter();

		$this->add_from_to_datetime();

		$this->add_action_buttons(false, get_string("submmit", 'local_thlib'));
	}

	function add_user_status_option_radio() {
		$mform = $this->_form;
		$radioarray = array();
		$radioarray[] = &$mform->createElement('radio', 'user_status', '', get_string('radio_all', 'local_thlib'), 0);
		$radioarray[] = &$mform->createElement('radio', 'user_status', '', get_string('radio_active', 'local_thlib'), 1);
		$radioarray[] = &$mform->createElement('radio', 'user_status', '', get_string('radio_suppend', 'local_thlib'), 2);

		$element = $mform->addGroup($radioarray, 'user_status', get_string('searchoption', 'local_thlib'), array(''), false);
		$attributes = $element->_attributes = ['class' => 'custom_required'];
		$element->setAttributes($attributes);
	}

	function add_show_option_radio() {
		$mform = $this->_form;
		$radioarray = array();
		$radioarray[] = &$mform->createElement('radio', 'show_option', '', get_string('radiomakhoa', 'local_thlib'), 0);
		$radioarray[] = &$mform->createElement('radio', 'show_option', '', get_string('radiomalop', 'local_thlib'), 1);
		$radioarray[] = &$mform->createElement('radio', 'show_option', '', get_string('radiouser', 'local_thlib'), 2);

		$mform->addGroup($radioarray, 'show_option', get_string('searchoption', 'local_thlib'), array(''), false);
		$mform->setDefault('show_option', 0);
	}

	function add_from_to_datetime() {
		$mform = $this->_form;
		$mform->addElement('date_selector', 'time_from', get_string('fromdate', 'local_thlib'));
		$mform->addElement('date_selector', 'time_to', get_string('todate', 'local_thlib'));
	}

	function add_makhoa_malop_user_filter() {
		$mform = $this->_form;

		$config = get_config('local_thlib');
		$sortorder = "lastname,firstname";
		if ($config->sortorder == 1) {
			$sortorder = "firstname,lastname";
		}

		$enrollmentcourseshortname = trim($config->enrollmentcourseshortname);
		$classcodeshortname = trim($config->classcodeshortname);

		$this->makhoaarr = get_profile_data($enrollmentcourseshortname);
		$this->maloparr = get_profile_data($classcodeshortname);

		// Ma Khoa Filter
		$choice = array();
		$choice[''] = '';
		foreach ($this->makhoaarr as $key => $value) {
			$choice[$value->id] = $value->data;
		}

		$options = array(
			'multiple' => false,
			'noselectionstring' => get_string('no_selection', 'local_thlib'),
		);

		$element = $mform->addElement('autocomplete', 'makhoaid', get_string('makhoa', 'local_thlib'), $choice, $options, array('classs' => 'cohort-cohort'));
		$attributes = $element->getAttributes() + ['id' => 'myid', 'class' => 'custom_required'];

		$element->setAttributes($attributes);
		// Ma lop filter
		$choice = array();
		$choice[''] = '';
		foreach ($this->maloparr as $key => $value) {
			$choice[$value->id] = $value->data;
		}

		$options = array(
			'multiple' => false,
			'noselectionstring' => get_string('no_selection', 'local_thlib'),
		);

		$element = $mform->addElement('autocomplete', 'malopid', get_string('malop', 'local_thlib'), $choice, $options, array('classs' => 'cohort-cohort'));
		$attributes = $element->getAttributes() + ['class' => 'custom_required'];
		$element->setAttributes($attributes);
		// User filter
		$this->user_arr = get_userid_form($mform, $sortorder);

		$mform->disabledIf('makhoaid', 'show_option', 'neq', '0');
		$mform->disabledIf('malopid', 'show_option', 'neq', '1');
		$mform->disabledIf('userid', 'show_option', 'neq', '2');

		$mform->hideif('makhoaid', 'show_option', 'neq', '0');
		$mform->hideif('malopid', 'show_option', 'neq', '1');
		$mform->hideif('userid', 'show_option', 'neq', '2');
	}

	function validation($data, $files) {
		return $this->validation_makhoa_malop_user($data, $files);
	}

	function validation_makhoa_malop_user($data, $files) {
		if ($data['show_option'] == 0 && empty($data['makhoaid'])) {
			return array('makhoaid' => get_string('err_required', 'form'));
		}

		if ($data['show_option'] == 1 && empty($data['malopid'])) {
			return array('malopid' => get_string('err_required', 'form'));
		}

		if ($data['show_option'] == 2 && empty($data['userid'])) {
			return array('userid' => get_string('err_required', 'form'));
		}
	}
}

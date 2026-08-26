<?php

require_once $CFG->dirroot . '/lib/formslib.php';
require_once $CFG->dirroot . '/local/thlib/lib.php';

class th_assign_turn_upload_form extends moodleform {

	function definition() {
		global $COURSE, $CFG;
		$mform = $this->_form;

		$link = "<a href='example.xlsx'>example.xlsx</a>";
		$mform->addElement('static', 'example', 'Example', $link);
		// Reuse the same options.
		$mform->addElement('filepicker', 'file', get_string('file'), null,
			array(
				'accepted_types' => array('.xlsx'),
				'areamaxbytes' => 100000000,
				'maxfiles' => 1,
			)
		);
		$mform->addRule('file', null, 'required', null, 'client');
		$this->add_action_buttons(true, get_string('submit'));
	}
}

class confirm_form extends moodleform {

	protected function definition() {
		global $SESSION;

		$th_assign_turn_upload_key = $this->_customdata['th_assign_turn_upload_key'];

		$mform = $this->_form;

		$mform->addElement('hidden', 'key');
		$mform->setType('key', PARAM_RAW);
		$mform->setDefault('key', $th_assign_turn_upload_key);

		// Check if we want to show the enrol user button.
		$showenrolebutton = true;
		$check = null;
		if (isset($SESSION->local_th_assign_grading) && array_key_exists($th_assign_turn_upload_key, $SESSION->local_th_assign_grading)) {
			$check = $SESSION->local_th_assign_grading[$th_assign_turn_upload_key];
			
			if (empty($check['valid'])) {
				$showenrolebutton = false;
			}
		}

		// Only show the enrol user button if necessary.
		if ($showenrolebutton) {

			$buttonstring = 'Gửi';

			$this->add_action_buttons(true, $buttonstring);
		}
	}
}
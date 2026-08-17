<?php

defined('MOODLE_INTERNAL') || die();

require_once "$CFG->libdir/formslib.php";

class question_update_form extends moodleform {
	//Add elements to form
	public function definition() {
		global $DB;
		$mform = $this->_form;
		$this->add_action_buttons(false, get_string('submit'));

	}
}
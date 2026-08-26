<?php

class block_th_import_audio extends block_base {
	public function init() {
		$this->title = get_string('title', 'block_th_import_audio');
	}
	// The PHP tag and the curly bracket for the class definition
	// will only be closed after there is another function added in the next section.

	public function get_content() {
		if ($this->content !== null) {
			return $this->content;
		}
		$this->content = new stdClass;
		global $COURSE;
		$context = context_course::instance($COURSE->id);
		if (has_capability('block/th_import_audio:addinstance', $context)) {
			$this->content->text = get_string("content", 'block_th_import_audio');
			$url = new moodle_url('/blocks/th_import_audio/view.php');
			$url1 = new moodle_url('/blocks/th_import_audio/log_import_audio.php');
			$this->content->footer = html_writer::link($url, get_string('reportlink', 'block_th_import_audio')) . '</br>' . html_writer::link($url1, 'Log import audio');
		} else {
			$this->content->footer = get_string('nopermission', 'block_th_import_audio');
		}
		return $this->content;
	}

	public function applicable_formats() {
		return array(
			'my' => true,
			'site-index' => true,
		);
	}
}
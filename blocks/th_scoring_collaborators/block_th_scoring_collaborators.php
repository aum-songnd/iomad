<?php

class block_th_scoring_collaborators extends block_base {
	public function init() {
		$this->title = get_string('title', 'block_th_scoring_collaborators');
	}

	public function get_content() {
		global $CFG;
		if ($this->content !== null) {
			return $this->content;
		}
		$this->content = new stdClass;
		global $COURSE;
		$context = context_course::instance($COURSE->id);
		if (has_capability('block/th_scoring_collaborators:view', $context)) {
			$url = new moodle_url('/blocks/th_scoring_collaborators/view.php');
			$this->content->footer = html_writer::link($url, get_string('list_of_tests_to_grade', 'block_th_scoring_collaborators'));
		} else {
			$this->content->footer = get_string('no_permission', 'block_th_scoring_collaborators');
		}
		return $this->content;
	}
}
?>

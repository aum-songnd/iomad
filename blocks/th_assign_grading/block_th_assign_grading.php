<?php

class block_th_assign_grading extends block_base {
	public function init() {
		$this->title = get_string('title', 'block_th_assign_grading');
	}

	function has_config() {
		return true;
	}

	public function get_content() {
		global $CFG;
		if ($this->content !== null) {
			return $this->content;
		}
		$this->content = new stdClass;
		global $COURSE;
		$context = context_course::instance($COURSE->id);
		if (has_capability('block/th_assign_grading:view', $context)) {
			$url = new moodle_url('/blocks/th_assign_grading/view.php');
			$this->content->footer = html_writer::link($url, 'Quản lý phân chia công việc cho CTV');
		} else {
			$this->content->footer = 'No Permission!';
		}
		return $this->content;
	}
}
?>

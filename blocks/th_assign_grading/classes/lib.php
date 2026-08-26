<?php

define('BLOCKth_bulkactivatecourse_HINT', 'hint');
define('BLOCKth_bulkactivatecourse_ENROLUSERS', 'enrolusers');

function block_th_assign_grading_controls(context $context, moodle_url $currenturl) {
	$tabs = array();
	$currenttab = 'view';
	$view = new moodle_url('/blocks/th_assign_grading/view.php');

	if (has_capability('block/th_assign_grading:view', $context)) {
		$addurl = new moodle_url('/blocks/th_assign_grading/view.php');
		$tabs[] = new tabobject('view', $addurl, 'Báo cáo gán chấm chữa cho CTV');
		if ($currenturl->get_path() === $addurl->get_path()) {
			$currenttab = 'view';
		}
	}
	if (has_capability('block/th_assign_grading:view', $context)) {
		$addurl = new moodle_url('/blocks/th_assign_grading/edit.php');
		$tabs[] = new tabobject('edit', $addurl, 'Gán chấm chữa cho CTV');
		if ($currenturl->get_path() === $addurl->get_path()) {
			$currenttab = 'edit';
		}
	}
	if (has_capability('block/th_assign_grading:view', $context)) {
		$addurl = new moodle_url('/blocks/th_assign_grading/index.php');
		$tabs[] = new tabobject('index', $addurl, 'Báo cáo gán lượt chấm cho học viên');
		if ($currenturl->get_path() === $addurl->get_path()) {
			$currenttab = 'index';
		}
	}
	if (has_capability('block/th_assign_grading:view', $context)) {
		$addurl = new moodle_url('/blocks/th_assign_grading/assign_turns.php');
		$tabs[] = new tabobject('assign_turns', $addurl, 'Gán lượt chấm thủ công');
		if ($currenturl->get_path() === $addurl->get_path()) {
			$currenttab = 'assign_turns';
		}
	}
	if (has_capability('block/th_assign_grading:view', $context)) {
		$addurl = new moodle_url('/blocks/th_assign_grading/upload.php');
		$tabs[] = new tabobject('upload_assign_turns', $addurl, 'Gán lượt chấm hàng loạt');
		if ($currenturl->get_path() === $addurl->get_path()) {
			$currenttab = 'upload_assign_turns';
		}
	}
	if (has_capability('block/th_assign_grading:view', $context)) {
		$addurl = new moodle_url('/blocks/th_assign_grading/grading_progress.php');
		$tabs[] = new tabobject('grading_progress', $addurl, 'Báo cáo tiến độ chấm bài của CTV');
		if ($currenturl->get_path() === $addurl->get_path()) {
			$currenttab = 'grading_progress';
		}
	}
	if (count($tabs) > 1) {
		return new tabtree($tabs, $currenttab);
	}
	return null;
}
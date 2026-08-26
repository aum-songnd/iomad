<?php

function th_import_audio_list_courses($companyid = 0)
{
	global $DB;

	$listcourses = [];
	$listcourses[] = '';

	if ($companyid > 0) {
		// Lấy khóa học thuộc company
		$sql = "SELECT c.id, c.fullname 
                FROM {course} c
                JOIN {company_course} cc ON cc.courseid = c.id
                WHERE cc.companyid = :companyid 
                AND c.id != 1 
                ORDER BY c.fullname";
		$courses = $DB->get_records_sql($sql, ['companyid' => $companyid]);
	} else {
		// Lấy tất cả khóa học 
		$sql = "SELECT c.id, c.fullname 
                FROM {course} c
                WHERE c.id != 1
                ORDER BY c.fullname";
		$courses = $DB->get_records_sql($sql);
	}

	// Thêm khóa học vào danh sách
	if (!empty($courses)) {
		foreach ($courses as $id => $course) {
			$listcourses[$id] = $course->fullname;
		}
	}

	return $listcourses;
}

function th_display_table_import_audio($import_audio){
	global $DB;
	
	$table       = new html_table();
	$table->head = array(
		get_string('stt', 'block_th_import_audio'),
		get_string('audioname', 'block_th_import_audio'),
		get_string('questionname', 'block_th_import_audio'),
		get_string('questiontext', 'block_th_import_audio'),
		get_string('coursename', 'block_th_import_audio'),
		get_string('status', 'block_th_import_audio')
	);	
	$stt         = 0;

	foreach($import_audio as $k => $import) {
		$courseid = $import->courseid;
		$sql = "SELECT fullname FROM {course} WHERE id = $courseid";
		$fullname_course = $DB->get_field_sql($sql);

		$link_course = new moodle_url('/course/view.php', ['id' => $courseid]);
		$link = html_writer::link($link_course, $fullname_course);

		$stt            = $stt + 1;
		$row            = new html_table_row();
		$cell           = new html_table_cell($stt);
		$row->cells[]   = $cell;
		$cell           = new html_table_cell($import->audioname);
		$row->cells[]   = $cell;
		$cell           = new html_table_cell($import->filename);
		$row->cells[]   = $cell;
		$cell           = new html_table_cell($import->list_question->questiontext1);
		$row->cells[]   = $cell;
		$cell           = new html_table_cell($link);
		$row->cells[]   = $cell;
		$cell           = new html_table_cell();
		$cell->text = html_writer::tag('span',
			get_string('audioadded', 'block_th_import_audio'),
			array('class' => 'badge badge-success')
		);
		$row->cells[]   = $cell;
		$table->data[] = $row;
	}
	$table->attributes = array('class' => 'th_import_audio_table', 'border' => '1');
	$table->attributes['style'] = "width: 100%; text-align:center;";
	$html = html_writer::table($table);
	return $html;
}

function draftfile_exists($itemid, $filepath, $filename) {
	global $USER;
	
	$fs = get_file_storage();
	$usercontext = context_user::instance($USER->id);
	return $fs->file_exists($usercontext->id, 'user', 'draft', $itemid, $filepath, $filename);
}

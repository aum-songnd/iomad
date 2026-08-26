<?php

function get_malop_form1($mform) {

global $DB,$CFG;

$options = array(
    'multiple'          => true,
    'noselectionstring' => 'Chọn bài cần chấm',
    'ajax' => 'block_th_assign_grading/get_quiz',
    'perpage' => 100
);

$element = $mform->addElement('autocomplete', 'attempid', 'Chọn bài cần chấm', [], $options);
$element = $mform->addRule('attempid', '', 'required', null, 'client', false, false);

}

function th_send_display_table_error($errors) {
	$table1 = new html_table($errors);
	$table1->head = array('STT', 'Hints');
	$stt = 0;
	foreach ($errors as $k => $error) {
		$stt = $stt + 1;
		$row = new html_table_row();
		$cell = new html_table_cell($stt);
		$row->cells[] = $cell;
		$cell = new html_table_cell($error);
		$row->cells[] = $cell;
		$table1->data[] = $row;
	}
	$html1 = html_writer::table($table1);
	return $html1;
}


function th_display_table_valid_users($correct_data) {
	global $DB, $USER;
	$table = new html_table();
	$table->head = array('STT', 'username', 'Lượt chấm');
	$stt = 0;

	foreach ($correct_data as $k => $data) {

		$stt = $stt + 1;
		$row = new html_table_row();
		$cell = new html_table_cell($stt);
		$row->cells[] = $cell;
		$cell = new html_table_cell($data[0][0]);
		$row->cells[] = $cell;
		$cell = new html_table_cell($data[0][1]);
		$row->cells[] = $cell;
		$table->data[] = $row;
	}

	$table->attributes = array('class' => 'th_display_table_valid_users', 'border' => '1');
	$table->attributes['style'] = "width: 100%; text-align:center;";
	$html = html_writer::table($table);
	return $html;
}
?>
<?php

require_once '../../config.php';
require_once $CFG->libdir . '/adminlib.php';
require_once $CFG->dirroot . '/local/thlib/lib.php';
require_once $CFG->dirroot . '/local/thlib/th_form.php';
require_once 'th_import_audio_form.php';
require_once $CFG->dirroot . '/lib/filelib.php';
require_once "lib.php";

global $DB, $OUTPUT, $PAGE, $COURSE, $USER;

// === PHẦN CHECK COMPANY ===
$usercompany_id = 0;

if (!class_exists('iomad')) {
	print_error('iomadrequired', 'block_th_import_audio');
}

if (is_siteadmin()) { // ========== SITE ADMIN ==========
	$usercompany_id = iomad::get_my_companyid($USER->id);

	if (empty($usercompany_id)) {
		$firstcompany = $DB->get_record_sql(
			"SELECT id FROM {company} ORDER BY id ASC LIMIT 1"
		);
		$usercompany_id = $firstcompany->id ?? 0;
	}
} else { // ========== USER THƯỜNG ==========
	$usercompany = company::by_userid($USER->id);
	$usercompany_id = $usercompany->id ?? 0;
}
if (empty($usercompany_id)) {
	print_error('nocompany', 'block_th_import_audio');
}
// === KẾT THÚC PHẦN CHECK COMPANY ===

// Check for all required variables.
$courseid = $COURSE->id;
$delete = optional_param('delete', 0, PARAM_BOOL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$id = optional_param('id', 0, PARAM_INT);
$fileid = optional_param('fileid', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
	print_error('invalidcourse', 'block_th_import_audio', $courseid);
}

if(!has_capability('block/th_import_audio:view', context_course::instance($courseid))){
	$cap = 'block/th_import_audio:view';
	$courses = get_user_capability_course($cap, $USER->id, true, 'id, fullname, shortname', 'sortorder', 0, 0, true);
	
	// Nếu chỉ cần kiểm tra “có/không”:
	if (empty($courses)) {
		print_error('nopermissions', 'error', '', $cap);
	} else {
		$first = reset($courses);
		$courseid = $first->id;
		require_login();
		require_capability('block/th_import_audio:view', context_course::instance($courseid));
	}
} else {
	require_login($courseid);
	require_capability('block/th_import_audio:view', context_course::instance($courseid));
}

$pageurl = '/blocks/th_import_audio/log_import_audio.php';
$title = get_string('logimportaudio', 'block_th_import_audio');
$PAGE->set_url('/blocks/th_import_audio/log_import_audio.php');
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('logimportaudio', 'block_th_import_audio'));
$PAGE->set_title($SITE->fullname . ': ' . get_string('logimportaudio', 'block_th_import_audio'));

$editurl = new moodle_url('/blocks/th_import_audio/log_import_audio.php');
$settingsnode = $PAGE->navbar->add(get_string('logimportaudio', 'block_th_import_audio'), $editurl);
$settingsnode->make_active();

if ($delete) {
	$PAGE->url->param('delete', 1);
	if ($confirm and confirm_sesskey()) {

		$fs = get_file_storage();
		$filerecord = $DB->get_record('files', array('id' => $fileid));
		$fs->get_file_instance($filerecord)->delete();

		redirect($CFG->wwwroot . '/blocks/th_import_audio/log_import_audio.php', "Xóa file <strong>$filerecord->filename</strong> thành công", null, \core\output\notification::NOTIFY_SUCCESS);
	}
	$strheading = get_string('delete');
	$PAGE->navbar->add($strheading);
	$PAGE->set_title($strheading);
	$PAGE->set_heading($COURSE->fullname);
	echo $OUTPUT->header();
	echo $OUTPUT->heading($strheading);
	$filerecord = $DB->get_record('files', array('id' => $fileid));
	$yesurl = new moodle_url('/blocks/th_import_audio/log_import_audio.php', array('fileid' => $fileid, 'confirm' => 1, 'delete' => 1, 'sesskey' => sesskey()));
	$message = get_string('confirmdeletefile', 'block_th_import_audio', $filerecord->filename);
	echo $OUTPUT->confirm($message, $yesurl, new moodle_url('/blocks/th_import_audio/log_import_audio.php'));
	echo $OUTPUT->footer();
	die;
}

$company_courses = $DB->get_records_sql(
	"SELECT courseid FROM {company_course} WHERE companyid = :companyid",
	['companyid' => $usercompany_id]
);

if (!empty($company_courses)) {
	$course_ids = array_keys($company_courses);
	list($insql, $inparams) = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED, 'course');

	$sql = "SELECT * FROM {th_log_import_audio} 
            WHERE courseid $insql 
            ORDER BY timecreated DESC";
	$params = $inparams;

	$log_import = $DB->get_records_sql($sql, $params);
} else {
	$log_import = [];
}

$baseurl = new moodle_url('/blocks/th_import_audio/log_import_audio.php');

$table = new html_table();
$table->head = array(
    get_string('stt', 'block_th_import_audio'),
    get_string('questionname', 'block_th_import_audio'),
    get_string('filename', 'block_th_import_audio'),
    get_string('coursename', 'block_th_import_audio'),
    get_string('timecreated', 'block_th_import_audio'),
    get_string('delete', 'block_th_import_audio')
);
$stt = 0;

foreach ($log_import as $log) {

	$fs = get_file_storage();

	if($log->option == 0){
		$fileinfo = array(
		    'component' => 'user',     
		    'filearea' => 'draft',     
		    'itemid' => $log->itemid,
		    'contextid' => $log->contextid,
		    'filepath' => '/',
		    'filename' => $log->filename
		);
	} else {
		$fileinfo = array(
		    'component' => 'question',     
		    'filearea' => 'questiontext',     
		    'itemid' => $log->itemid,
		    'contextid' => $log->contextid,
		    'filepath' => '/',
		    'filename' => $log->filename
		);
	}
	
	// Get file
	$file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
	                      $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);

	if ($file) {
	    $stt++;
		$course_name = $DB->get_field_sql("SELECT fullname FROM {course} WHERE id = '$log->courseid'");

		$urlparams = array('fileid' => $file->get_id(), 'returnurl' => $baseurl->out_as_local_url(false));
		$link_delete = new moodle_url('/blocks/th_import_audio/log_import_audio.php', $urlparams + array('delete' => 1));
		$delete = html_writer::link(
			$link_delete,
			$OUTPUT->pix_icon('t/delete', get_string('delete')),
			array('title' => get_string('delete'))
		);

		$row = new html_table_row();
		$cell = new html_table_cell($stt);
		$row->cells[] = $cell;
		$cell = new html_table_cell($log->questionname);
		$row->cells[] = $cell;
		$cell = new html_table_cell($log->filename);
		$row->cells[] = $cell;
		$cell = new html_table_cell($course_name);
		$row->cells[] = $cell;
		$cell = new html_table_cell(date("d-m-Y H:i:s", $log->timecreated));
		$row->cells[] = $cell;
		$cell = new html_table_cell($delete);
		$row->cells[] = $cell;
		$table->data[] = $row;
	}
}

$table->attributes = array('class' => 'th_log_import_audio_table', 'border' => '1');
$table->attributes['style'] = "width: 100%; text-align:center;";
$html = html_writer::table($table);

echo $OUTPUT->header();
echo html_writer::tag('div',
    $OUTPUT->heading(get_string('logimportaudio', 'block_th_import_audio')),
    ['style' => 'text-align: center']
);
echo $html;
$lang = current_language();
echo '<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">';
$PAGE->requires->js_call_amd('local_thlib/main', 'init', array('.th_log_import_audio_table', get_string('logimportaudio', 'block_th_import_audio'), $lang));
echo $OUTPUT->footer();
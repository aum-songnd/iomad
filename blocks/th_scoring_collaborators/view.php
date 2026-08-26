<?php

require_once '../../config.php';
require_once $CFG->libdir . '/adminlib.php';
require_once $CFG->dirroot.'/grade/lib.php';
require_once $CFG->libdir.'/mathslib.php';

global $DB, $OUTPUT, $PAGE, $COURSE, $USER;

// Check for all required variables.
$courseid = $COURSE->id;
$returnto = optional_param('returnto', 'course', PARAM_ALPHANUM); // Generic navigation return page switch.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
	print_error('invalidcourse', 'block_th_scoring_collaborators', $courseid);
}

require_login($courseid);
$syscontext = context_system::instance();
$coursecontext = context_course::instance($courseid);

if (!has_capability('block/th_scoring_collaborators:view', $syscontext)) {
    require_capability('block/th_scoring_collaborators:view', $coursecontext);
}


$pageurl = '/blocks/th_scoring_collaborators/view.php';
$title = get_string('title', 'block_th_scoring_collaborators');
$PAGE->set_url('/blocks/th_scoring_collaborators/view.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('th_scoring_collaborators', 'block_th_scoring_collaborators'));
$PAGE->set_title($SITE->fullname . ': ' . get_string('title', 'block_th_scoring_collaborators'));

$editurl = new moodle_url('/blocks/th_scoring_collaborators/view.php');
$settingsnode = $PAGE->navbar->add('block_th_scoring_collaborators', $editurl);
$settingsnode->make_active();

$list_attempts = $DB->get_records_sql("SELECT * FROM {block_th_assign_grading} AS ag WHERE ag.ctvid = $USER->id GROUP BY id ORDER BY ag.id DESC");

function containsWord($string, $word) {
	return stripos($string, $word) !== false;
}

if($list_attempts) {
	$table = new html_table();
	$table->head = array(
		get_string('serial_number', 'block_th_scoring_collaborators'),
		get_string('course_name', 'block_th_scoring_collaborators'),
		get_string('test_name', 'block_th_scoring_collaborators'),
		get_string('test_type', 'block_th_scoring_collaborators'),
		get_string('student_name', 'block_th_scoring_collaborators'),
		get_string('grading_link', 'block_th_scoring_collaborators'),
		get_string('grading_status', 'block_th_scoring_collaborators'),
		get_string('approval_status', 'block_th_scoring_collaborators')
	);
	$stt = 0;

	foreach ($list_attempts  as $k => $attempt) {
		$data = $DB->get_record_sql("SELECT DISTINCT qa.id,qa.attempt,q.name,c.fullname,u.firstname, u.lastname ,cm.idnumber,ag.confirm_grading
		FROM {quiz_attempts} as qa 
		JOIN {quiz} as q ON qa.quiz = q.id 
		JOIN {course} as c ON q.course = c.id 
		JOIN {user} as u ON qa.userid = u.id 
		JOIN {course_modules} as cm ON q.id = cm.instance AND cm.course = c.id
		JOIN {block_th_assign_grading} as ag ON ag.attemptid = qa.id
		WHERE qa.id = $attempt->attemptid AND cm.idnumber <> ''
		;
		");
		
		if (empty($data)) {
			continue;
		}
		
		if(containsWord($data->idnumber, 'Ess')){
			$name_type = get_string('writing', 'block_th_scoring_collaborators');
		}else {
			$name_type = get_string('speaking', 'block_th_scoring_collaborators');
		}

		$quizz_name = $data->name . '- ' . get_string('attempt_number', 'block_th_scoring_collaborators') . ' ' . $data->attempt;
		
		
		if($attempt->status){
			$button = '<p style="color:red">' . get_string('graded', 'block_th_scoring_collaborators') . '</p>';
		}else {
			$button = '<p>' . get_string('not_graded', 'block_th_scoring_collaborators') . '</p>';
		}

		if($data->confirm_grading) {
			$link_attempt = '';
			$button_confirm = '<a class="th_success">' . get_string('approved', 'block_th_scoring_collaborators') . '</a>';
		}else {
			$link_attempt = '<a class="th_link_attempt" href="'.$CFG->wwwroot.'/blocks/th_scoring_collaborators/review.php?attempt='.$attempt->attemptid.'&id='.$attempt->id.'">
			' . get_string('view_grading', 'block_th_scoring_collaborators') . '
			</a>';
			$button_confirm = '<a class="th_wait">' . get_string('not_approved', 'block_th_scoring_collaborators') . '</a>';
		}

		$student_name = $data->firstname . ' ' . $data->lastname;

		$stt = $stt + 1;
		$row = new html_table_row();
		$cell = new html_table_cell($stt);
		$row->cells[] = $cell;
		$cell = new html_table_cell($data->fullname);
		$row->cells[] = $cell;
		$cell = new html_table_cell($quizz_name);
		$row->cells[] = $cell;
		$cell = new html_table_cell($name_type);
		$row->cells[] = $cell;
		$cell = new html_table_cell($student_name);
		$row->cells[] = $cell;
		$cell = new html_table_cell($link_attempt);
		$row->cells[] = $cell;
		$cell = new html_table_cell($button);
		$row->cells[] = $cell;
		$cell = new html_table_cell($button_confirm);
		$row->cells[] = $cell;
		$table->data[] = $row;

	}

	$html = html_writer::table($table);
	echo $OUTPUT->header();
	echo "<center><h4>" . get_string('list_of_tests_to_grade', 'block_th_scoring_collaborators') . "</h4></center>";
	echo $html;
}else {
	echo $OUTPUT->header();
	echo "<center><h4>" . get_string('empty_list', 'block_th_scoring_collaborators') . "</h4></center>";
}

echo $OUTPUT->footer();

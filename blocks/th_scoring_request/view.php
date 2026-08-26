<?php

require_once '../../config.php';

// Khai báo các biến toàn cục sử dụng trong Moodle
global $DB, $OUTPUT, $PAGE, $COURSE, $USER;

// Lấy các tham số truyền vào từ URL hoặc mặc định
$courseid = $COURSE->id;
$returnto = optional_param('returnto', 'course', PARAM_ALPHANUM); // Trang chuyển hướng sau khi thao tác
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$courseid_param = optional_param('courseid', -1, PARAM_INT);

// Kiểm tra course hợp lệ
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
	print_error('invalidcourse');
}

// Yêu cầu đăng nhập vào course
require_login($courseid);

// Thiết lập các thông tin cho trang
$pageurl = '/blocks/th_scoring_request/view.php';
$title = get_string('title', 'block_th_scoring_request');
$PAGE->set_url('/blocks/th_scoring_request/view.php?courseid=' . $courseid_param . '');
$PAGE->set_pagelayout('standard');
$PAGE->set_heading($title);
$PAGE->set_title($SITE->fullname . ': ' . get_string('title', 'block_th_scoring_request'));

// Thêm breadcrumb cho navigation
$editurl = new moodle_url('/blocks/th_scoring_request/view.php?courseid=' . $courseid_param . '');
$settingsnode = $PAGE->navbar->add(get_string('breadcrumb', 'block_th_scoring_request'), $editurl);
$settingsnode->make_active();

// Lấy giá trị từ settings
$config = get_config('block_th_scoring_request');
$rec = $config->rec;
$ess = $config->ess;

// Lấy danh sách các bài kiểm tra đã nộp của user
$get_attempt = $DB->get_records_sql("SELECT row_number() OVER() as stt,q.id, q.name, qa.attempt, qa.id as atempt_review 
					FROM {quiz_attempts} as qa 
					JOIN {quiz} as q ON qa.quiz = q.id 
					JOIN {course_modules} as cm ON q.id = cm.instance
					WHERE q.course = $courseid_param AND qa.userid = $USER->id 
					AND (cm.idnumber LIKE :rec OR cm.idnumber LIKE :ess)
					AND qa.state = 'finished' 
					ORDER BY id,attempt;",
					['rec' => '%' . $rec . '%', 'ess' => '%' . $ess . '%']);

// Tạo bảng hiển thị danh sách bài kiểm tra
$table = new html_table();
if ($get_attempt) {
	$table->head = array(
		get_string('table_header_stt', 'block_th_scoring_request'),
		get_string('table_header_name', 'block_th_scoring_request'),
		get_string('table_header_action', 'block_th_scoring_request'),
		get_string('table_header_turns', 'block_th_scoring_request')
	);
	$stt = 0;

	// Lấy thông tin lượt yêu cầu chấm còn lại
	$attemp_data = $DB->get_record_sql("SELECT * FROM {th_assign_turns} WHERE userid = $USER->id");

	// Duyệt từng bài kiểm tra đã nộp
	foreach ($get_attempt as $attempt) {
		$quiz_name = $attempt->name;
		$quiz_number = $attempt->attempt;
		$name_attempt = get_string('quiz_test', 'block_th_scoring_request') . ': ' . $quiz_name . ' , ' . get_string('attempt_number', 'block_th_scoring_request', $quiz_number);

		// Kiểm tra xem user đã gửi yêu cầu chấm cho lần làm bài này chưa
		$scoring_request = $DB->get_record_sql("SELECT * FROM {block_th_scoring_request} AS sr 
							WHERE sr.quizid = $attempt->id 
							AND sr.userid = $USER->id 
							AND sr.attemptid = $quiz_number 
							AND sr.atempt_review = $attempt->atempt_review");

		// Kiểm tra trạng thái chấm bài
		$list_attempts = $DB->get_record_sql("SELECT * FROM {block_th_assign_grading} 
												WHERE attemptid = $attempt->atempt_review");

		// Tạo nút chức năng cho từng bài kiểm tra
		$button = '<a class="th_link_attempt" style="margin-right:5px" href="' . $CFG->wwwroot . '/mod/quiz/review.php?attempt=' . $attempt->atempt_review . '">' . get_string('view_review', 'block_th_scoring_request') . '</a>';

		$used_turns = 0;
		if (!empty($attemp_data)) {
			$used_turns = $attemp_data->numberturns - $attemp_data->used;
			// Nếu còn lượt yêu cầu chấm
			if ($used_turns > 0) {
				if (empty($scoring_request)) {
					// Chưa gửi yêu cầu, còn lượt => hiển thị nút gửi yêu cầu
					$button .= '<a class="th_wait" href="' . $CFG->wwwroot . '/blocks/th_scoring_request/edit.php?quizid=' . $attempt->id . '&course=' . $courseid_param . '&attempt=' . $attempt->attempt . '&atempt_review=' . $attempt->atempt_review . '&redirect=2">' . get_string('request_grading', 'block_th_scoring_request') . '</a>';
				} else {
					if (!empty($list_attempts) && $list_attempts->confirm_grading == 1) {
						// Đã chấm xong
						$button .= '<a class="th_success">' . get_string('graded', 'block_th_scoring_request') . '</a>';
					} else if (!empty($list_attempts)) {
						// Đang chấm bài
						$button .= '<a class="th_wait_grade">' . get_string('grading_inprogress', 'block_th_scoring_request') . '</a>';
					} else {
						// Có yêu cầu nhưng chưa chấm, cho phép hủy
						$button .= '<a class="th_cancel" href="' . $CFG->wwwroot . '/blocks/th_scoring_request/edit.php?atempt_review=' . $attempt->atempt_review . '&course=' . $courseid_param . '&delete=1&redirect=2">' . get_string('cancel_request', 'block_th_scoring_request') . '</a>';
					}
				}
			} else if (!empty($attemp_data) && $used_turns <= 0) {
				// Hết lượt yêu cầu, không hiển thị nút gửi yêu cầu
				$used_turns = 0;
				if (empty($scoring_request)) {
					$button .= '';
				} else {
					if (!empty($list_attempts) && $list_attempts->confirm_grading == 1) {
						$button .= '<a class="th_success">' . get_string('graded', 'block_th_scoring_request') . '</a>';
					} else if (!empty($list_attempts)) {
						$button .= '<a class="th_wait_grade">' . get_string('grading_inprogress', 'block_th_scoring_request') . '</a>';
					} else {
						$button .= '<a class="th_cancel" href="' . $CFG->wwwroot . '/blocks/th_scoring_request/edit.php?atempt_review=' . $attempt->atempt_review . '&course=' . $COURSE->id . '&delete=1&redirect=2">' . get_string('cancel_request', 'block_th_scoring_request') . '</a>';
					}
				}
			}
		}

		// Tạo dòng dữ liệu cho bảng
		$stt = $stt + 1;
		$row = new html_table_row();
		$cell = new html_table_cell($stt);
		$row->cells[] = $cell;
		$cell = new html_table_cell($name_attempt);
		$row->cells[] = $cell;
		$cell = new html_table_cell($button);
		$row->cells[] = $cell;
		$cell = new html_table_cell($used_turns);
		$row->cells[] = $cell;
		$table->data[] = $row;
	}
}

// Hiển thị bảng ra giao diện
$html = html_writer::table($table);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('list', 'block_th_scoring_request'));
echo $html;
echo $OUTPUT->footer();

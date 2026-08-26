<?php

class block_th_scoring_request extends block_base {
	public function init() {
		$this->title = get_string('title', 'block_th_scoring_request');
	}

	public function has_config() {
		return true; 
	}

	public function get_content() {
		global $CFG, $DB, $COURSE, $USER,$config;
		if ($this->content !== null) {
			return $this->content;
		}
		$this->content = new stdClass;
		$context = context_course::instance($COURSE->id);

		// Lấy giá trị từ settings
		$config = get_config('block_th_scoring_request');
		$rec = $config->rec;
		$ess = $config->ess;

		// Lấy danh sách các bài kiểm tra đã nộp của user
		$get_attempt = $DB->get_records_sql("SELECT row_number() OVER() as stt,q.id, q.name, qa.attempt, qa.id as atempt_review 
							FROM {quiz_attempts} as qa 
							JOIN {quiz} as q ON qa.quiz = q.id 
							JOIN {course_modules} as cm ON q.id = cm.instance
							WHERE q.course = $COURSE->id AND qa.userid = $USER->id 
							AND qa.state = 'finished' 
							AND (cm.idnumber LIKE :rec OR cm.idnumber LIKE :ess)
							ORDER BY id,attempt;",
							['rec' => '%' . $rec . '%', 'ess' => '%' . $ess . '%']);

		if ($get_attempt) {
			$this->content->footer = '<p class="title">' . get_string('list_submitted_tests', 'block_th_scoring_request') . '</p>';
			// Lấy thông tin lượt yêu cầu chấm còn lại
			$attemp_data = $DB->get_record_sql("SELECT * FROM {th_assign_turns} WHERE userid = $USER->id");
		$used_turns = 0;
		if ($attemp_data) {
			$used_turns = $attemp_data->numberturns - $attemp_data->used;
		}

			$attemptshow = 0;
			foreach ($get_attempt as $attempt) {
				$attemptshow++;
				if ($attemptshow > 5) {
					break;
				}
				$quiz_name = $attempt->name;
				$quiz_number = $attempt->attempt;

				// Kiểm tra xem user đã gửi yêu cầu chấm cho lần làm bài này chưa
				$scoring_request = $DB->get_record_sql("SELECT * FROM {block_th_scoring_request} AS sr 
									WHERE sr.quizid = $attempt->id 
									AND sr.userid = $USER->id 
									AND sr.attemptid = $quiz_number 
									AND sr.atempt_review = $attempt->atempt_review");

				// Kiểm tra trạng thái chấm bài
				$list_attempts = $DB->get_record_sql("SELECT * FROM {block_th_assign_grading} 
													WHERE attemptid = $attempt->atempt_review");

				// Xử lý nút chức năng hiển thị cho từng trường hợp
				if (!empty($attemp_data) && $used_turns > 0) {
					if (empty($scoring_request)) {
						// Chưa gửi yêu cầu, còn lượt => hiển thị nút gửi yêu cầu
						$button = '<a class="th_wait" href="' . $CFG->wwwroot . '/blocks/th_scoring_request/edit.php?quizid=' . $attempt->id . '&course=' . $COURSE->id . '&attempt=' . $attempt->attempt . '&atempt_review=' . $attempt->atempt_review . '&redirect=1">' . get_string('request_grading', 'block_th_scoring_request') . '</a>';
					} else {
						if (!empty($list_attempts) && $list_attempts->confirm_grading == 1) {
							// Đã chấm xong
							$button = '<a class="th_success">' . get_string('graded', 'block_th_scoring_request') . '</a>';
						} else if (!empty($list_attempts)) {
							// Đang chấm bài
							$button = '<a class="th_wait_grade">' . get_string('grading_inprogress', 'block_th_scoring_request') . '</a>';
						} else {
							// Có yêu cầu nhưng chưa chấm, cho phép hủy
							$button = '<a class="th_cancel" href="' . $CFG->wwwroot . '/blocks/th_scoring_request/edit.php?atempt_review=' . $attempt->atempt_review . '&course=' . $COURSE->id . '&delete=1&redirect=1">' . get_string('cancel_request', 'block_th_scoring_request') . '</a>';
						}
					}
				} else if (!empty($attemp_data) && $used_turns <= 0) {
					if (empty($scoring_request)) {
						// Hết lượt yêu cầu, không hiển thị nút
						$button = '';
					} else {
						if (!empty($list_attempts) && $list_attempts->confirm_grading == 1) {
							$button = '<a class="th_success">' . get_string('graded', 'block_th_scoring_request') . '</a>';
						} else if (!empty($list_attempts)) {
							$button = '<a class="th_wait_grade">' . get_string('grading_inprogress', 'block_th_scoring_request') . '</a>';
						} else {
							$button = '<a class="th_cancel" href="' . $CFG->wwwroot . '/blocks/th_scoring_request/edit.php?atempt_review=' . $attempt->atempt_review . '&course=' . $COURSE->id . '&delete=1&redirect=1">' . get_string('cancel_request', 'block_th_scoring_request') . '</a>';
						}
					}
				} else {
					$button = '';
				}

				// Hiển thị thông tin từng lần làm bài và các nút chức năng
				$this->content->footer .= '
				<div class="th_quiz_attempt">
					<span>' . get_string('quiz_test', 'block_th_scoring_request') . ': ' . $quiz_name . ' , ' . get_string('attempt_number', 'block_th_scoring_request', $quiz_number) . ' </span>
					<div>
						<a class="th_link_attempt" href="' . $CFG->wwwroot . '/mod/quiz/review.php?attempt=' . $attempt->atempt_review . '">' . get_string('view_review', 'block_th_scoring_request') . '</a>
						' . $button . '
					</div>
				</div>';
			}

			// Hiển thị số lượt yêu cầu còn lại
			if (!empty($attemp_data)) {
				$this->content->footer .= '<span class="th_number">' . get_string('remaining_turns', 'block_th_scoring_request', $used_turns) . '</span>';
			}
			// Nếu có nhiều hơn 1 bài kiểm tra, hiển thị nút xem tất cả
			if (COUNT($get_attempt) > 1) {
				$this->content->footer .= '<a class="th_all_quiz" href="' . $CFG->wwwroot . '/blocks/th_scoring_request/view.php?courseid=' . $COURSE->id . '">' . get_string('view_all', 'block_th_scoring_request') . '</a>';
			}
		} else {
			// Nếu không có bài kiểm tra nào đã nộp
			$this->content->footer = '
			<p class="titile">' . get_string('no_attempts', 'block_th_scoring_request') . '</p class="titile">';
		}

		return $this->content;
	}
}

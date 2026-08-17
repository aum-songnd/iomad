<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package   theme_lambda
 * @copyright 2022 redPIthemes
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/quiz/renderer.php';

class theme_th_lambda_mod_quiz_renderer extends mod_quiz_renderer {

    public function attempt_form($attemptobj, $page, $slots, $id, $nextpage) {
        $output = '';

        $output .= html_writer::start_tag('form', [
            'action' => new moodle_url($attemptobj->processattempt_url(), ['cmid' => $attemptobj->get_cmid()]),
            'method' => 'post',
            'enctype' => 'multipart/form-data',
            'accept-charset' => 'utf-8',
            'id' => 'responseform'
        ]);
        $output .= html_writer::start_tag('div');

        $firstQ = '';
        $remainQ = '';
        $ques = 0;
        foreach ($slots as $slot) {
            $ques++;
            $rendered = $attemptobj->render_question(
                $slot,
                false,
                $this,
                $attemptobj->attempt_url($slot, $page),
                $this
            );
            if ($ques == 1) {
                $firstQ = $rendered;
            } else {
                $remainQ .= $rendered;
            }
        }

        $DOM = new DOMDocument;
        libxml_use_internal_errors(true);
        $DOM->loadHTML($firstQ);
        libxml_use_internal_errors(false);
        $item = $DOM->getElementById('th_mycustomwidth');

        if ($item) {
            $width = (int) $item->getAttribute('value');
            $remain_width = 12 - $width;

            if (strpos($firstQ, 'thvstepcluster') !== false) {
                $grid = "<div class='row-fluid'>
                    <div class='span12 overflow-auto'>$firstQ</div>
                </div>";
            } else {
                $grid = "<div class='row-fluid'>
                    <div class='span$width overflow-auto' style='height: 90vh;'>$firstQ</div>
                    <div class='span$remain_width overflow-auto' style='height: 90vh;'>$remainQ</div>
                </div>";
            }
            $output .= $grid;

            $output .= '<style>
                .que.thvstepcluster {
                    display: grid;
                    grid-template-columns: ' . $width . 'fr ' . $remain_width . 'fr;
                    gap: 1rem;
                    overflow: hidden;
                }

                .que.thvstepcluster > .description {
                    grid-column: 1;
                    height: 90vh;
                    overflow: auto;
                    margin: 0 !important;
                    position: sticky;
                    top: 0;
                    align-self: start;
                }

                .que.thvstepcluster > .que:not(.description) {
                    grid-column: 2;
                    overflow-y: auto;
                    max-height: 100%;
                }

                .que.thvstepcluster > .content {
                    height: 90vh;
                    overflow: auto;
                }
            </style>';
        } else {
            $output .= $firstQ . $remainQ;
        }

        $navmethod = $attemptobj->get_quiz()->navmethod;
        $output .= $this->attempt_navigation_buttons(
            $page,
            $attemptobj->is_last_page($page),
            $navmethod
        );

        $output .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'attempt',
            'value' => $attemptobj->get_attemptid()
        ]);
        $output .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'thispage',
            'value' => $page,
            'id' => 'followingpage'
        ]);
        $output .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'nextpage',
            'value' => $nextpage
        ]);
        $output .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'timeup',
            'value' => '0',
            'id' => 'timeup'
        ]);
        $output .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        ]);
        $output .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'scrollpos',
            'value' => '',
            'id' => 'scrollpos'
        ]);
        $output .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'slots',
            'value' => implode(',', $attemptobj->get_active_slots($page))
        ]);

        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');
        $output .= $this->connection_warning();

        $allowcopy = get_config('theme_th_lambda', 'allowcopyshortanswer');
        if (empty($allowcopy)) {
            $this->page->requires->js(new moodle_url('/theme/th_lambda/javascript/shortanswer_nopaste.js'));
        }

        return $output;
    }

    protected function th_has_quizcluster_snapshot_table(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('local_th_qc_attemptmk');
    }

    protected function th_can_show_marks($displayoptions): bool {
        if (!isset($displayoptions->marks)) {
            return false;
        }
        return $displayoptions->marks >= question_display_options::MARK_AND_MAX;
    }

    protected function th_get_effective_userid_for_view($viewobj): ?int {
        if (!empty($viewobj->attemptobjs) && is_array($viewobj->attemptobjs)) {
            foreach ($viewobj->attemptobjs as $attemptobj) {
                try {
                    $attempt = $attemptobj->get_attempt();
                    if (!empty($attempt->userid)) {
                        return (int) $attempt->userid;
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        if (isset($viewobj->userid) && !empty($viewobj->userid)) {
            return (int) $viewobj->userid;
        }

        return null;
    }

    protected function th_get_snapshot_marks_by_slot(int $attemptid): array {
        global $DB;

        if (!$this->th_has_quizcluster_snapshot_table()) {
            return [];
        }

        $rows = $DB->get_records('local_th_qc_attemptmk', ['attemptid' => $attemptid], '', 'slot, maxmark');
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->slot] = (float) $row->maxmark;
        }
        ksort($out);

        return $out;
    }

    protected function th_get_attempt_snapshot_total(int $attemptid): float {
        global $DB;

        if (!$this->th_has_quizcluster_snapshot_table()) {
            return 0.0;
        }

        $sql = "SELECT COALESCE(SUM(maxmark), 0)
                  FROM {local_th_qc_attemptmk}
                 WHERE attemptid = :attemptid";
        return (float) $DB->get_field_sql($sql, ['attemptid' => $attemptid]);
    }

    protected function th_get_attempt_raw_mark_from_snapshot($attemptobj): float {
        $attemptid = (int) $attemptobj->get_attemptid();
        $snapshots = $this->th_get_snapshot_marks_by_slot($attemptid);

        if (empty($snapshots)) {
            return (float) $attemptobj->get_sum_marks();
        }

        $sum = 0.0;
        foreach (array_keys($snapshots) as $slot) {
            try {
                $qa = $attemptobj->get_question_attempt($slot);
                $fraction = $qa->get_fraction();
                if ($fraction === null) {
                    $fraction = 0.0;
                }
                $sum += ((float) $fraction * (float) $snapshots[$slot]);
            } catch (\Throwable $e) {
            }
        }

        return (float) $sum;
    }

    protected function th_get_display_grade_from_snapshot(float $rawmark, float $attemptmax, float $quizgrade): float {
        if ($attemptmax <= 0) {
            return 0.0;
        }

        return ($rawmark / $attemptmax) * $quizgrade;
    }

    protected function th_get_attempt_bundle($attemptobj, $quiz): array {
        $attemptid = (int) $attemptobj->get_attemptid();

        $rawscore = $this->th_get_attempt_raw_mark_from_snapshot($attemptobj);
        $attemptmax = $this->th_get_attempt_snapshot_total($attemptid);
        if ($attemptmax <= 0) {
            $attemptmax = (float) $quiz->sumgrades;
        }

        $attemptgrade = $this->th_get_display_grade_from_snapshot(
            $rawscore,
            $attemptmax,
            (float) $quiz->grade
        );

        return [
            'rawscore' => (float) $rawscore,
            'attemptmax' => (float) $attemptmax,
            'attemptgrade' => (float) $attemptgrade,
        ];
    }

    protected function th_get_fresh_attemptobj(int $attemptid): ?quiz_attempt {
        global $CFG;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        try {
            return quiz_attempt::create($attemptid);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function th_get_user_attempt_bundles(object $quiz, int $userid, bool $includepreview = false): array {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $params = [
            'quiz' => $quiz->id,
            'userid' => $userid,
            'state' => 'finished',
        ];

        $select = 'quiz = :quiz AND userid = :userid AND state = :state';

        if (!$includepreview) {
            $select .= ' AND preview = 0';
        }

        $attemptrecords = $DB->get_records_select(
            'quiz_attempts',
            $select,
            $params,
            'attempt ASC, id ASC',
            '*'
        );

        $bundles = [];
        foreach ($attemptrecords as $attemptrecord) {
            try {
                $attemptobj = quiz_attempt::create($attemptrecord->id);
                $bundle = $this->th_get_attempt_bundle($attemptobj, $quiz);
                $bundle['attempt'] = (int) $attemptrecord->attempt;
                $bundle['id'] = (int) $attemptrecord->id;
                $bundle['preview'] = !empty($attemptrecord->preview);
                $bundles[] = $bundle;
            } catch (\Throwable $e) {
            }
        }

        return $bundles;
    }

    protected function th_get_snapshot_based_mygrade(object $quiz, int $userid, ?float $fallback = null): ?float {
        $bundles = $this->th_get_user_attempt_bundles($quiz, $userid, false);

        if (empty($bundles)) {
            $bundles = $this->th_get_user_attempt_bundles($quiz, $userid, true);
        }

        if (empty($bundles)) {
            return $fallback;
        }

        $grades = [];
        foreach ($bundles as $b) {
            $grades[] = (float) $b['attemptgrade'];
        }

        switch ((int) $quiz->grademethod) {
            case QUIZ_GRADEHIGHEST:
                return max($grades);

            case QUIZ_GRADEAVERAGE:
                return array_sum($grades) / count($grades);

            case QUIZ_ATTEMPTFIRST:
                return (float) reset($grades);

            case QUIZ_ATTEMPTLAST:
                return (float) end($grades);

            default:
                return $fallback ?? max($grades);
        }
    }

    public function view_table($quiz, $context, $viewobj) {
        global $DB, $USER;

        if (!$viewobj->attempts) {
            return '';
        }

        $gradeitem = $DB->get_record('grade_items', [
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id
        ]);

        $table = new html_table();
        $table->attributes['class'] = 'generaltable quizattemptsummary';
        $table->head = [];
        $table->align = [];
        $table->size = [];
        $table->caption = get_string('summaryofattempts', 'quiz');
        $table->captionhide = true;

        if ($viewobj->attemptcolumn) {
            $table->head[] = get_string('attemptnumber', 'quiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }

        $table->head[] = get_string('attemptstate', 'quiz');
        $table->align[] = 'left';
        $table->size[] = '';

        if ($viewobj->markcolumn) {
            $table->head[] = get_string('marks', 'quiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }

        if ($viewobj->gradecolumn) {
            if ($gradeitem
                && $gradeitem->idnumber === 'ielts-letter'
                && (int) $gradeitem->display === GRADE_DISPLAY_TYPE_LETTER) {
                $table->head[] = get_string('grade_ielts', 'theme_th_lambda');
            } else {
                $table->head[] = get_string('gradenoun');
            }
            $table->align[] = 'center';
            $table->size[] = '';
        }

        if ($viewobj->canreviewmine) {
            $table->head[] = get_string('review', 'quiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }

        if ($viewobj->feedbackcolumn) {
            $table->head[] = get_string('feedback', 'quiz');
            $table->align[] = 'left';
            $table->size[] = '';
        }

        foreach ($viewobj->attemptobjs as $attemptobj) {
            $attemptoptions = $attemptobj->get_display_options(true);
            $row = [];

            $attemptid = (int) $attemptobj->get_attemptid();
            $freshattemptobj = $this->th_get_fresh_attemptobj($attemptid);
            $sourceattemptobj = $freshattemptobj ?: $attemptobj;

            $bundle = $this->th_get_attempt_bundle($sourceattemptobj, $quiz);
            $attemptmax = (float) $bundle['attemptmax'];
            $rawscore = (float) $bundle['rawscore'];
            $attemptgrade = (float) $bundle['attemptgrade'];

            if ($viewobj->attemptcolumn) {
                if ($attemptobj->is_preview()) {
                    $row[] = get_string('preview', 'quiz');
                } else {
                    $row[] = $attemptobj->get_attempt_number();
                }
            }

            $row[] = $this->attempt_state($attemptobj);

            if ($viewobj->markcolumn) {
                if ($this->th_can_show_marks($attemptoptions) && $attemptobj->is_finished()) {
                    $state = $this->check_attempt_has_unmarked_essay($attemptid);
                    if ($state) {
                        $row[] = quiz_format_grade($quiz, null);
                    } else {
                        $row[] = quiz_format_grade($quiz, $rawscore) . ' / ' .
                        quiz_format_grade($quiz, $attemptmax);
                    }
                } else {
                    $row[] = '';
                }
            }

            if ($viewobj->gradecolumn) {
                if ($this->th_can_show_marks($attemptoptions) && $attemptobj->is_finished()) {
                    if ($gradeitem
                        && $gradeitem->idnumber === 'ielts-letter'
                        && (int) $gradeitem->display === GRADE_DISPLAY_TYPE_LETTER) {
                        $row[] = $this->convert_to_ielts_band($rawscore);
                    } else {
                        $state = $this->check_attempt_has_unmarked_essay($attemptid);
                        if ($state) {
                            $row[] = quiz_format_grade($quiz, null);
                        } else {
                            $row[] = quiz_format_grade($quiz, $attemptgrade);
                        }
                    }
                } else {
                    $row[] = '';
                }
            }

            if ($viewobj->canreviewmine) {
                $row[] = $viewobj->accessmanager->make_review_link(
                    $attemptobj->get_attempt(),
                    $attemptoptions,
                    $this
                );
            }

            if ($viewobj->feedbackcolumn && $attemptobj->is_finished()) {
                if ($attemptoptions->overallfeedback) {
                    $row[] = quiz_feedback_for_grade($attemptgrade, $quiz, $context);
                } else {
                    $row[] = '';
                }
            }

            if ($attemptobj->is_preview()) {
                $table->data['preview_' . $attemptid] = $row;
            } else {
                $table->data[$attemptobj->get_attempt_number()] = $row;
            }
        }

        $output = '';
        $output .= $this->view_table_heading();
        $output .= html_writer::table($table);
        return $output;
    }

    public function view_result_info($quiz, $context, $cm, $viewobj) {
        global $DB, $USER;

        $gradeitem = $DB->get_record('grade_items', [
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id
        ]);

        if ($gradeitem
            && $gradeitem->idnumber === 'ielts-letter'
            && (int) $gradeitem->display === GRADE_DISPLAY_TYPE_LETTER) {
            return '';
        }

        $output = '';
        if (!$viewobj->numattempts && !$viewobj->gradecolumn && is_null($viewobj->mygrade)) {
            return $output;
        }

        $effectiveuserid = $this->th_get_effective_userid_for_view($viewobj);
        if (!$effectiveuserid) {
            $effectiveuserid = (int) $USER->id;
        }
        
        //xử lý hiện trạng thái chưa chấm điểm khi trong quiz có câu tự luận
        // --- CÁCH SỬ DỤNG ---
        $state = $this->check_unmarked_essay_attempts($USER->id, $quiz->cmid);

        // Ví dụ sử dụng:
        if ($state) {
            $mygrade = null;
        } else {
            $mygrade = $this->th_get_snapshot_based_mygrade($quiz, $effectiveuserid, $viewobj->mygrade);
        }

        $resultinfo = '';

        if ($viewobj->overallstats) {
            if ($viewobj->moreattempts) {
                $a = new stdClass();
                $a->method = quiz_get_grading_option_name($quiz->grademethod);
                $a->mygrade = quiz_format_grade($quiz, $mygrade);
                $a->quizgrade = quiz_format_grade($quiz, $quiz->grade);
                $resultinfo .= $this->heading(get_string('gradesofar', 'quiz', $a), 3);
            } else {
                $a = new stdClass();
                $a->grade = quiz_format_grade($quiz, $mygrade);
                $a->maxgrade = quiz_format_grade($quiz, $quiz->grade);
                $a = get_string('outofshort', 'quiz', $a);
                $resultinfo .= $this->heading(get_string('yourfinalgradeis', 'quiz', $a), 3);
            }
        }

        if ($viewobj->mygradeoverridden) {
            $resultinfo .= html_writer::tag(
                'p',
                get_string('overriddennotice', 'grades'),
                ['class' => 'overriddennotice']
            ) . "\n";
        }

        if ($viewobj->gradebookfeedback) {
            $resultinfo .= $this->heading(get_string('comment', 'quiz'), 3);
            $resultinfo .= html_writer::div($viewobj->gradebookfeedback, 'quizteacherfeedback') . "\n";
        }

        if ($viewobj->feedbackcolumn) {
            $resultinfo .= $this->heading(get_string('overallfeedback', 'quiz'), 3);
            $resultinfo .= html_writer::div(
                quiz_feedback_for_grade($mygrade, $quiz, $context),
                'quizgradefeedback'
            ) . "\n";
        }

        if ($resultinfo) {
            $output .= $this->box($resultinfo, 'generalbox', 'feedback');
        }

        return $output;
    }

    /**
     * Kiểm tra xem một học viên có câu hỏi Essay nào chưa được chấm trong một Quiz cụ thể hay không.
     * 
     * @param int $userid ID của người dùng
     * @param int $cmid Course Module ID (id trong bảng course_modules)
     * @return bool True nếu còn câu hỏi chưa chấm, ngược lại False.
     */
    // public function check_unmarked_essay_attempts($userid, $cmid) {
    //     global $DB;

    //     $sql = "SELECT qas.id
    //             FROM {quiz_attempts} qa
    //             JOIN {quiz} qu ON qu.id = qa.quiz
    //             JOIN {course_modules} cm ON cm.instance = qu.id
    //             JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
    //             JOIN {question_usages} qus ON qus.id = qa.uniqueid
    //             JOIN {question_attempts} qatt ON qatt.questionusageid = qus.id
    //             JOIN {question} q ON q.id = qatt.questionid
    //             JOIN {question_attempt_steps} qas ON qas.questionattemptid = qatt.id
    //             WHERE 
    //                 qa.userid = :userid
    //                 AND cm.id = :cmid
    //                 AND q.qtype = 'essay'
    //                 AND qas.state = 'needsgrading'
    //                 AND qas.id = (
    //                     SELECT MAX(id) 
    //                     FROM {question_attempt_steps} 
    //                     WHERE questionattemptid = qatt.id
    //                 )";

    //     $params = [
    //         'userid' => $userid,
    //         'cmid'   => $cmid
    //     ];

    //     // Trả về true nếu tồn tại ít nhất 1 bản ghi, ngược lại false
    //     return $DB->record_exists_sql($sql, $params);
    // }

    public function check_unmarked_essay_attempts($userid, $cmid) {
        global $DB;

        // 1. Đếm tổng số lần làm bài (attempts) đã nộp của user tại Quiz này
        $sql_total_attempts = "SELECT COUNT(qa.id)
                            FROM {quiz_attempts} qa
                            JOIN {quiz} qu ON qu.id = qa.quiz
                            JOIN {course_modules} cm ON cm.instance = qu.id
                            WHERE qa.userid = :userid 
                                AND cm.id = :cmid
                                AND qa.state = 'finished'";

        $total_attempts = $DB->count_records_sql($sql_total_attempts, ['userid' => $userid, 'cmid' => $cmid]);

        // Nếu user chưa làm bài nào thì không có gì để chấm -> trả về false
        if ($total_attempts == 0) {
            return false;
        }

        // 2. Đếm số lần làm bài (attempts) mà chứa câu hỏi tự luận (essay) CHƯA CHẤM
        $sql_unmarked_attempts = "SELECT COUNT(DISTINCT qa.id)
                                FROM {quiz_attempts} qa
                                JOIN {quiz} qu ON qu.id = qa.quiz
                                JOIN {course_modules} cm ON cm.instance = qu.id
                                JOIN {question_usages} qus ON qus.id = qa.uniqueid
                                JOIN {question_attempts} qatt ON qatt.questionusageid = qus.id
                                JOIN {question} q ON q.id = qatt.questionid
                                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qatt.id
                                WHERE qa.userid = :userid
                                    AND cm.id = :cmid
                                    AND qa.state = 'finished'
                                    AND q.qtype = 'essay'
                                    AND qas.state = 'needsgrading'
                                    AND qas.id = (
                                        SELECT MAX(id) 
                                        FROM {question_attempt_steps} 
                                        WHERE questionattemptid = qatt.id
                                    )";

        $unmarked_attempts = $DB->count_records_sql($sql_unmarked_attempts, ['userid' => $userid, 'cmid' => $cmid]);

        // BIẾN ĐỔI LOGIC ĐỘNG:
        // Chỉ trả về true khi TẤT CẢ các lần làm bài đều là lần làm bài chưa chấm.
        // Nếu có N lần làm, mà cả N lần đều chưa chấm -> true.
        // Chỉ cần có 1 lần đã chấm hoặc không có essay -> unmarked_attempts sẽ nhỏ hơn total_attempts -> false.
        return ($total_attempts === $unmarked_attempts);
    }

    /**
     * Kiểm tra xem một Attempt cụ thể có câu hỏi Essay nào chưa được chấm hay không.
     * 
     * @param int $attemptid ID của lần làm bài (quiz_attempts id)
     * @return bool True nếu còn ít nhất 1 câu essay chưa chấm, ngược lại False.
     */
    public function check_attempt_has_unmarked_essay($attemptid) {
        global $DB;

        $sql = "SELECT qas.id
                FROM {quiz_attempts} qa
                JOIN {question_usages} qus ON qus.id = qa.uniqueid
                JOIN {question_attempts} qatt ON qatt.questionusageid = qus.id
                JOIN {question} q ON q.id = qatt.questionid
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qatt.id
                WHERE 
                    qa.id = :attemptid
                    AND q.qtype = 'essay'
                    AND qas.state = 'needsgrading'
                    AND qas.id = (
                        SELECT MAX(id) 
                        FROM {question_attempt_steps} 
                        WHERE questionattemptid = qatt.id
                    )";

        $params = ['attemptid' => $attemptid];

        return $DB->record_exists_sql($sql, $params);
    }

    private function convert_to_ielts_band($rawscore) {
        if ($rawscore >= 39) return 9;
        if ($rawscore >= 37) return 8.5;
        if ($rawscore >= 35) return 8;
        if ($rawscore >= 33) return 7.5;
        if ($rawscore >= 30) return 7;
        if ($rawscore >= 27) return 6.5;
        if ($rawscore >= 23) return 6;
        if ($rawscore >= 20) return 5.5;
        if ($rawscore >= 16) return 5;
        if ($rawscore >= 13) return 4.5;
        if ($rawscore >= 10) return 4;
        if ($rawscore >= 7)  return 3.5;
        if ($rawscore >= 5)  return 3;
        if ($rawscore >= 3)  return 2.5;
        return 0;
    }

    public function review_page(
        quiz_attempt $attemptobj,
        $slots,
        $page,
        $showall,
        $lastpage,
        mod_quiz_display_options $displayoptions,
        $summarydata
    ) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $quiz = $attemptobj->get_quiz();
        $cm = $attemptobj->get_cm();

        $gradeitem = $DB->get_record('grade_items', [
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'courseid' => $attemptobj->get_courseid()
        ]);

        $bundle = $this->th_get_attempt_bundle($attemptobj, $quiz);
        $attemptmax = $bundle['attemptmax'];
        $rawmark = $bundle['rawscore'];
        $displaygrade = $bundle['attemptgrade'];

        $percentage = 0.0;
        if ($attemptmax > 0) {
            $percentage = ($rawmark / $attemptmax) * 100;
        }

        $filtered = [];
        foreach ($summarydata as $row) {
            if ($row['title'] == get_string('marks', 'quiz')) {
                continue;
            }
            if ($row['title'] == get_string('grade', 'quiz')) {
                continue;
            }
            $filtered[] = $row;
        }

        if (!$this->th_can_show_marks($displayoptions)) {
            return parent::review_page(
                $attemptobj,
                $slots,
                $page,
                $showall,
                $lastpage,
                $displayoptions,
                $filtered
            );
        }

        if ($gradeitem
            && $gradeitem->display == GRADE_DISPLAY_TYPE_LETTER
            && !empty($cm->idnumber)
            && strpos($cm->idnumber, 'ielts-letter') !== false
        ) {
            $marks_display = quiz_format_grade($quiz, $rawmark) . '/' . quiz_format_grade($quiz, $attemptmax);
            $band = $this->convert_to_ielts_band($rawmark);

            $filtered[] = [
                'title' => get_string('marks', 'quiz'),
                'content' => $marks_display
            ];

            $filtered[] = [
                'title' => get_string('grade_ielts', 'theme_th_lambda'),
                'content' => $band
            ];

            return parent::review_page(
                $attemptobj,
                $slots,
                $page,
                $showall,
                $lastpage,
                $displayoptions,
                $filtered
            );
        }

        $filtered[] = [
            'title' => get_string('marks', 'quiz'),
            'content' => quiz_format_grade($quiz, $rawmark) . '/' . quiz_format_grade($quiz, $attemptmax)
        ];

        $filtered[] = [
            'title' => get_string('grade', 'quiz'),
            'content' => quiz_format_grade($quiz, $displaygrade) . ' out of ' .
                quiz_format_grade($quiz, $quiz->grade) . ' (' .
                round($percentage, 2) . '%)'
        ];

        return parent::review_page(
            $attemptobj,
            $slots,
            $page,
            $showall,
            $lastpage,
            $displayoptions,
            $filtered
        );
    }
}
?>
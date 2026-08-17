<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Prints an instance of mod_thquizcluster.
 *
 * @package     mod_thquizcluster
 * @copyright   2023 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once $CFG->dirroot . '/mod/quiz/locallib.php';
require_once $CFG->libdir.'/gradelib.php';
require_once $CFG->libdir.'/completionlib.php';
require_once $CFG->dirroot.'/course/format/lib.php';
require_once $CFG->dirroot.'/mod/thquiz/quizcluster/attemptlib.php';
require_once $CFG->dirroot.'/mod/thquiz/renderer.php';
require_once($CFG->dirroot.'/mod/thquiz/quizcluster/locallib.php');

global $CFG, $DB, $PAGE;

// Course module id.
$id = optional_param('id', 0, PARAM_INT);
$cm_cluster_id = $id;

// Activity instance id.
$t = optional_param('t', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('thquiz', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('thquiz', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('thquiz', array('id' => $t), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('thquiz', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);

$PAGE->set_url('/mod/thquiz/view_cluster.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

$accessmanager = get_accessmanager_cluster($cm_cluster_id);
if($accessmanager->is_rule_seb()){
    $PAGE->set_pagelayout('secure');
    $PAGE->blocks->show_only_fake_blocks();
}

$quiz_listening = $moduleinstance->quiz_listening;
$quiz_reading = $moduleinstance->quiz_reading;
$quiz_writing = $moduleinstance->quiz_writing;
$quiz_speaking = $moduleinstance->quiz_speaking;

if ($quiz_listening || $quiz_reading || $quiz_writing || $quiz_speaking) {
    
    // Tạo mảng, loại bỏ các giá trị bị rỗng
    $cmid_quiz_raw = array($quiz_listening, $quiz_reading, $quiz_writing, $quiz_speaking);
    $cmid_quiz = array_filter($cmid_quiz_raw); 
    
    echo $OUTPUT->header();
    $i = 0;
    
    // chỉ chạy những bài nào đã được chọn
    foreach($cmid_quiz as $cmid){
        $i++;
        if ($cmid) {
            if (!$cm = get_coursemodule_from_id('quiz', $cmid)) {
                print_error('invalidcoursemodule');
            }
            if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
                print_error('coursemisconf');
            }
        } else {
            if (!$quiz = $DB->get_record('quiz', array('id' => $q))) {
                print_error('invalidquizid', 'quiz');
            }
            if (!$course = $DB->get_record('course', array('id' => $quiz->course))) {
                print_error('invalidcourseid');
            }
            if (!$cm = get_coursemodule_from_instance("quiz", $quiz->id, $course->id)) {
                print_error('invalidcoursemodule');
            }
        }

        // Check login and get context.
        // require_login($course, false, $cm);
        $context = context_module::instance($cm->id);
        require_capability('mod/quiz:view', $context);

        // Cache some other capabilities we use several times.
        $canattempt    = has_capability('mod/quiz:attempt', $context);
        $canreviewmine = has_capability('mod/quiz:reviewmyattempts', $context);
        $canpreview    = has_capability('mod/quiz:preview', $context);

        // Create an object to manage all the other (non-roles) access rules.
        $timenow       = time();
        $quizobj       = quiz_cluster::create($cm->instance, $USER->id);

        $accessmanager = new quiz_access_manager($quizobj, $timenow,
            has_capability('mod/quiz:ignoretimelimits', $context, null, false));
        $quiz = $quizobj->get_quiz();

        // Initialize $PAGE, compute blocks.
        $PAGE->set_url('/mod/thquiz/view_cluster.php', array('id' => $cm->id));

        // Create view object which collects all the information the renderer will need.
        $viewobj                = new mod_thquiz_view_object();
        $viewobj->accessmanager = $accessmanager;
        $viewobj->canreviewmine = $canreviewmine || $canpreview;

        // Get this user's attempts.
        $attempts            = quiz_get_user_attempts($quiz->id, $USER->id, 'finished', true);
        $lastfinishedattempt = end($attempts);
        $unfinished          = false;
        $unfinishedattemptid = null;
        if ($unfinishedattempt = quiz_get_user_attempt_unfinished($quiz->id, $USER->id)) {
            $attempts[] = $unfinishedattempt;

            $quizobj->create_attempt_object($unfinishedattempt)->handle_if_time_expired(time(), false);

            $unfinished = $unfinishedattempt->state == quiz_cluster_attempt::IN_PROGRESS ||
            $unfinishedattempt->state == quiz_cluster_attempt::OVERDUE;
            if (!$unfinished) {
                $lastfinishedattempt = $unfinishedattempt;
            }
            $unfinishedattemptid = $unfinishedattempt->id;
            $unfinishedattempt   = null;// To make it clear we do not use this again.
        }

        $numattempts = count($attempts);

        $viewobj->attempts    = $attempts;
        $viewobj->attemptobjs = array();
        foreach ($attempts as $attempt) {
            $viewobj->attemptobjs[] = new quiz_cluster_attempt($attempt, $quiz, $cm, $course, false);
        }

        // Work out the final grade
        if (!$canpreview) {
            $mygrade = quiz_get_best_grade($quiz, $USER->id);
        } else if ($lastfinishedattempt) {
            $mygrade = quiz_rescale_grade($lastfinishedattempt->sumgrades, $quiz, false);
        } else {
            $mygrade = null;
        }

        $mygradeoverridden = false;
        $gradebookfeedback = '';

        $grading_info = grade_get_grades($course->id, 'mod', 'quiz', $quiz->id, $USER->id);
        if (!empty($grading_info->items)) {
            $item = $grading_info->items[0];
            if (isset($item->grades[$USER->id])) {
                $grade = $item->grades[$USER->id];

                if ($grade->overridden) {
                    $mygrade           = $grade->grade+0;// Convert to number.
                    $mygradeoverridden = true;
                }
                if (!empty($grade->str_feedback)) {
                    $gradebookfeedback = $grade->str_feedback;
                }
            }
        }

        $title = $course->shortname.': '.format_string($quiz->name);
        $PAGE->set_title($title);
        $PAGE->set_heading($course->fullname);

        $output = $PAGE->get_renderer('mod_thquiz');

        // Print table with existing attempts.
        if ($attempts) {
            list($someoptions, $alloptions) = quiz_get_combined_reviewoptions($quiz, $attempts);

            $viewobj->attemptcolumn = $quiz->attempts != 1;
            $viewobj->gradecolumn = $someoptions->marks >= question_display_options::MARK_AND_MAX && quiz_has_grades($quiz);
            $viewobj->markcolumn   = $viewobj->gradecolumn && ($quiz->grade != $quiz->sumgrades);
            $viewobj->overallstats = $lastfinishedattempt && $alloptions->marks >= question_display_options::MARK_AND_MAX;
            $viewobj->feedbackcolumn = quiz_has_feedback($quiz) && $alloptions->overallfeedback;
        }

        $viewobj->timenow      = $timenow;
        $viewobj->numattempts  = $numattempts;
        $viewobj->mygrade      = $mygrade;
        $viewobj->moreattempts = $unfinished || !$accessmanager->is_finished($numattempts, $lastfinishedattempt);
        $viewobj->mygradeoverridden   = $mygradeoverridden;
        $viewobj->gradebookfeedback   = $gradebookfeedback;
        $viewobj->lastfinishedattempt = $lastfinishedattempt;
        $viewobj->canedit             = has_capability('mod/quiz:manage', $context);
        $viewobj->editurl             = new moodle_url('/mod/quiz/edit.php', array('cmid' => $cm->id));
        $viewobj->backtocourseurl     = new moodle_url('/course/view.php', array('id'     => $course->id));
        $viewobj->startattempturl     = $quizobj->start_attempt_url_cluster($cm_cluster_id);

        $viewobj->popuprequired = $accessmanager->attempt_must_be_in_popup();
        $viewobj->popupoptions  = $accessmanager->get_popup_options();

        $viewobj->infomessages = $viewobj->accessmanager->describe_rules();
        if ($quiz->attempts != 1) {
            $viewobj->infomessages[] = get_string('gradingmethod', 'quiz',
                quiz_get_grading_option_name($quiz->grademethod));
        }

        $viewobj->quizhasquestions = $quizobj->has_questions();
        $viewobj->preventmessages  = array();
        if (!$viewobj->quizhasquestions) {
            $viewobj->buttontext = '';
        } else {
            if ($unfinished) {
                if ($canpreview) {
                    $viewobj->buttontext = 'Bạn đang xem trước bài thi ' . format_string($quiz->name) . ', Tiếp tục xem trước';
                } else if ($canattempt) {
                    $viewobj->buttontext = 'Bạn đang làm bài thi ' . format_string($quiz->name) . ', Tiếp tục làm';
                }
            } else {
                if ($canpreview) {
                    $viewobj->buttontext = 'Bắt đầu xem trước bài thi ' . format_string($quiz->name);
                } else if ($canattempt) {
                    $viewobj->preventmessages = $viewobj->accessmanager->prevent_new_attempt(
                        $viewobj->numattempts, $viewobj->lastfinishedattempt);
                    if ($viewobj->preventmessages) {
                        $viewobj->buttontext = '';
                    } else if ($viewobj->numattempts == 0) {
                        $viewobj->buttontext = 'Bắt đầu bài thi ' . format_string($quiz->name);
                    } else {
                        $viewobj->buttontext = 'Bắt đầu bài thi ' . format_string($quiz->name);
                    }
                } 
            }

            if ($canpreview) {
                $viewobj->preventmessages = $viewobj->accessmanager->prevent_access();
            } else if ($viewobj->buttontext) {
                if (!$viewobj->moreattempts) {
                    $viewobj->buttontext = '';
                } else if ($canattempt
                    && $viewobj->preventmessages = $viewobj->accessmanager->prevent_access()) {
                    $viewobj->buttontext = '';
                }
            }
        }

        $viewobj->showbacktocourse = ($viewobj->buttontext === '' &&
            course_get_format($course)->has_view_page());

        if (isguestuser()) {
            echo '<div id="modal'.$i.'" class="modal-quizcluster" data-modal>
                    <div class="modal-quizcluster-content"> 
                        <span class="close" data-close>&times;</span>
                        '.$output->view_page_guest($course, $quiz, $cm, $context, $viewobj->infomessages).'
                    </div>
                </div>';
        } else if (!isguestuser() && !($canattempt || $canpreview || $viewobj->canreviewmine)) {
            echo '<div id="modal'.$i.'" class="modal-quizcluster" data-modal>
                    <div class="modal-quizcluster-content">
                        <span class="close" data-close>&times;</span>
                        '.$output->view_page_notenrolled($course, $quiz, $cm, $context, $viewobj->infomessages).'
                    </div>
                </div>';
        } else {
            echo '<div id="modal'.$i.'" class="modal-quizcluster" data-modal>
                    <div class="modal-quizcluster-content">
                        <span class="close" data-close>&times;</span>
                        '.$output->view_page_quiz($course, $quiz, $cm, $context, $viewobj, $cm_cluster_id).'
                    </div>
                </div>';
        }
    }

    // ĐOẠN HTML VẼ CÁC NÚT
    echo '<div class="exam-detail">
            <div class="practice-list__card">
                <div class="practice-list__items flex-grid">';
                
    // Biến $j dùng để theo dõi chỉ số modal tương ứng (vì $i ở vòng lặp trên tự tăng tùy theo mảng)
    $j = 1; 

    // Nếu có chọn Listening thì in nút Listening (Gọi modal id là modal$j)
    if ($quiz_listening) {
        echo '      <div class="practice-item -blue">
                        <span class="practice-item__icon -listening"></span>
                        <h6 class="practice-item__title">Listening</h6>
                        <button data-modal-target="#modal'.$j.'" data-mode="listening" class="practice-item__btn iot-gribt -blue -listening"><i class="fa-solid fa-bolt"></i> Làm bài</button>
                    </div>';
        $j++;
    }

    if ($quiz_reading) {
        echo '      <div class="practice-item -blue">
                        <span class="practice-item__icon -reading"></span>
                        <h6 class="practice-item__title">Reading</h6>
                        <button data-modal-target="#modal'.$j.'" data-mode="reading" class="practice-item__btn iot-gribt -green -reading"><i class="fa-solid fa-bolt"></i> Làm bài</button>
                    </div>';
        $j++;
    }

    if ($quiz_writing) {
        echo '      <div class="practice-item -blue">
                        <span class="practice-item__icon -writing"></span>
                        <h6 class="practice-item__title">Writing</h6>
                        <button data-modal-target="#modal'.$j.'" data-mode="writing" class="practice-item__btn iot-gribt -orange -writing"><i class="fa-solid fa-bolt"></i> Làm bài</button>
                    </div>';
        $j++;
    }

    if ($quiz_speaking) {
        echo '      <div class="practice-item -blue">
                        <span class="practice-item__icon -speaking"></span>
                        <h6 class="practice-item__title">Speaking</h6>
                        <button data-modal-target="#modal'.$j.'" data-mode="speaking" class="practice-item__btn iot-gribt -pink -speaking"><i class="fa-solid fa-bolt"></i> Làm bài</button>
                    </div>';
    }

    echo '      </div>
            </div>
        </div>';
    echo $OUTPUT->footer();

} else {
    // Nếu cả 4 cái đều "Chưa chọn" thì báo lỗi
    echo $OUTPUT->header();
    echo 'Chưa chọn quiz cluster';
    echo $OUTPUT->footer();
}
?>

<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', (event) => {
      // Mở modal
      document.querySelectorAll('[data-modal-target]').forEach(button => {
        button.addEventListener('click', () => {
          const modal = document.querySelector(button.dataset.modalTarget);
          openModal(modal);
        });
      });

      // Đóng modal khi nhấn nút đóng
      document.querySelectorAll('[data-close]').forEach(button => {
        button.addEventListener('click', () => {
          const modal = button.closest('.modal-quizcluster');
          closeModal(modal);
        });
      });

      // Đóng modal khi nhấn ra ngoài khu vực modal
      document.querySelectorAll('.modal-quizcluster').forEach(modal => {
        modal.addEventListener('click', (e) => {
          if (e.currentTarget === e.target) {
            closeModal(modal);
          }
        });
      });

      function openModal(modal) {
        if (modal == null) return;
        modal.style.display = 'block';
      }

      function closeModal(modal) {
        if (modal == null) return;
        modal.style.display = 'none';
      }
    });
</script>



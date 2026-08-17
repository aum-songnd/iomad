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
 * Defines the renderer for the thquiz module.
 *
 * @package   mod_thquiz
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
require_once $CFG->dirroot . '/mod/quiz/renderer.php';
require_once($CFG->dirroot . '/question/engine/renderer.php');
require_once $CFG->dirroot . '/mod/quiz/locallib.php';
require_once $CFG->libdir . '/gradelib.php';
require_once $CFG->libdir . '/completionlib.php';
require_once $CFG->dirroot . '/course/format/lib.php';
require_once $CFG->dirroot . '/mod/thquiz/quizcluster/attemptlib.php';

/**
 * The renderer for the thquiz module.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class mod_thquiz_links_to_other_attempts implements renderable {
    /**
     * @var array string attempt number => url, or null for the current attempt.
     * url may be either a moodle_url, or a renderable.
     */
    public $links = array();
}


class mod_thquiz_view_object {
    /** @var array $infomessages of messages with information to display about the thquiz. */
    public $infomessages;
    /** @var array $attempts contains all the user's attempts at this thquiz. */
    public $attempts;
    /** @var array $attemptobjs thquiz_attempt objects corresponding to $attempts. */
    public $attemptobjs;
    /** @var thquiz_access_manager $accessmanager contains various access rules. */
    public $accessmanager;
    /** @var bool $canreviewmine whether the current user has the capability to
     *       review their own attempts. */
    public $canreviewmine;
    /** @var bool $canedit whether the current user has the capability to edit the thquiz. */
    public $canedit;
    /** @var moodle_url $editurl the URL for editing this thquiz. */
    public $editurl;
    /** @var int $attemptcolumn contains the number of attempts done. */
    public $attemptcolumn;
    /** @var int $gradecolumn contains the grades of any attempts. */
    public $gradecolumn;
    /** @var int $markcolumn contains the marks of any attempt. */
    public $markcolumn;
    /** @var int $overallstats contains all marks for any attempt. */
    public $overallstats;
    /** @var string $feedbackcolumn contains any feedback for and attempt. */
    public $feedbackcolumn;
    /** @var string $timenow contains a timestamp in string format. */
    public $timenow;
    /** @var int $numattempts contains the total number of attempts. */
    public $numattempts;
    /** @var float $mygrade contains the user's final grade for a thquiz. */
    public $mygrade;
    /** @var bool $moreattempts whether this user is allowed more attempts. */
    public $moreattempts;
    /** @var int $mygradeoverridden contains an overriden grade. */
    public $mygradeoverridden;
    /** @var string $gradebookfeedback contains any feedback for a gradebook. */
    public $gradebookfeedback;
    /** @var bool $unfinished contains 1 if an attempt is unfinished. */
    public $unfinished;
    /** @var object $lastfinishedattempt the last attempt from the attempts array. */
    public $lastfinishedattempt;
    /** @var array $preventmessages of messages telling the user why they can't
     *       attempt the thquiz now. */
    public $preventmessages;
    /** @var string $buttontext caption for the start attempt button. If this is null, show no
     *      button, or if it is '' show a back to the course button. */
    public $buttontext;
    /** @var moodle_url $startattempturl URL to start an attempt. */
    public $startattempturl;
    /** @var mod_thquiz_preflight_check_form|null $preflightcheckform confirmation form that must be
     *       submitted before an attempt is started, if required. */
    public $preflightcheckform;
    /** @var moodle_url $startattempturl URL for any Back to the course button. */
    public $backtocourseurl;
    /** @var bool $showbacktocourse should we show a back to the course button? */
    public $showbacktocourse;
    /** @var bool whether the attempt must take place in a popup window. */
    public $popuprequired;
    /** @var array options to use for the popup window, if required. */
    public $popupoptions;
    /** @var bool $thquizhasquestions whether the thquiz has any questions. */
    public $thquizhasquestions;

    public function __get($field) {
        switch ($field) {
            case 'startattemptwarning':
                debugging('startattemptwarning has been deprecated. It is now always blank.');
                return '';

            default:
                debugging('Unknown property ' . $field);
                return null;
        }
    }
}

class mod_thquiz_renderer extends mod_quiz_renderer {
	
    public function attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id,
		$nextpage) {
		$output = '';
		$output .= $this->header();
		$output .= $this->quiz_notices($messages);
		$output .= $this->countdown_timer($attemptobj, time());
		$output .= $this->attempt_form($attemptobj, $page, $slots, $id, $nextpage);
		$output .= $this->footer();
		return $output;
	}

	public function attempt_cluster_page($attemptobj, $page, $accessmanager, $messages, $slots, $id,
		$nextpage, $cm_cluster_id) {
		$output = '';
		$output .= $this->header();
		$output .= $this->quiz_notices($messages);
		$output .= $this->countdown_timer($attemptobj, time());
		$output .= $this->attempt_cluster_form($attemptobj, $page, $slots, $id, $nextpage, $cm_cluster_id);
		$output .= $this->footer();
		return $output;
	}

	public function view_page($course, $quiz, $cm, $context, $viewobj) {
		$output = '';
		$output .= $this->view_information($quiz, $cm, $context, $viewobj->infomessages);
		$output .= $this->view_table($quiz, $context, $viewobj);
		$output .= $this->view_result_info($quiz, $context, $cm, $viewobj);
		$output .= $this->box($this->view_page_buttons($viewobj), 'quizattempt');
		return $output;
	}

    public function view_page_cluster($course, $quiz, $cm, $context, $viewobj) {
        $output = '';
        // $output .= $this->view_information($quiz, $cm, $context, $viewobj->infomessages);
		$output .= $this->view_table($quiz, $context, $viewobj);
		$output .= $this->view_result_info($quiz, $context, $cm, $viewobj);
        $output .= $this->view_page_cluster_tertiary_nav($viewobj);
        $output .= $this->box($this->view_cluster_page_buttons($viewobj), 'quizattempt');
        return $output;
    }

    public function view_page_quiz($course, $quiz, $cm, $context, $viewobj, $cm_cluster_id) {
    	global $OUTPUT, $USER;
        $output = '';
        $output .= '<div class="quiz_title">';
        $output .= $OUTPUT->heading($quiz->name);
        $output .= '</div>';

        $context = context_module::instance($cm->id);
        $canpreview = has_capability('mod/quiz:preview', $context);
        $output .= '<div class="quiz_detail">';
        if(!$canpreview){
    		if($viewobj->moreattempts){
                if(!$viewobj->quizhasquestions) {
                    $output .= 'Chưa có câu hỏi nào trong bài kiểm tra này !';
                } else {
                    $output .= $this->view_information($quiz, $cm, $context, $viewobj->infomessages);
                    $output .= $this->view_table_cluster($quiz, $context, $viewobj, $cm_cluster_id);
                    $output .= $this->view_page_cluster_tertiary_nav($viewobj);
                    // $output .= $this->view_result_info($quiz, $context, $cm, $viewobj);
                    $output .= $this->box($this->view_cluster_page_buttons($viewobj), 'quizattempt');
                }    
            } else {
                $output .= $this->view_information($quiz, $cm, $context, $viewobj->infomessages);
    			$output .= $this->view_table_cluster($quiz, $context, $viewobj, $cm_cluster_id);
                // $output .= $this->view_result_info($quiz, $context, $cm, $viewobj);
    			$output .= 'Bạn đã hoàn thành bài kiểm tra này !';
    		}
        } else {
            $output .= $this->view_information($quiz, $cm, $context, $viewobj->infomessages);
            $output .= $this->view_table_cluster($quiz, $context, $viewobj, $cm_cluster_id);
            $output .= $this->view_result_info($quiz, $context, $cm, $viewobj);
            $output .= $this->view_page_cluster_tertiary_nav($viewobj);
            $output .= $this->box($this->view_cluster_page_buttons($viewobj), 'quizattempt');
        }
        $output .= '</div>';
	    return $output;
    }

    public function view_page_quiz1($course, $quiz, $cm, $context, $viewobj, $cm_cluster_id) {
    	global $OUTPUT;

		$context = context_module::instance($cm->id);
        $canpreview = has_capability('mod/quiz:preview', $context);

        $output = '';
		if(!$canpreview){
			if($viewobj->moreattempts){
				if(!$viewobj->quizhasquestions) {
					$output .= 'Chưa có câu hỏi nào trong bài kiểm tra này !';
				} else {
					$output .= $this->view_table_cluster($quiz, $context, $viewobj, $cm_cluster_id);
					$output .= $this->view_page_cluster_tertiary_nav($viewobj);
					$output .= $this->box($this->view_cluster_page_buttons($viewobj), 'quizattempt');
				}
			} else {
				$output .= $this->view_table_cluster($quiz, $context, $viewobj, $cm_cluster_id);
				$output .= 'Bạn đã hoàn thành bài kiểm tra này !';
			}
		} else {
            // $output .= $this->view_information($quiz, $cm, $context, $viewobj->infomessages);
            $output .= $this->view_table_cluster($quiz, $context, $viewobj, $cm_cluster_id);
            $output .= $this->view_result_info($quiz, $context, $cm, $viewobj);
            $output .= $this->view_page_cluster_tertiary_nav($viewobj);
            $output .= $this->box($this->view_cluster_page_buttons($viewobj), 'quizattempt');
        }

	    return $output;
    }

    // public function view_information($quiz, $cm, $context, $messages, bool $quizhasquestions = false) {
    //     $output = '';

	// 	function filterGradingMethod($value) {
	// 	    return strpos($value, 'Grading method: Highest grade') === false;
	// 	}
	// 	$messages = array_filter($messages, 'filterGradingMethod');

    //     // Output any access messages.
    //     if ($messages) {
    //         $output .= $this->box($this->access_messages($messages), 'quizinfo');
    //     }

    //     if (has_any_capability(['mod/quiz:manageoverrides', 'mod/quiz:viewoverrides'], $context)) {
    //         if ($overrideinfo = $this->quiz_override_summary_links($quiz, $cm)) {
    //             $output .= html_writer::tag('div', $overrideinfo, ['class' => 'quizattemptcounts']);
    //         }
    //     }

    //     return $output;
    // }

    public function view_page_cluster_tertiary_nav(mod_thquiz_view_object $viewobj): string {
        $content = '';

        if($viewobj->buttontext){
        	$attemptbtn = $this->start_attempt_button($viewobj->buttontext,
                $viewobj->startattempturl, $viewobj->preflightcheckform,
                $viewobj->popuprequired, $viewobj->popupoptions);
        	$content .= $attemptbtn;
        }
        
        if ($content) {
            return html_writer::div(html_writer::div($content, 'row'), 'container-fluid tertiary-navigation');
        } else {
            return '';
        }
    }

    public function view_cluster_page_buttons(mod_thquiz_view_object $viewobj) {
        $output = '';
        $output .= $this->access_messages($viewobj->preventmessages);

        if ($viewobj->showbacktocourse) {
            $output .= $this->single_button($viewobj->backtocourseurl,
                    get_string('backtocourse', 'quiz'), 'get',
                    array('class' => 'continuebutton'));
        }

        return $output;
    }

    public function view_table_cluster($quiz, $context, $viewobj, $cm_cluster_id) {

    	if (!$viewobj->attempts) {
            return '';
        }

        // Prepare table header.
        $table = new html_table();
        $table->attributes['class'] = 'generaltable quizattemptsummary';
        $table->head = array();
        $table->align = array();
        $table->size = array();
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
            $table->head[] = get_string('marks', 'quiz') . ' / ' .
                    quiz_format_grade($quiz, $quiz->sumgrades);
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->gradecolumn) {
            $table->head[] = get_string('gradenoun') . ' / ' .
                    quiz_format_grade($quiz, $quiz->grade);
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

        // One row for each attempt.
        foreach ($viewobj->attemptobjs as $attemptobj) {
            $attemptoptions = $attemptobj->get_display_options(true);
            $row = array();

            // Add the attempt number.
            if ($viewobj->attemptcolumn) {
                if ($attemptobj->is_preview()) {
                    $row[] = get_string('preview', 'quiz');
                } else {
                    $row[] = $attemptobj->get_attempt_number();
                }
            }

            $row[] = $this->attempt_state($attemptobj);

            if ($viewobj->markcolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                        $attemptobj->is_finished()) {
                    $row[] = quiz_format_grade($quiz, $attemptobj->get_sum_marks());
                } else {
                    $row[] = '';
                }
            }

            // Ouside the if because we may be showing feedback but not grades.
            $attemptgrade = quiz_rescale_grade($attemptobj->get_sum_marks(), $quiz, false);

            if ($viewobj->gradecolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                        $attemptobj->is_finished()) {

                    // Highlight the highest grade if appropriate.
                    if ($viewobj->overallstats && !$attemptobj->is_preview()
                            && $viewobj->numattempts > 1 && !is_null($viewobj->mygrade)
                            && $attemptobj->get_state() == quiz_attempt::FINISHED
                            && $attemptgrade == $viewobj->mygrade
                            && $quiz->grademethod == QUIZ_GRADEHIGHEST) {
                        $table->rowclasses[$attemptobj->get_attempt_number()] = 'bestrow';
                    }

                    $row[] = quiz_format_grade($quiz, $attemptgrade);
                } else {
                    $row[] = '';
                }
            }

            if ($viewobj->canreviewmine) {
                $attempt = $attemptobj->get_attempt();
                $quizobj = $attemptobj->get_quizobj();
                if ($attemptoptions->attempt) {
                    $row[] = $this->review_link($quizobj->review_cluster_url($attempt->id, $cm_cluster_id),
                        null, array());
                } else {
                    $when = quiz_attempt_state($quizobj->get_quiz(), $attempt);
                    $row[] = $this->no_review_message($quizobj->cannot_review_message($when, true));
                }
            }

            if ($viewobj->feedbackcolumn && $attemptobj->is_finished()) {
                if ($attemptoptions->overallfeedback) {
                    $row[] = quiz_feedback_for_grade($attemptgrade, $quiz, $context);
                } else {
                    $row[] = '';
                }
            }

            if ($attemptobj->is_preview()) {
                $table->data['preview'] = $row;
            } else {
                $table->data[$attemptobj->get_attempt_number()] = $row;
            }
        } // End of loop over attempts.

        $output = '';
        $output .= $this->view_table_heading();
        $output .= html_writer::table($table);
        return $output;
    }

	public function navigation_panel_cluster1(quiz_nav_panel_base $panel, $cm_cluster_id) {

		$output = '';
		$userpicture = $panel->user_picture();
		if ($userpicture) {
			$fullname = fullname($userpicture->user);
			if ($userpicture->size === true) {
				$fullname = html_writer::div($fullname);
			}
			$output .= html_writer::tag('div', $this->render($userpicture) . $fullname,
				array('id' => 'user-picture', 'class' => 'clearfix'));
		}

		$output .= $panel->render_before_button_bits($this);

		$bcc = $panel->get_button_container_class();
		$output .= html_writer::start_tag('div', array('class' => "qn_buttons clearfix $bcc"));

        // print_object($panel);
        // exit;

		foreach ($panel->get_question_cluster_buttons($cm_cluster_id) as $button) {
			$output .= $this->render($button);
		}
		$output .= html_writer::end_tag('div');

		$output .= html_writer::tag('div', $panel->render_end_bits_cluster($this, $cm_cluster_id),
			array('class' => 'othernav'));

		$this->page->requires->js_init_call('M.mod_quiz.nav.init', null, false,
			quiz_get_js_module());

		return $output;
	}

	public function summary_page($attemptobj, $displayoptions) {
		$output = '';
		$output .= $this->header();
		$output .= $this->heading(format_string($attemptobj->get_quiz_name()));
		$output .= $this->heading(get_string('summaryofattempt', 'quiz'), 3);
		$output .= $this->summary_table($attemptobj, $displayoptions);
		$output .= $this->summary_page_controls($attemptobj);
		$output .= $this->footer();
		return $output;
	}

	public function summary_cluster_page($attemptobj, $displayoptions, $cm_cluster_id) {
		$output = '';
		$output .= $this->header();
		$output .= $this->heading(format_string($attemptobj->get_quiz_name()));
		$output .= $this->heading(get_string('summaryofattempt', 'quiz'), 3);
		$output .= $this->summary_table($attemptobj, $displayoptions);
		$output .= $this->summary_cluster_page_controls($attemptobj, $cm_cluster_id);
		$output .= $this->footer();
		return $output;
	}

	public function summary_page_controls($attemptobj) {
		$output = '';

		// Return to place button.
		if ($attemptobj->get_state() == quiz_cluster_attempt::IN_PROGRESS) {
			$button = new single_button(
				new moodle_url($attemptobj->attempt_url(null, $attemptobj->get_currentpage())),
				get_string('returnattempt', 'quiz'));
			$output .= $this->container($this->container($this->render($button),
				'controls'), 'submitbtns mdl-align');
		}

		// Finish attempt button.
		$options = array(
			'attempt' => $attemptobj->get_attemptid(),
			'finishattempt' => 1,
			'timeup' => 0,
			'slots' => '',
			'cmid' => $attemptobj->get_cmid(),
			'sesskey' => sesskey(),
		);

		$button = new single_button(
			new moodle_url($attemptobj->processattempt_url(), $options),
			get_string('submitallandfinish', 'quiz'));
		$button->id = 'responseform';
        $button->class = 'btn-finishattempt';
        $button->formid = 'frm-finishattempt';
		if ($attemptobj->get_state() == quiz_cluster_attempt::IN_PROGRESS) {
			$button->add_action(new confirm_action(get_string('confirmclose', 'quiz'), null,
				get_string('submitallandfinish', 'quiz')));
		}
        $button->primary = true;

		$duedate = $attemptobj->get_due_date();
		$message = '';
		if ($attemptobj->get_state() == quiz_cluster_attempt::OVERDUE) {
			$message = get_string('overduemustbesubmittedby', 'quiz', userdate($duedate));

		} else if ($duedate) {
			$message = get_string('mustbesubmittedby', 'quiz', userdate($duedate));
		}

		$output .= $this->countdown_timer($attemptobj, time());
		$output .= $this->container($message . $this->container(
			$this->render($button), 'controls'), 'submitbtns mdl-align');

		return $output;
	}

	public function summary_cluster_page_controls($attemptobj, $cm_cluster_id) {
		$output = '';

		// Return to place button.
		if ($attemptobj->get_state() == quiz_cluster_attempt::IN_PROGRESS) {
			$button = new single_button(
				new moodle_url($attemptobj->attempt_url(null, $attemptobj->get_currentpage()), array('cm_cluster_id' => $cm_cluster_id)),
				get_string('returnattempt', 'quiz'));
			$output .= $this->container($this->container($this->render($button),
				'controls'), 'submitbtns mdl-align');
		}

		// Finish attempt button.
		$options = array(
			'attempt' => $attemptobj->get_attemptid(),
			'finishattempt' => 1,
			'timeup' => 0,
			'slots' => '',
			'cmid' => $attemptobj->get_cmid(),
			'sesskey' => sesskey(),
			'cm_cluster_id' => $cm_cluster_id,
		);

		$button = new single_button(
			new moodle_url($attemptobj->processattempt_url(), $options),
			get_string('submitallandfinish', 'quiz'));
		$button->id = 'responseform';
        $button->class = 'btn-finishattempt';
        $button->formid = 'frm-finishattempt';
		if ($attemptobj->get_state() == quiz_cluster_attempt::IN_PROGRESS) {
			$button->add_action(new confirm_action(get_string('confirmclose', 'quiz'), null,
				get_string('submitallandfinish', 'quiz')));
		}
        $button->primary = true;

		$duedate = $attemptobj->get_due_date();
		$message = '';
		if ($attemptobj->get_state() == quiz_cluster_attempt::OVERDUE) {
			$message = get_string('overduemustbesubmittedby', 'quiz', userdate($duedate));

		} else if ($duedate) {
			$message = get_string('mustbesubmittedby', 'quiz', userdate($duedate));
		}

		$output .= $this->countdown_timer($attemptobj, time());
		$output .= $this->container($message . $this->container(
			$this->render($button), 'controls'), 'submitbtns mdl-align');

		return $output;
	}

	public function attempt_form($attemptobj, $page, $slots, $id, $nextpage) {
		$output = '';

		// Start the form.
		$output .= html_writer::start_tag('form',
			array('action' => new moodle_url($attemptobj->processattempt_url(),
				array('cmid' => $attemptobj->get_cmid())), 'method' => 'post',
				'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
				'id' => 'responseform'));
		$output .= html_writer::start_tag('div');

		// Print all the questions.
		foreach ($slots as $slot) {
			$output .= $attemptobj->render_question($slot, false, $this,
				$attemptobj->attempt_url($slot, $page), $this);
		}

		$navmethod = $attemptobj->get_quiz()->navmethod;
		$output .= $this->attempt_navigation_buttons($page, $attemptobj->is_last_page($page), $navmethod);

		// Some hidden fields to trach what is going on.
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'attempt',
			'value' => $attemptobj->get_attemptid()));
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'thispage',
			'value' => $page, 'id' => 'followingpage'));
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'nextpage',
			'value' => $nextpage));
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'timeup',
			'value' => '0', 'id' => 'timeup'));
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey',
			'value' => sesskey()));
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'scrollpos',
			'value' => '', 'id' => 'scrollpos'));

		// Add a hidden field with questionids. Do this at the end of the form, so
		// if you navigate before the form has finished loading, it does not wipe all
		// the student's answers.
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slots',
			'value' => implode(',', $attemptobj->get_active_slots($page))));

		// Finish the form.
		$output .= html_writer::end_tag('div');
		$output .= html_writer::end_tag('form');

		$output .= $this->connection_warning();

		return $output;
	}

	public function attempt_cluster_form($attemptobj, $page, $slots, $id, $nextpage, $cm_cluster_id) {
		$output = '';

		// Start the form.
		$output .= html_writer::start_tag('form',
			array('action' => new moodle_url($attemptobj->processattempt_url(),
				array('cmid' => $attemptobj->get_cmid())), 'method' => 'post',
				'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
				'id' => 'responseform', 'cm_cluster_id' => $cm_cluster_id));
		$output .= html_writer::start_tag('div');

		// Print all the questions.
		$firstQ = '';
		$remainQ = '';
		$ques = 0;
		foreach ($slots as $slot) {
			$ques++;
			$rendered = $attemptobj->render_question($slot, false, $this, $attemptobj->attempt_url($slot, $page), $this);
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
			$width = (int) $item->getAttribute("value");
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
		}
		else {
			$output .= $firstQ . $remainQ;
		}

		$navmethod = $attemptobj->get_quiz()->navmethod;
		$output .= $this->attempt_navigation_buttons($page, $attemptobj->is_last_page($page), $navmethod);

		// Some hidden fields to trach what is going on.
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'attempt',
			'value' => $attemptobj->get_attemptid()));
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'thispage',
			'value' => $page, 'id' => 'followingpage'));
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'nextpage',
			'value' => $nextpage));
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'timeup',
			'value' => '0', 'id' => 'timeup'));
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey',
			'value' => sesskey()));
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'scrollpos',
			'value' => '', 'id' => 'scrollpos'));
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'cm_cluster_id',
			'value' => $cm_cluster_id));

		// Add a hidden field with questionids. Do this at the end of the form, so
		// if you navigate before the form has finished loading, it does not wipe all
		// the student's answers.
		$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slots',
			'value' => implode(',', $attemptobj->get_active_slots($page))));

		// Finish the form.
		$output .= html_writer::end_tag('div');
		$output .= html_writer::end_tag('form');

		$output .= $this->connection_warning();

		return $output;
	}

	//review

	public function review_page(quiz_attempt $attemptobj, $slots, $page, $showall,
		$lastpage, mod_quiz_display_options $displayoptions,
		$summarydata) {
		$output = '';
		$output .= $this->header();
		$output .= $this->review_summary_table($summarydata, $page);
		$output .= $this->review_form($page, $showall, $displayoptions,
			$this->questions($attemptobj, true, $slots, $page, $showall, $displayoptions),
			$attemptobj);

		$output .= $this->review_next_navigation($attemptobj, $page, $lastpage, $showall);
		$output .= $this->footer();
		return $output;
	}

	public function review_cluster_page($cm_cluster_id, quiz_attempt $attemptobj, $slots, $page, $showall,
		$lastpage, mod_quiz_display_options $displayoptions,
		$summarydata) {
		$output = '';
		$output .= $this->header();
		$output .= $this->review_summary_table($summarydata, $page);
		$output .= $this->review_form($page, $showall, $displayoptions,
			$this->questions($attemptobj, true, $slots, $page, $showall, $displayoptions),
			$attemptobj);

		$output .= $this->review_cluster_next_navigation($cm_cluster_id, $attemptobj, $page, $lastpage, $showall);
		$output .= $this->footer();
		return $output;
	}

	public function review_next_navigation(quiz_attempt $attemptobj, $page, $lastpage, $showall = null) {
		$nav = '';
		if ($page > 0) {
			$nav .= link_arrow_left(get_string('navigateprevious', 'quiz'),
				$attemptobj->review_url(null, $page - 1, $showall), false, 'mod_quiz-prev-nav');
		}
		if ($lastpage) {
			$nav .= $this->finish_review_link($attemptobj);
		} else {
			$nav .= link_arrow_right(get_string('navigatenext', 'quiz'),
				$attemptobj->review_url(null, $page + 1, $showall), false, 'mod_quiz-next-nav');
		}
		return html_writer::tag('div', $nav, array('class' => 'submitbtns'));
	}

	public function review_cluster_next_navigation($cm_cluster_id, quiz_attempt $attemptobj, $page, $lastpage, $showall = null) {
		$nav = '';
		if ($page > 0) {
			$nav .= link_arrow_left(get_string('navigateprevious', 'quiz'),
				$attemptobj->review_cluster_url($cm_cluster_id, null, $page - 1, $showall), false, 'mod_quiz-prev-nav');
		}
		if ($lastpage) {
			$nav .= $this->finish_cluster_review_link($attemptobj, $cm_cluster_id);
		} else {
			$nav .= link_arrow_right(get_string('navigatenext', 'quiz'),
				$attemptobj->review_cluster_url($cm_cluster_id, null, $page + 1, $showall), false, 'mod_quiz-next-nav');
		}
		return html_writer::tag('div', $nav, array('class' => 'submitbtns'));
	}

	public function finish_review_link(quiz_attempt $attemptobj) {
		global $CFG;
		$url = $CFG->wwwroot.'/mod/thquizcluster/view.php?id=';

		if ($attemptobj->get_access_manager(time())->attempt_must_be_in_popup()) {
			$this->page->requires->js_init_call('M.mod_quiz.secure_window.init_close_button',
				array($url), false, quiz_get_js_module());
			return html_writer::empty_tag('input', array('type' => 'button',
				'value' => get_string('finishreview', 'quiz'),
				'id' => 'secureclosebutton',
				'class' => 'mod_quiz-next-nav btn btn-primary'));

		} else {
			return html_writer::link($url, get_string('finishreview', 'quiz'),
				array('class' => 'mod_quiz-next-nav'));
		}
	}

	public function finish_cluster_review_link(quiz_attempt $attemptobj, $cm_cluster_id) {
		global $CFG;
		$url = $CFG->wwwroot."/mod/thquiz/view_cluster.php?id=$cm_cluster_id";

		if ($attemptobj->get_access_manager(time())->attempt_must_be_in_popup()) {
			$this->page->requires->js_init_call('M.mod_quiz.secure_window.init_close_button',
				array($url), false, quiz_get_js_module());
			return html_writer::empty_tag('input', array('type' => 'button',
				'value' => get_string('finishreview', 'quiz'),
				'id' => 'secureclosebutton',
				'class' => 'mod_quiz-next-nav btn btn-primary'));

		} else {
			return html_writer::link($url, get_string('finishreview', 'quiz'),
				array('class' => 'mod_quiz-next-nav'));
		}
	}

	public function navigation_panel_cluster(quiz_nav_panel_base $panel, $cm_cluster_id) {
        global $DB, $PAGE, $USER;
        $quiz_id = $this->th_get_panel_quiz_id($panel);

        $cm_cluster = get_coursemodule_from_id('thquiz', $cm_cluster_id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $cm_cluster->course), '*', MUST_EXIST);
        $module_cluster = $DB->get_record('thquiz', array('id' => $cm_cluster->instance), '*', MUST_EXIST);

        $quiz_listening = $module_cluster->quiz_listening;
        $quiz_reading = $module_cluster->quiz_reading;
        $quiz_writing = $module_cluster->quiz_writing;
        $quiz_speaking = $module_cluster->quiz_speaking;

        // ======================================================================
        // FIX LỖI: LỌC CÁC BÀI THI ĐƯỢC CHỌN THAY VÌ BẮT BUỘC PHẢI ĐỦ 4 BÀI
        // ======================================================================
        $raw_modules = array($quiz_listening, $quiz_reading, $quiz_writing, $quiz_speaking);
        $quiz_module = array_filter($raw_modules); // Loại bỏ những cái rỗng (0)

        // CHỈ CHẠY NẾU CÓ ÍT NHẤT 1 BÀI ĐƯỢC CHỌN
        if (!empty($quiz_module)) {
            $list_quiz = implode(",", $quiz_module);
            $regions = $PAGE->blocks->get_regions();
            
            $query = "select {course_modules}.id, {quiz}.name
                from {course_modules}
                inner join {quiz}
                on {course_modules}.instance = {quiz}.id and
                {course_modules}.id in ($list_quiz)";

            $records = $DB->get_records_sql($query);

            foreach ($quiz_module as $id) {
                // Kiểm tra xem ID có tồn tại trong kết quả trả về không để tránh lỗi
                if (!isset($records[$id])) {
                    continue; 
                }
                
                $quiz_name = $records[$id]->name;

                if ($id != $quiz_id) {
                    if ($id) {
                        if (!$cm = get_coursemodule_from_id('quiz', $id)) {
                            throw new \moodle_exception('invalidcoursemodule');
                        }
                        if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
                            throw new \moodle_exception('coursemisconf');
                        }
                    } else {
                        if (!$quiz = $DB->get_record('quiz', array('id' => $q))) {
                            throw new \moodle_exception('invalidquizid', 'quiz');
                        }
                        if (!$course = $DB->get_record('course', array('id' => $quiz->course))) {
                            throw new \moodle_exception('invalidcourseid');
                        }
                        if (!$cm = get_coursemodule_from_instance("quiz", $quiz->id, $course->id)) {
                            throw new \moodle_exception('invalidcoursemodule');
                        }
                    }

                    $context = context_module::instance($cm->id);
                    require_capability('mod/quiz:view', $context);

                    $canattempt = has_capability('mod/quiz:attempt', $context);
                    $canreviewmine = has_capability('mod/quiz:reviewmyattempts', $context);
                    $canpreview = has_capability('mod/quiz:preview', $context);

                    $timenow = time();
                    $quizobj = quiz_cluster::create($cm->instance, $USER->id);
                    $accessmanager = new quiz_access_manager($quizobj, $timenow,
                        has_capability('mod/quiz:ignoretimelimits', $context, null, false));
                    $quiz = $quizobj->get_quiz();

                    $viewobj = new mod_thquiz_view_object();
                    $viewobj->accessmanager = $accessmanager;
                    $viewobj->canreviewmine = $canreviewmine || $canpreview;

                    $attempts = quiz_get_user_attempts($quiz->id, $USER->id, 'finished', true);
                    $lastfinishedattempt = end($attempts);
                    $unfinished = false;
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
                        $unfinishedattempt = null; 
                    }
                    $numattempts = count($attempts);

                    $viewobj->attempts = $attempts;
                    $viewobj->attemptobjs = array();
                    foreach ($attempts as $attempt) {
                        $viewobj->attemptobjs[] = new quiz_cluster_attempt($attempt, $quiz, $cm, $course, false);
                    }

                    if (!$canpreview) {
                        $mygrade = quiz_get_best_grade($quiz, $USER->id);
                    } else if ($lastfinishedattempt) {
                        $mygrade = quiz_rescale_grade($lastfinishedattempt->sumgrades, $quiz, false);
                    } else {
                        $mygrade = null;
                    }

                    $mygradeoverridden = false;
                    $gradebookfeedback = '';
                    $item = null;

                    $grading_info = grade_get_grades($course->id, 'mod', 'quiz', $quiz->id, $USER->id);
                    if (!empty($grading_info->items)) {
                        $item = $grading_info->items[0];
                        if (isset($item->grades[$USER->id])) {
                            $grade = $item->grades[$USER->id];

                            if ($grade->overridden) {
                                $mygrade = $grade->grade + 0; 
                                $mygradeoverridden = true;
                            }
                            if (!empty($grade->str_feedback)) {
                                $gradebookfeedback = $grade->str_feedback;
                            }
                        }
                    }

                    $output = $PAGE->get_renderer('mod_thquiz');

                    if ($attempts) {
                        list($someoptions, $alloptions) = quiz_get_combined_reviewoptions($quiz, $attempts);

                        $viewobj->attemptcolumn = $quiz->attempts != 1;
                        $viewobj->gradecolumn = $someoptions->marks >= question_display_options::MARK_AND_MAX && quiz_has_grades($quiz);
                        $viewobj->markcolumn = $viewobj->gradecolumn && ($quiz->grade != $quiz->sumgrades);
                        $viewobj->overallstats = $lastfinishedattempt && $alloptions->marks >= question_display_options::MARK_AND_MAX;
                        $viewobj->feedbackcolumn = quiz_has_feedback($quiz) && $alloptions->overallfeedback;
                    }

                    $viewobj->timenow = $timenow;
                    $viewobj->numattempts = $numattempts;
                    $viewobj->mygrade = $mygrade;
                    $viewobj->moreattempts = $unfinished || !$accessmanager->is_finished($numattempts, $lastfinishedattempt);
                    $viewobj->mygradeoverridden = $mygradeoverridden;
                    $viewobj->gradebookfeedback = $gradebookfeedback;
                    $viewobj->lastfinishedattempt = $lastfinishedattempt;
                    $viewobj->canedit = has_capability('mod/quiz:manage', $context);
                    $viewobj->editurl = new moodle_url('/mod/quiz/edit.php', array('cmid' => $cm->id));
                    $viewobj->backtocourseurl = new moodle_url('/course/view.php', array('id' => $course->id));
                    $viewobj->startattempturl = $quizobj->start_attempt_url_cluster1($cm_cluster_id, $quiz_id);

                    $viewobj->popuprequired = $accessmanager->attempt_must_be_in_popup();
                    $viewobj->popupoptions = $accessmanager->get_popup_options();

                    $viewobj->infomessages = $viewobj->accessmanager->describe_rules();
                    if ($quiz->attempts != 1) {
                        $viewobj->infomessages[] = get_string('gradingmethod', 'quiz',
                            quiz_get_grading_option_name($quiz->grademethod));
                    }

                    if ($item && grade_floats_different($item->gradepass, 0)) {
                        $a = new \stdClass();
                        $a->grade = quiz_format_grade($quiz, $item->gradepass);
                        $a->maxgrade = quiz_format_grade($quiz, $quiz->grade);
                        $viewobj->infomessages[] = get_string('gradetopassoutof', 'quiz', $a);
                    }

                    $viewobj->quizhasquestions = $quizobj->has_questions();
                    $viewobj->preventmessages = array();
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
                            } else if ($canattempt) {
                                $viewobj->preventmessages = $viewobj->accessmanager->prevent_access();
                                if ($viewobj->preventmessages) {
                                    $viewobj->buttontext = '';
                                }
                            }
                        }
                    }

                    $viewobj->showbacktocourse = ($viewobj->buttontext === '' &&
                        course_get_format($course)->has_view_page());

                    if (isguestuser()) {
                        echo $output->view_page_guest($course, $quiz, $cm, $context, $viewobj->infomessages, $viewobj);
                    } else if (!isguestuser() && !($canattempt || $canpreview || $viewobj->canreviewmine)) {
                        echo $output->view_page_notenrolled($course, $quiz, $cm, $context, $viewobj->infomessages, $viewobj);
                    } else {

                        $block_content = $output->view_page_quiz1($course, $quiz, $cm, $context, $viewobj, $cm_cluster_id);

                        $bc = new block_contents();
                        $bc->attributes['id'] = 'mod_quiz_navblock quizcluster';
                        $bc->attributes['role'] = 'navigation';
                        $bc->attributes['aria-labelledby'] = 'mod_quiz_navblock_title';
                        $bc->title = \html_writer::span($quiz_name, '', array('id' => 'mod_quiz_navblock_title'));
                        $bc->content = $block_content;

                        $PAGE->blocks->add_fake_block($bc, reset($regions));
                    }

                    $quizobj = quiz::create($cm->instance, $USER->id);

                    $timenow = time();
                    $accessmanager = $quizobj->get_access_manager($timenow);
                    list($currentattemptid, $attemptnumber, $lastattempt, $messages, $page) =
                        quiz_validate_new_attempt($quizobj, $accessmanager, false, -1, true);

                } else {
                    $this_navigation_block = $this->navigation_panel_cluster1($panel, $cm_cluster_id);
                    $bc = new block_contents();
                    $bc->attributes['id'] = 'mod_quiz_navblock';
                    $bc->attributes['role'] = 'navigation';
                    $bc->attributes['aria-labelledby'] = 'mod_quiz_navblock_title';
                    $bc->title = \html_writer::span($quiz_name, '', array('id' => 'mod_quiz_navblock_title'));
                    $bc->content = $this_navigation_block;
                    $PAGE->blocks->add_fake_block($bc, reset($regions));
                }
            }
        }
    }

	public function th_get_panel_quiz_id($panel) {
		$reflector = new ReflectionObject($panel);
		$reflect_property = $reflector->getProperty('attemptobj');
		$reflect_property->setAccessible(true);
		$attemptobj = $reflect_property->getValue($panel);

		return $attemptobj->get_cmid();
	}

	// public function original_navigation_panel(quiz_nav_panel_base $panel) {
	// 	$output = '';
	// 	$userpicture = $panel->user_picture();
	// 	if ($userpicture) {
	// 		$fullname = fullname($userpicture->user);
	// 		if ($userpicture->size === true) {
	// 			$fullname = html_writer::div($fullname);
	// 		}
	// 		$output .= html_writer::tag('div', $this->render($userpicture) . $fullname,
	// 			array('id' => 'user-picture', 'class' => 'clearfix'));
	// 	}
	// 	$output .= $panel->render_before_button_bits($this);

	// 	$bcc = $panel->get_button_container_class();
	// 	$output .= html_writer::start_tag('div', array('class' => "qn_buttons clearfix $bcc"));

	// 	$rp = new ReflectionProperty('quiz_attempt_nav_panel', 'attemptobj');
	// 	$rp->setAccessible(true);
	// 	$attemptobj = $rp->getValue($panel);

	// 	foreach ($this->get_question_buttons($attemptobj, $panel) as $button) {
	// 		$output .= $this->render($button);
	// 	}
	// 	$output .= html_writer::end_tag('div');

	// 	$output .= html_writer::tag('div', $panel->render_end_bits($this),
	// 		array('class' => 'othernav'));

	// 	$this->page->requires->js_init_call('M.mod_quiz.nav.init', null, false,
	// 		quiz_get_js_module());

	// 	return $output;
	// }

	public function get_question_buttons($attemptobj, $panel_this) {
		$buttons = array();

		$reflector = new ReflectionObject($panel_this);

		$reflect_property = $reflector->getProperty('options');
		$reflect_property->setAccessible(true);
		$this_options = $reflect_property->getValue($panel_this);

		$reflect_property = $reflector->getProperty('showall');
		$reflect_property->setAccessible(true);
		$this_showall = $reflect_property->getValue($panel_this);

		$reflect_property = $reflector->getProperty('page');
		$reflect_property->setAccessible(true);
		$this_page = $reflect_property->getValue($panel_this);

		foreach ($attemptobj->get_slots() as $slot) {

			$heading = $attemptobj->get_heading_before_slot($slot);
			if (!is_null($heading)) {
				$sections = $attemptobj->get_quizobj()->get_sections();
				if (!(empty($heading) && count($sections) == 1)) {
					$buttons[] = new quiz_nav_section_heading(format_string($heading));
				}
			}

			$qa = $attemptobj->get_question_attempt($slot);

			$question = $qa->get_question();
			$question_type_name = $question->get_type_name();

			$showcorrectness = $this_options->correctness && $qa->has_marks();

			$button = new quiz_nav_question_button();
			$button->id = 'quiznavbutton' . $slot;
			$button->number = $attemptobj->get_question_number($slot);
			$button->stateclass = $qa->get_state_class($showcorrectness);
			$button->navmethod = $attemptobj->get_navigation_method();
			if (!$showcorrectness && $button->stateclass === 'notanswered') {
				$button->stateclass = 'complete';
			}
			$button->statestring = $this->get_state_string($qa, $showcorrectness);
			$button->page = $attemptobj->get_question_page($slot);
			$button->currentpage = $this_showall || $button->page == $this_page;
			$button->flagged = $qa->is_flagged();
			$button->url = $panel_this->get_question_url($slot);

			if ($attemptobj->is_blocked_by_previous_question($slot)) {
				$button->url = null;
				$button->stateclass = 'blocked';
				$button->statestring = get_string('questiondependsonprevious', 'quiz');
			}
			$buttons[] = $button;

			if ($question_type_name == "thvstepcluster") {
				foreach ($question->question_items_instance as $key => $question_item) {
					if ($question_item->get_type_name() == "description") {
						continue;
					}

					$button = new quiz_nav_question_button();
					$button->id = 'quiznavbutton' . $slot;
					$button->number = $attemptobj->get_question_number($slot) . ".$key";
					$button->stateclass = $qa->get_state_class($showcorrectness);
					$button->navmethod = $attemptobj->get_navigation_method();
					if (!$showcorrectness && $button->stateclass === 'notanswered') {
						$button->stateclass = 'complete';
					}
					$button->statestring = $this->get_state_string($qa, $showcorrectness);
					$button->page = $attemptobj->get_question_page($slot);
					$button->currentpage = $this_showall || $button->page == $this_page;
					$button->flagged = $qa->is_flagged();
					$button->url = $panel_this->get_question_url($slot);

					if ($attemptobj->is_blocked_by_previous_question($slot)) {
						$button->url = null;
						$button->stateclass = 'blocked';
						$button->statestring = get_string('questiondependsonprevious', 'quiz');
					}
					$buttons[] = $button;
				}
			}
		}
		return $buttons;
	}

	protected function get_state_string(question_attempt $qa, $showcorrectness) {
		if ($qa->get_question(false)->length > 0) {
			return $qa->get_state_string($showcorrectness);
		}

		// Special case handling for 'information' items.
		if ($qa->get_state() == question_state::$todo) {
			return get_string('notyetviewed', 'quiz');
		} else {
			return get_string('viewed', 'quiz');
		}
	}
}

class core_question1_renderer extends core_question_renderer {
    
}

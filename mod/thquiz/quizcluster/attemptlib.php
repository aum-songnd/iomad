<?php

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot.'/mod/quiz/attemptlib.php';
require_once $CFG->dirroot.'/mod/thquiz/quizcluster/question_engine.php';

global $CFG;

class quiz_cluster extends quiz {

	public function __construct($quiz, $cm, $course, $getcontext = true) {
		$this->quiz       = $quiz;
		$this->cm         = $cm;
		$this->quiz->cmid = $this->cm->id;
		$this->course     = $course;
		if ($getcontext && !empty($cm->id)) {
			$this->context = context_module::instance($cm->id);
		}
	}

	public static function create($quizid, $userid = null) {
		global $DB;

		$quiz   = quiz_access_manager::load_quiz_and_settings($quizid);
		$course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
		$cm     = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

		// Update quiz with override information.
		if ($userid) {
			$quiz = quiz_update_effective_access($quiz, $userid);
		}

		return new quiz_cluster($quiz, $cm, $course);
	}

	public function start_attempt_url($page = 0) {
		$params = array('cmid' => $this->cm->id, 'sesskey' => sesskey());
		if ($page) {
			$params['page'] = $page;
		}

		return new moodle_url('/mod/thquiz/quizcluster/startattempt.php', $params);
	}

	public function start_attempt_url_cluster($cm_cluster_id, $page = 0) {
		$params = array('cmid' => $this->cm->id, 'sesskey' => sesskey());
		if ($page) {
			$params['page'] = $page;
		}

		$params['cm_cluster_id'] = $cm_cluster_id;

		return new moodle_url('/mod/thquiz/quizcluster/startattempt.php', $params);
	}

	public function start_attempt_url_cluster1($cm_cluster_id, $quiz_old, $page = 0) {
		$params = array('cmid' => $this->cm->id, 'sesskey' => sesskey());
		if ($page) {
			$params['page'] = $page;
		}

		if ($quiz_old) {
			$params['quiz_old'] = $quiz_old;
		}

		$params['cm_cluster_id'] = $cm_cluster_id;

		return new moodle_url('/mod/thquiz/quizcluster/startattempt.php', $params);
	}

	public function attempt_url($attemptid, $page = 0) {
		global $CFG;
		$url = $CFG->wwwroot.'/mod/thquiz/quizcluster/attempt.php?attempt='.$attemptid;
		if ($page) {
			$url .= '&page='.$page;
		}
		$url .= '&cmid='.$this->get_cmid();
		return $url;
	}

	public function attempt_url_cluster($attemptid, $cm_cluster_id, $page = 0) {
		global $CFG;
		$url = $CFG->wwwroot.'/mod/thquiz/quizcluster/attempt.php?attempt='.$attemptid;
		if ($page) {
			$url .= '&page='.$page;
		}
		$url .= '&cmid='.$this->get_cmid();
		$url .= '&cm_cluster_id='.$cm_cluster_id;
		return $url;
	}

	public function view_url() {
		global $CFG;
		return $CFG->wwwroot.'/mod/thquiz/quizcluster/view.php?id='.$this->cm->id;
	}

	public function view_cluster_url($cm_cluster_id) {
		global $CFG;
		return $CFG->wwwroot.'/mod/thquiz/view_cluster.php?id='.$cm_cluster_id;
	}

	public function summary_url($attemptid) {
        return new moodle_url('/mod/thquiz/quizcluster/summary.php', array('attempt' => $attemptid, 'cmid' => $this->get_cmid()));
    }

	public function summary_url_cluster($attemptid, $cm_cluster_id) {
		return new moodle_url('/mod/thquiz/quizcluster/summary.php', array('attempt' => $attemptid, 'cmid' => $this->get_cmid(), 'cm_cluster_id' => $cm_cluster_id));
	}

	public function review_url($attemptid) {
        return new moodle_url('/mod/thquiz/quizcluster/review.php', array('attempt' => $attemptid, 'cmid' => $this->get_cmid()));
    }

	public function review_cluster_url($attemptid, $cm_cluster_id) {
        return new moodle_url('/mod/thquiz/quizcluster/review.php', array('attempt' => $attemptid, 'cmid' => $this->get_cmid(), 'cm_cluster_id' => $cm_cluster_id));
    }
}

class quiz_cluster_attempt extends quiz_attempt {

	public function __construct($attempt, $quiz, $cm, $course, $loadquestions = true) {
		$this->attempt = $attempt;
		$this->quizobj = new quiz_cluster($quiz, $cm, $course);

		if ($loadquestions) {
			$this->load_questions();
		}
	}

	/**
     * This method can be called later if the object was constructed with $loadqusetions = false.
     */
    public function load_questions() {
        global $DB;

        if (isset($this->quba)) {
            throw new coding_exception('This quiz attempt has already had the questions loaded.');
        }

        $this->quba = question_engine_cluster::load_questions_usage_by_activity($this->attempt->uniqueid);
        $this->slots = $DB->get_records('quiz_slots',
                array('quizid' => $this->get_quizid()), 'slot', 'slot, id, requireprevious');
        $this->sections = array_values($DB->get_records('quiz_sections',
                array('quizid' => $this->get_quizid()), 'firstslot'));

        $this->link_sections_and_slots();
        $this->determine_layout();
        $this->number_questions();
    }

	public function processattempt_url() {
		return new moodle_url('/mod/thquiz/quizcluster/processattempt.php');
	}

	public function summary_url() {
		return new moodle_url('/mod/thquiz/quizcluster/summary.php', array('attempt' => $this->attempt->id, 'cmid' => $this->get_cmid()));
	}

	public function summary_url_cluster($cm_cluster_id) {
		return new moodle_url('/mod/thquiz/quizcluster/summary.php', array('attempt' => $this->attempt->id, 'cmid' => $this->get_cmid(), 'cm_cluster_id' => $cm_cluster_id));
	}

	public static function create($attemptid) {
		return self::create_helper(array('id' => $attemptid));
	}

	public function get_currentpage() {
		return $this->attempt->currentpage;
	}

	public function attempt_url($slot = null, $page = -1, $thispage = -1) {
		return $this->page_and_question_url('attempt', $slot, $page, false, $thispage);
	}

	public function attempt_cluster_url($cm_cluster_id, $slot = null, $page = -1, $thispage = -1) {
		return $this->page_and_question_cluster_url('attempt', $slot, $page, false, $thispage, $cm_cluster_id);
	}

	protected function page_and_question_url($script, $slot, $page, $showall, $thispage) {

		$defaultshowall = $this->get_default_show_all($script);
		if ($showall === null && ($page == 0 || $page == -1)) {
			$showall = $defaultshowall;
		}

		// Fix up $page.
		if ($page == -1) {
			if ($slot !== null && !$showall) {
				$page = $this->get_question_page($slot);
			} else {
				$page = 0;
			}
		}

		if ($showall) {
			$page = 0;
		}

		// Add a fragment to scroll down to the question.
		$fragment = '';
		if ($slot !== null) {
			if ($slot == reset($this->pagelayout[$page])) {
				// First question on page, go to top.
				$fragment = '#';
			} else {
				$qa       = $this->get_question_attempt($slot);
				$fragment = '#'.$qa->get_outer_question_div_unique_id();
			}
		}

		// Work out the correct start to the URL.
		if ($thispage == $page) {
			return new moodle_url($fragment);
		} else {
			$url = new moodle_url('/mod/thquiz/quizcluster/'.$script.'.php'.$fragment,
				array('attempt' => $this->attempt->id, 'cmid' => $this->get_cmid()));
			if ($page == 0 && $showall != $defaultshowall) {
				$url->param('showall', (int) $showall);
			} else if ($page > 0) {
				$url->param('page', $page);
			}
			return $url;
		}
	}

	protected function page_and_question_cluster_url($script, $slot, $page, $showall, $thispage, $cm_cluster_id) {

		$defaultshowall = $this->get_default_show_all($script);

		if ($showall === null && ($page == 0 || $page == -1)) {
			$showall = $defaultshowall;
		}

		// Fix up $page.
		if ($page == -1) {
			if ($slot !== null && !$showall) {
				$page = $this->get_question_page($slot);
			} else {
				$page = 0;
			}
		}

		if ($showall) {
			$page = 0;
		}

		// Add a fragment to scroll down to the question.
		$fragment = '';
		if ($slot !== null) {
			if ($slot == reset($this->pagelayout[$page])) {
				// First question on page, go to top.
				$fragment = '#';
			} else {
				$qa       = $this->get_question_attempt($slot);
				$fragment = '#'.$qa->get_outer_question_div_unique_id();
			}
		}

		// Work out the correct start to the URL.
		if ($thispage == $page) {
			return new moodle_url($fragment);
		} else {
			$url = new moodle_url('/mod/thquiz/quizcluster/'.$script.'.php'.$fragment,
				array('attempt' => $this->attempt->id, 'cmid' => $this->get_cmid(), 'cm_cluster_id' => $cm_cluster_id));
			
			if ($page == 0 && $showall != $defaultshowall) {
				$url->param('showall', (int) $showall);
			} else if ($page > 0) {
				$url->param('page', $page);
			}

			return $url;
		}

		
	}

	public function view_url() {
		return $this->quizobj->view_url();
	}

	protected static function create_helper($conditions) {
		global $DB;

		$attempt = $DB->get_record('quiz_attempts', $conditions, '*', MUST_EXIST);
		$quiz    = quiz_access_manager::load_quiz_and_settings($attempt->quiz);
		$course  = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
		$cm      = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

		// Update quiz with override information.
		$quiz = quiz_update_effective_access($quiz, $attempt->userid);

		return new quiz_cluster_attempt($attempt, $quiz, $cm, $course);
	}

	public function get_navigation_panel_cluster(mod_thquiz_renderer $output, $cm_cluster_id, 
             $panelclass, $page, $showall = false) {
        $panel = new $panelclass($this, $this->get_display_options(true), $page, $showall);

        $bc = new block_contents();
        $bc->attributes['id'] = 'mod_quiz_navblock';
        $bc->attributes['role'] = 'navigation';
        $bc->title = get_string('quiznavigation', 'quiz');
        $bc->content = $output->navigation_panel_cluster($panel, $cm_cluster_id);
        return $bc;
    }

    public function get_navigation_panel_cluster1(mod_thquiz_renderer $output, $cm_cluster_id, 
             $panelclass, $page, $showall = false) {
        $panel = new $panelclass($this, $this->get_display_options(true), $page, $showall);

        $bc = new block_contents();
        $bc->attributes['id'] = 'mod_quiz_navblock';
        $bc->attributes['role'] = 'navigation';
        $bc->title = get_string('quiznavigation', 'quiz');
        $bc->content = $output->navigation_panel_cluster1($panel, $cm_cluster_id);
        return $bc;
    }

	public function review_cluster_url($cm_cluster_id, $slot = null, $page = -1, $showall = null, $thispage = -1) {
        return $this->page_and_question_cluster_url('review', $slot, $page, $showall, $thispage, $cm_cluster_id);
    }

	public function render_question($slot, $reviewing, mod_quiz_renderer $renderer, $thispageurl = null) {
        if ($this->is_blocked_by_previous_question($slot)) {
            $placeholderqa = $this->make_blocked_question_placeholder($slot);

            $displayoptions = $this->get_display_options($reviewing);
            $displayoptions->manualcomment = question_display_options::HIDDEN;
            $displayoptions->history = question_display_options::HIDDEN;
            $displayoptions->readonly = true;

            return html_writer::div($placeholderqa->render($displayoptions,
                    $this->get_question_number($this->get_original_slot($slot))),
                    'mod_quiz-blocked_question_warning');
        }

        return $this->render_question_helper($slot, $reviewing, $thispageurl, $renderer, null);
    }

	protected function render_question_helper($slot, $reviewing, $thispageurl,
            mod_quiz_renderer $renderer, $seq) {
        $originalslot = $this->get_original_slot($slot);
        $number = $this->get_question_number($originalslot);
        $displayoptions = $this->get_display_options_with_edit_link($reviewing, $slot, $thispageurl);

        if ($slot != $originalslot) {
            $originalmaxmark = $this->get_question_attempt($slot)->get_max_mark();
            $this->get_question_attempt($slot)->set_max_mark($this->get_question_attempt($originalslot)->get_max_mark());
        }

        if ($this->can_question_be_redone_now($slot)) {
            $displayoptions->extrainfocontent = $renderer->redo_question_button(
                    $slot, $displayoptions->readonly);
        }

        if ($displayoptions->history && $displayoptions->questionreviewlink) {
            $links = $this->links_to_other_redos($slot, $displayoptions->questionreviewlink);
            if ($links) {
                $displayoptions->extrahistorycontent = html_writer::tag('p',
                        get_string('redoesofthisquestion', 'quiz', $renderer->render($links)));
            }
        }

        if ($seq === null) {

			// print_object($this->quba);
			// exit;
            $output = $this->quba->render_question($slot, $displayoptions, $number);
        } else {
            $output = $this->quba->render_question_at_step($slot, $seq, $displayoptions, $number);
        }

        if ($slot != $originalslot) {
            $this->get_question_attempt($slot)->set_max_mark($originalmaxmark);
        }

        return $output;
    }
}

class thquiz_review_nav_panel extends quiz_nav_panel_base {
	
    public function get_question_cluster_url($cm_cluster_id, $slot) {
        return $this->attemptobj->review_cluster_url($cm_cluster_id, $slot, -1, $this->showall, $this->page);
    }

	public function get_question_url($slot) {
        return $this->attemptobj->review_url($slot, -1, $this->showall, $this->page);
    }

	public function get_question_cluster_buttons($cm_cluster_id) {
        $buttons = array();
        foreach ($this->attemptobj->get_slots() as $slot) {
            $heading = $this->attemptobj->get_heading_before_slot($slot);
            if (!is_null($heading)) {
                $sections = $this->attemptobj->get_quizobj()->get_sections();
                if (!(empty($heading) && count($sections) == 1)) {
                    $buttons[] = new quiz_nav_section_heading(format_string($heading));
                }
            }

            $qa = $this->attemptobj->get_question_attempt($slot);
            $showcorrectness = $this->options->correctness && $qa->has_marks();

            $button = new quiz_nav_question_button();
            $button->id          = 'quiznavbutton' . $slot;
            $button->number      = $this->attemptobj->get_question_number($slot);
            $button->stateclass  = $qa->get_state_class($showcorrectness);
            $button->navmethod   = $this->attemptobj->get_navigation_method();
            if (!$showcorrectness && $button->stateclass === 'notanswered') {
                $button->stateclass = 'complete';
            }
            $button->statestring = $this->get_state_string($qa, $showcorrectness);
            $button->page        = $this->attemptobj->get_question_page($slot);
            $button->currentpage = $this->showall || $button->page == $this->page;
            $button->flagged     = $qa->is_flagged();
            $button->url         = $this->get_question_cluster_url($cm_cluster_id, $slot);
            if ($this->attemptobj->is_blocked_by_previous_question($slot)) {
                $button->url = null;
                $button->stateclass = 'blocked';
                $button->statestring = get_string('questiondependsonprevious', 'quiz');
            }
            $buttons[] = $button;
        }

        return $buttons;
    }

    public function render_end_bits(mod_quiz_renderer $output) {
        $html = '';
        // if ($this->attemptobj->get_num_pages() > 1) {
        //     if ($this->showall) {
        //         $html .= html_writer::link($this->attemptobj->review_url(null, 0, false),
        //                 get_string('showeachpage', 'quiz'));
        //     } else {
        //         $html .= html_writer::link($this->attemptobj->review_url(null, 0, true),
        //                 get_string('showall', 'quiz'));
        //     }
        // }

        $html .= $output->finish_review_link($this->attemptobj);
        $html .= $this->render_restart_preview_link($output);
        return $html;
    }

    public function render_end_bits_cluster(mod_quiz_renderer $output, $cm_cluster_id) {
        $html = '';
        // TH edit to not show "Show all questions on one page" button 
        if ($this->attemptobj->get_num_pages() > 1) {
            if ($this->showall) {
                $html .= html_writer::link($this->attemptobj->review_cluster_url($cm_cluster_id, null, 0, false),
                        get_string('showeachpage', 'quiz'));
            } else {
                $html .= html_writer::link($this->attemptobj->review_cluster_url($cm_cluster_id, null, 0, true),
                        get_string('showall', 'quiz'));
            }
        }

        $html .= $output->finish_cluster_review_link($this->attemptobj, $cm_cluster_id);
        // $html .= $this->render_restart_preview_link($output);
        return $html;
    }
}

class thquiz_attempt_nav_panel extends quiz_nav_panel_base {
    public function get_question_url($slot) {
        if ($this->attemptobj->can_navigate_to($slot)) {
            return $this->attemptobj->attempt_url($slot, -1, $this->page);
        } else {
            return null;
        }
    }

    public function get_question_cluster_url($cm_cluster_id, $slot) {
        return $this->attemptobj->review_cluster_url($cm_cluster_id, $slot, -1, $this->showall, $this->page);
    }

    public function render_before_button_bits(mod_quiz_renderer $output) {
        return html_writer::tag('div', get_string('navnojswarning', 'quiz'),
                array('id' => 'quiznojswarning'));
    }

    public function render_end_bits(mod_quiz_renderer $output) {
        if ($this->page == -1) {
            // Don't link from the summary page to itself.
            return '';
        }
        return html_writer::link($this->attemptobj->summary_url(),
                get_string('endtest', 'quiz'), array('class' => 'endtestlink aalink'));
        		// . $this->render_restart_preview_link($output);
    }

    public function get_question_cluster_buttons($cm_cluster_id) {
        $buttons = array();
        foreach ($this->attemptobj->get_slots() as $slot) {
            $heading = $this->attemptobj->get_heading_before_slot($slot);
            if (!is_null($heading)) {
                $sections = $this->attemptobj->get_quizobj()->get_sections();
                if (!(empty($heading) && count($sections) == 1)) {
                    $buttons[] = new quiz_nav_section_heading(format_string($heading));
                }
            }

            $qa = $this->attemptobj->get_question_attempt($slot);
            $showcorrectness = $this->options->correctness && $qa->has_marks();

            $button = new quiz_nav_question_button();
            $button->id          = 'quiznavbutton' . $slot;
            $button->number      = $this->attemptobj->get_question_number($slot);
            $button->stateclass  = $qa->get_state_class($showcorrectness);
            $button->navmethod   = $this->attemptobj->get_navigation_method();
            if (!$showcorrectness && $button->stateclass === 'notanswered') {
                $button->stateclass = 'complete';
            }
            $button->statestring = $this->get_state_string($qa, $showcorrectness);
            $button->page        = $this->attemptobj->get_question_page($slot);
            $button->currentpage = $this->showall || $button->page == $this->page;
            $button->flagged     = $qa->is_flagged();
            $button->url         = $this->get_question_cluster_url($cm_cluster_id, $slot);
            if ($this->attemptobj->is_blocked_by_previous_question($slot)) {
                $button->url = null;
                $button->stateclass = 'blocked';
                $button->statestring = get_string('questiondependsonprevious', 'quiz');
            }
            $buttons[] = $button;
        }

        return $buttons;
    }

    public function render_end_bits_cluster(mod_quiz_renderer $output, $cm_cluster_id) {
        if ($this->page == -1) {
            // Don't link from the summary page to itself.
            return '';
        }
        return html_writer::link($this->attemptobj->summary_url(),
                get_string('endtest', 'quiz'), array('class' => 'endtestlink aalink'));
        		// . $this->render_restart_preview_link($output);
    }
}
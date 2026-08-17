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
 * Defines the thquiz module ettings form.
 *
 * @package    mod_thquiz
 * @copyright  2006 Jamie Pratt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/thquiz/locallib.php');


/**
 * Settings form for the thquiz module.
 *
 * @copyright  2006 Jamie Pratt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_thquiz_mod_form extends moodleform_mod {
    /** @var array options to be used with date_time_selector fields in the thquiz. */
    public static $datefieldoptions = array('optional' => true);

    protected $_feedbacks;
    protected static $reviewfields = array(); // Initialised in the constructor.

    /** @var int the max number of attempts allowed in any user or group override on this thquiz. */
    protected $maxattemptsanyoverride = null;

    public function __construct($current, $section, $cm, $course) {
        self::$reviewfields = array(
            'attempt'          => array('theattempt', 'thquiz'),
            'correctness'      => array('whethercorrect', 'question'),
            'marks'            => array('marks', 'thquiz'),
            'specificfeedback' => array('specificfeedback', 'question'),
            'generalfeedback'  => array('generalfeedback', 'question'),
            'rightanswer'      => array('rightanswer', 'question'),
            'overallfeedback'  => array('reviewoverallfeedback', 'thquiz'),
        );
        parent::__construct($current, $section, $cm, $course);
    }

    protected function definition() {
        global $COURSE, $CFG, $DB, $PAGE;
        $thquizconfig = get_config('thquiz');
        $mform = $this->_form;

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Name.
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Introduction.
        $this->standard_intro_elements(get_string('introduction', 'thquiz'));

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'timing', get_string('timing', 'thquiz'));

        // Open and close dates.
        $mform->addElement('date_time_selector', 'timeopen', get_string('thquizopen', 'thquiz'),
                self::$datefieldoptions);
        $mform->addHelpButton('timeopen', 'thquizopenclose', 'thquiz');

        $mform->addElement('date_time_selector', 'timeclose', get_string('thquizclose', 'thquiz'),
                self::$datefieldoptions);

        // Time limit.
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'thquiz'),
                array('optional' => true));
        $mform->addHelpButton('timelimit', 'timelimit', 'thquiz');

        // What to do with overdue attempts.
        $mform->addElement('select', 'overduehandling', get_string('overduehandling', 'thquiz'),
                thquiz_get_overdue_handling_options());
        $mform->addHelpButton('overduehandling', 'overduehandling', 'thquiz');
        // TODO Formslib does OR logic on disableif, and we need AND logic here.
        // $mform->disabledIf('overduehandling', 'timelimit', 'eq', 0);
        // $mform->disabledIf('overduehandling', 'timeclose', 'eq', 0);

        // Grace period time.
        $mform->addElement('duration', 'graceperiod', get_string('graceperiod', 'thquiz'),
                array('optional' => true));
        $mform->addHelpButton('graceperiod', 'graceperiod', 'thquiz');
        $mform->hideIf('graceperiod', 'overduehandling', 'neq', 'graceperiod');

        // -------------------------------------------------------------------------------
        // Grade settings.
        $this->standard_grading_coursemodule_elements();

        $mform->removeElement('grade');
        if (property_exists($this->current, 'grade')) {
            $currentgrade = $this->current->grade;
        } else {
            $currentgrade = $thquizconfig->maximumgrade;
        }
        $mform->addElement('hidden', 'grade', $currentgrade);
        $mform->setType('grade', PARAM_FLOAT);

        // Number of attempts.
        $attemptoptions = array('0' => get_string('unlimited'));
        for ($i = 1; $i <= THQUIZ_MAX_ATTEMPT_OPTION; $i++) {
            $attemptoptions[$i] = $i;
        }
        $mform->addElement('select', 'attempts', get_string('attemptsallowed', 'thquiz'),
                $attemptoptions);

        // Grading method.
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'thquiz'),
                thquiz_get_grading_options());
        $mform->addHelpButton('grademethod', 'grademethod', 'thquiz');
        if ($this->get_max_attempts_for_any_override() < 2) {
            $mform->hideIf('grademethod', 'attempts', 'eq', 1);
        }

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'layouthdr', get_string('layout', 'thquiz'));

        $pagegroup = array();
        $pagegroup[] = $mform->createElement('select', 'questionsperpage',
                get_string('newpage', 'thquiz'), thquiz_questions_per_page_options(), array('id' => 'id_questionsperpage'));
        $mform->setDefault('questionsperpage', $thquizconfig->questionsperpage);

        if (!empty($this->_cm) && !thquiz_has_attempts($this->_cm->instance)) {
            $pagegroup[] = $mform->createElement('checkbox', 'repaginatenow', '',
                    get_string('repaginatenow', 'thquiz'), array('id' => 'id_repaginatenow'));
        }

        $mform->addGroup($pagegroup, 'questionsperpagegrp',
                get_string('newpage', 'thquiz'), null, false);
        $mform->addHelpButton('questionsperpagegrp', 'newpage', 'thquiz');
        $mform->setAdvanced('questionsperpagegrp', $thquizconfig->questionsperpage_adv);

        // Navigation method.
        $mform->addElement('select', 'navmethod', get_string('navmethod', 'thquiz'),
                thquiz_get_navigation_options());
        $mform->addHelpButton('navmethod', 'navmethod', 'thquiz');

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'interactionhdr', get_string('questionbehaviour', 'thquiz'));

        // Shuffle within questions.
        $mform->addElement('selectyesno', 'shuffleanswers', get_string('shufflewithin', 'thquiz'));
        $mform->addHelpButton('shuffleanswers', 'shufflewithin', 'thquiz');

        // How questions behave (question behaviour).
        if (!empty($this->current->preferredbehaviour)) {
            $currentbehaviour = $this->current->preferredbehaviour;
        } else {
            $currentbehaviour = '';
        }
        $behaviours = question_engine::get_behaviour_options($currentbehaviour);
        $mform->addElement('select', 'preferredbehaviour',
                get_string('howquestionsbehave', 'question'), $behaviours);
        $mform->addHelpButton('preferredbehaviour', 'howquestionsbehave', 'question');

        // Can redo completed questions.
        $redochoices = array(0 => get_string('no'), 1 => get_string('canredoquestionsyes', 'thquiz'));
        $mform->addElement('select', 'canredoquestions', get_string('canredoquestions', 'thquiz'), $redochoices);
        $mform->addHelpButton('canredoquestions', 'canredoquestions', 'thquiz');
        foreach ($behaviours as $behaviour => $notused) {
            if (!question_engine::can_questions_finish_during_the_attempt($behaviour)) {
                $mform->hideIf('canredoquestions', 'preferredbehaviour', 'eq', $behaviour);
            }
        }

        // Each attempt builds on last.
        $mform->addElement('selectyesno', 'attemptonlast',
                get_string('eachattemptbuildsonthelast', 'thquiz'));
        $mform->addHelpButton('attemptonlast', 'eachattemptbuildsonthelast', 'thquiz');
        if ($this->get_max_attempts_for_any_override() < 2) {
            $mform->hideIf('attemptonlast', 'attempts', 'eq', 1);
        }

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'reviewoptionshdr',
                get_string('reviewoptionsheading', 'thquiz'));
        $mform->addHelpButton('reviewoptionshdr', 'reviewoptionsheading', 'thquiz');

        // Review options.
        $this->add_review_options_group($mform, $thquizconfig, 'during',
                mod_thquiz_display_options::DURING, true);
        $this->add_review_options_group($mform, $thquizconfig, 'immediately',
                mod_thquiz_display_options::IMMEDIATELY_AFTER);
        $this->add_review_options_group($mform, $thquizconfig, 'open',
                mod_thquiz_display_options::LATER_WHILE_OPEN);
        $this->add_review_options_group($mform, $thquizconfig, 'closed',
                mod_thquiz_display_options::AFTER_CLOSE);

        foreach ($behaviours as $behaviour => $notused) {
            $unusedoptions = question_engine::get_behaviour_unused_display_options($behaviour);
            foreach ($unusedoptions as $unusedoption) {
                $mform->disabledIf($unusedoption . 'during', 'preferredbehaviour',
                        'eq', $behaviour);
            }
        }
        $mform->disabledIf('attemptduring', 'preferredbehaviour',
                'neq', 'wontmatch');
        $mform->disabledIf('overallfeedbackduring', 'preferredbehaviour',
                'neq', 'wontmatch');
        foreach (self::$reviewfields as $field => $notused) {
            $mform->disabledIf($field . 'closed', 'timeclose[enabled]');
        }

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'display', get_string('appearance'));

        // Show user picture.
        $mform->addElement('select', 'showuserpicture', get_string('showuserpicture', 'thquiz'),
                thquiz_get_user_image_options());
        $mform->addHelpButton('showuserpicture', 'showuserpicture', 'thquiz');

        // Overall decimal points.
        $options = array();
        for ($i = 0; $i <= THQUIZ_MAX_DECIMAL_OPTION; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'decimalpoints', get_string('decimalplaces', 'thquiz'),
                $options);
        $mform->addHelpButton('decimalpoints', 'decimalplaces', 'thquiz');

        // Question decimal points.
        $options = array(-1 => get_string('sameasoverall', 'thquiz'));
        for ($i = 0; $i <= THQUIZ_MAX_Q_DECIMAL_OPTION; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'questiondecimalpoints',
                get_string('decimalplacesquestion', 'thquiz'), $options);
        $mform->addHelpButton('questiondecimalpoints', 'decimalplacesquestion', 'thquiz');

        // Show blocks during thquiz attempt.
        $mform->addElement('selectyesno', 'showblocks', get_string('showblocks', 'thquiz'));
        $mform->addHelpButton('showblocks', 'showblocks', 'thquiz');

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'security', get_string('extraattemptrestrictions', 'thquiz'));

        // Require password to begin thquiz attempt.
        $mform->addElement('passwordunmask', 'thquizpassword', get_string('requirepassword', 'thquiz'));
        $mform->setType('thquizpassword', PARAM_TEXT);
        $mform->addHelpButton('thquizpassword', 'requirepassword', 'thquiz');

        // IP address.
        $mform->addElement('text', 'subnet', get_string('requiresubnet', 'thquiz'));
        $mform->setType('subnet', PARAM_TEXT);
        $mform->addHelpButton('subnet', 'requiresubnet', 'thquiz');

        // Enforced time delay between thquiz attempts.
        $mform->addElement('duration', 'delay1', get_string('delay1st2nd', 'thquiz'),
                array('optional' => true));
        $mform->addHelpButton('delay1', 'delay1st2nd', 'thquiz');
        if ($this->get_max_attempts_for_any_override() < 2) {
            $mform->hideIf('delay1', 'attempts', 'eq', 1);
        }

        $mform->addElement('duration', 'delay2', get_string('delaylater', 'thquiz'),
                array('optional' => true));
        $mform->addHelpButton('delay2', 'delaylater', 'thquiz');
        if ($this->get_max_attempts_for_any_override() < 3) {
            $mform->hideIf('delay2', 'attempts', 'eq', 1);
            $mform->hideIf('delay2', 'attempts', 'eq', 2);
        }

        // Browser security choices.
        $mform->addElement('select', 'browsersecurity', get_string('browsersecurity', 'thquiz'),
                thquiz_access_manager::get_browser_security_choices());
        $mform->addHelpButton('browsersecurity', 'browsersecurity', 'thquiz');

        // Any other rule plugins.
        thquiz_access_manager::add_settings_form_fields($this, $mform);

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'overallfeedbackhdr', get_string('overallfeedback', 'thquiz'));
        $mform->addHelpButton('overallfeedbackhdr', 'overallfeedback', 'thquiz');

        if (isset($this->current->grade)) {
            $needwarning = $this->current->grade === 0;
        } else {
            $needwarning = $thquizconfig->maximumgrade == 0;
        }
        if ($needwarning) {
            $mform->addElement('static', 'nogradewarning', '',
                    get_string('nogradewarning', 'thquiz'));
        }

        $mform->addElement('static', 'gradeboundarystatic1',
                get_string('gradeboundary', 'thquiz'), '100%');

        $repeatarray = array();
        $repeatedoptions = array();
        $repeatarray[] = $mform->createElement('editor', 'feedbacktext',
                get_string('feedback', 'thquiz'), array('rows' => 3), array('maxfiles' => EDITOR_UNLIMITED_FILES,
                        'noclean' => true, 'context' => $this->context));
        $repeatarray[] = $mform->createElement('text', 'feedbackboundaries',
                get_string('gradeboundary', 'thquiz'), array('size' => 10));
        $repeatedoptions['feedbacktext']['type'] = PARAM_RAW;
        $repeatedoptions['feedbackboundaries']['type'] = PARAM_RAW;

        if (!empty($this->_instance)) {
            $this->_feedbacks = $DB->get_records('thquiz_feedback',
                    array('thquizid' => $this->_instance), 'mingrade DESC');
            $numfeedbacks = count($this->_feedbacks);
        } else {
            $this->_feedbacks = array();
            $numfeedbacks = $thquizconfig->initialnumfeedbacks;
        }
        $numfeedbacks = max($numfeedbacks, 1);

        $nextel = $this->repeat_elements($repeatarray, $numfeedbacks - 1,
                $repeatedoptions, 'boundary_repeats', 'boundary_add_fields', 3,
                get_string('addmoreoverallfeedbacks', 'thquiz'), true);

        // Put some extra elements in before the button.
        $mform->insertElementBefore($mform->createElement('editor',
                "feedbacktext[$nextel]", get_string('feedback', 'thquiz'), array('rows' => 3),
                array('maxfiles' => EDITOR_UNLIMITED_FILES, 'noclean' => true,
                      'context' => $this->context)),
                'boundary_add_fields');
        $mform->insertElementBefore($mform->createElement('static',
                'gradeboundarystatic2', get_string('gradeboundary', 'thquiz'), '0%'),
                'boundary_add_fields');

        // Add the disabledif rules. We cannot do this using the $repeatoptions parameter to
        // repeat_elements because we don't want to dissable the first feedbacktext.
        for ($i = 0; $i < $nextel; $i++) {
            $mform->disabledIf('feedbackboundaries[' . $i . ']', 'grade', 'eq', 0);
            $mform->disabledIf('feedbacktext[' . ($i + 1) . ']', 'grade', 'eq', 0);
        }

        $list_mod_quiz = $DB->get_records_sql("SELECT
                                    cm.id AS coursemoduleid,
                                    cm.module,
                                    cm.instance AS quizid,
                                    cm.course,
                                    m.name AS modulename,
                                    tq.name AS quizname
                                FROM
                                    {course_modules} cm
                                JOIN
                                    {modules} m ON cm.module = m.id
                                JOIN
                                    {quiz} tq ON cm.instance = tq.id
                                WHERE
                                    cm.course = $COURSE->id
                                AND 
                                    cm.deletioninprogress = 0
                                AND
                                    m.name = 'quiz';");

        $arr_mod_quiz = array();
        $arr_mod_quiz[0] = 'Chưa chọn';
        foreach($list_mod_quiz as $mod_quiz){
            $arr_mod_quiz[$mod_quiz->coursemoduleid] = $mod_quiz->quizname;
        }

        $mform->addElement('header', 'list_quiz_cluster', get_string('list_quiz_cluster', 'thquiz'));
        $mform->addElement('select', 'quiz_listening', get_string('quiz_listening', 'thquiz'), $arr_mod_quiz, array());
        $mform->setDefault('quiz_listening', '0');
        $mform->addElement('select', 'quiz_reading', get_string('quiz_reading', 'thquiz'), $arr_mod_quiz, array());
        $mform->setDefault('quiz_reading', '0');
        $mform->addElement('select', 'quiz_writing', get_string('quiz_writing', 'thquiz'), $arr_mod_quiz, array());
        $mform->setDefault('quiz_writing', '0');
        $mform->addElement('select', 'quiz_speaking', get_string('quiz_speaking', 'thquiz'), $arr_mod_quiz, array());
        $mform->setDefault('quiz_speaking', '0');

        // -------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();

        // Check and act on whether setting outcomes is considered an advanced setting.
        $mform->setAdvanced('modoutcomes', !empty($thquizconfig->outcomes_adv));

        // The standard_coursemodule_elements method sets this to 100, but the
        // thquiz has its own setting, so use that.
        $mform->setDefault('grade', $thquizconfig->maximumgrade);

        // -------------------------------------------------------------------------------
        $this->apply_admin_defaults();
        $this->add_action_buttons();

        $PAGE->requires->yui_module('moodle-mod_thquiz-modform', 'M.mod_thquiz.modform.init');
    }

    protected function add_review_options_group($mform, $thquizconfig, $whenname,
            $when, $withhelp = false) {
        global $OUTPUT;

        $group = array();
        foreach (self::$reviewfields as $field => $string) {
            list($identifier, $component) = $string;

            $label = get_string($identifier, $component);
            $group[] = $mform->createElement('html', html_writer::start_div('review_option_item'));
            $el = $mform->createElement('checkbox', $field . $whenname, '', $label);
            if ($withhelp) {
                $el->_helpbutton = $OUTPUT->render(new help_icon($identifier, $component));
            }
            $group[] = $el;
            $group[] = $mform->createElement('html', html_writer::end_div());
        }
        $mform->addGroup($group, $whenname . 'optionsgrp',
                get_string('review' . $whenname, 'thquiz'), null, false);

        foreach (self::$reviewfields as $field => $notused) {
            $cfgfield = 'review' . $field;
            if ($thquizconfig->$cfgfield & $when) {
                $mform->setDefault($field . $whenname, 1);
            } else {
                $mform->setDefault($field . $whenname, 0);
            }
        }

        if ($whenname != 'during') {
            $mform->disabledIf('correctness' . $whenname, 'attempt' . $whenname);
            $mform->disabledIf('specificfeedback' . $whenname, 'attempt' . $whenname);
            $mform->disabledIf('generalfeedback' . $whenname, 'attempt' . $whenname);
            $mform->disabledIf('rightanswer' . $whenname, 'attempt' . $whenname);
        }
    }

    protected function preprocessing_review_settings(&$toform, $whenname, $when) {
        foreach (self::$reviewfields as $field => $notused) {
            $fieldname = 'review' . $field;
            if (array_key_exists($fieldname, $toform)) {
                $toform[$field . $whenname] = $toform[$fieldname] & $when;
            }
        }
    }

    public function data_preprocessing(&$toform) {
        if (isset($toform['grade'])) {
            // Convert to a real number, so we don't get 0.0000.
            $toform['grade'] = $toform['grade'] + 0;
        }

        if (count($this->_feedbacks)) {
            $key = 0;
            foreach ($this->_feedbacks as $feedback) {
                $draftid = file_get_submitted_draft_itemid('feedbacktext['.$key.']');
                $toform['feedbacktext['.$key.']']['text'] = file_prepare_draft_area(
                    $draftid,               // Draftid.
                    $this->context->id,     // Context.
                    'mod_thquiz',             // Component.
                    'feedback',             // Filarea.
                    !empty($feedback->id) ? (int) $feedback->id : null, // Itemid.
                    null,
                    $feedback->feedbacktext // Text.
                );
                $toform['feedbacktext['.$key.']']['format'] = $feedback->feedbacktextformat;
                $toform['feedbacktext['.$key.']']['itemid'] = $draftid;

                if ($toform['grade'] == 0) {
                    // When a thquiz is un-graded, there can only be one lot of
                    // feedback. If the thquiz previously had a maximum grade and
                    // several lots of feedback, we must now avoid putting text
                    // into input boxes that are disabled, but which the
                    // validation will insist are blank.
                    break;
                }

                if ($feedback->mingrade > 0) {
                    $toform['feedbackboundaries['.$key.']'] =
                            round(100.0 * $feedback->mingrade / $toform['grade'], 6) . '%';
                }
                $key++;
            }
        }

        if (isset($toform['timelimit'])) {
            $toform['timelimitenable'] = $toform['timelimit'] > 0;
        }

        $this->preprocessing_review_settings($toform, 'during',
                mod_thquiz_display_options::DURING);
        $this->preprocessing_review_settings($toform, 'immediately',
                mod_thquiz_display_options::IMMEDIATELY_AFTER);
        $this->preprocessing_review_settings($toform, 'open',
                mod_thquiz_display_options::LATER_WHILE_OPEN);
        $this->preprocessing_review_settings($toform, 'closed',
                mod_thquiz_display_options::AFTER_CLOSE);
        $toform['attemptduring'] = true;
        $toform['overallfeedbackduring'] = false;

        // Password field - different in form to stop browsers that remember
        // passwords from getting confused.
        if (isset($toform['password'])) {
            $toform['thquizpassword'] = $toform['password'];
            unset($toform['password']);
        }

        // Load any settings belonging to the access rules.
        if (!empty($toform['instance'])) {
            $accesssettings = thquiz_access_manager::load_settings($toform['instance']);
            foreach ($accesssettings as $name => $value) {
                $toform[$name] = $value;
            }
        }

        if (empty($toform['completionminattempts'])) {
            $toform['completionminattempts'] = 1;
        } else {
            $toform['completionminattemptsenabled'] = $toform['completionminattempts'] > 0;
        }   
        
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            // Turn off completion settings if the checkboxes aren't ticked.
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionminattemptsenabled) || !$autocompletion) {
                $data->completionminattempts = 0;
            }
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check open and close times are consistent.
        if ($data['timeopen'] != 0 && $data['timeclose'] != 0 &&
                $data['timeclose'] < $data['timeopen']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'thquiz');
        }

        // Check that the grace period is not too short.
        if ($data['overduehandling'] == 'graceperiod') {
            $graceperiodmin = get_config('thquiz', 'graceperiodmin');
            if ($data['graceperiod'] <= $graceperiodmin) {
                $errors['graceperiod'] = get_string('graceperiodtoosmall', 'thquiz', format_time($graceperiodmin));
            }
        }

        if (!empty($data['completionminattempts'])) {
            if ($data['attempts'] > 0 && $data['completionminattempts'] > $data['attempts']) {
                $errors['completionminattemptsgroup'] = get_string('completionminattemptserror', 'thquiz');
            }
        }

        // Check the boundary value is a number or a percentage, and in range.
        $i = 0;
        while (!empty($data['feedbackboundaries'][$i] )) {
            $boundary = trim($data['feedbackboundaries'][$i]);
            if (strlen($boundary) > 0) {
                if ($boundary[strlen($boundary) - 1] == '%') {
                    $boundary = trim(substr($boundary, 0, -1));
                    if (is_numeric($boundary)) {
                        $boundary = $boundary * $data['grade'] / 100.0;
                    } else {
                        $errors["feedbackboundaries[$i]"] =
                                get_string('feedbackerrorboundaryformat', 'thquiz', $i + 1);
                    }
                } else if (!is_numeric($boundary)) {
                    $errors["feedbackboundaries[$i]"] =
                            get_string('feedbackerrorboundaryformat', 'thquiz', $i + 1);
                }
            }
            if (is_numeric($boundary) && $boundary <= 0 || $boundary >= $data['grade'] ) {
                $errors["feedbackboundaries[$i]"] =
                        get_string('feedbackerrorboundaryoutofrange', 'thquiz', $i + 1);
            }
            if (is_numeric($boundary) && $i > 0 &&
                    $boundary >= $data['feedbackboundaries'][$i - 1]) {
                $errors["feedbackboundaries[$i]"] =
                        get_string('feedbackerrororder', 'thquiz', $i + 1);
            }
            $data['feedbackboundaries'][$i] = $boundary;
            $i += 1;
        }
        $numboundaries = $i;

        // Check there is nothing in the remaining unused fields.
        if (!empty($data['feedbackboundaries'])) {
            for ($i = $numboundaries; $i < count($data['feedbackboundaries']); $i += 1) {
                if (!empty($data['feedbackboundaries'][$i] ) &&
                        trim($data['feedbackboundaries'][$i] ) != '') {
                    $errors["feedbackboundaries[$i]"] =
                            get_string('feedbackerrorjunkinboundary', 'thquiz', $i + 1);
                }
            }
        }
        for ($i = $numboundaries + 1; $i < count($data['feedbacktext']); $i += 1) {
            if (!empty($data['feedbacktext'][$i]['text']) &&
                    trim($data['feedbacktext'][$i]['text'] ) != '') {
                $errors["feedbacktext[$i]"] =
                        get_string('feedbackerrorjunkinfeedback', 'thquiz', $i + 1);
            }
        }

        // If CBM is involved, don't show the warning for grade to pass being larger than the maximum grade.
        if (($data['preferredbehaviour'] == 'deferredcbm') OR ($data['preferredbehaviour'] == 'immediatecbm')) {
            unset($errors['gradepass']);
        }
        // Any other rule plugins.
        $errors = thquiz_access_manager::validate_settings_form_fields($errors, $data, $files, $this);

        return $errors;
    }

    /**
     * Display module-specific activity completion rules.
     * Part of the API defined by moodleform_mod
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $items = array();

        $mform->addElement('advcheckbox', 'completionattemptsexhausted', null,
            get_string('completionattemptsexhausted', 'thquiz'),
            array('group' => 'cattempts'));
        $mform->disabledIf('completionattemptsexhausted', 'completionpassgrade', 'notchecked');
        $items[] = 'completionattemptsexhausted';

        $group = array();
        $group[] = $mform->createElement('checkbox', 'completionminattemptsenabled', '',
            get_string('completionminattempts', 'thquiz'));
        $group[] = $mform->createElement('text', 'completionminattempts', '', array('size' => 3));
        $mform->setType('completionminattempts', PARAM_INT);
        $mform->addGroup($group, 'completionminattemptsgroup', get_string('completionminattemptsgroup', 'thquiz'), array(' '), false);
        $mform->disabledIf('completionminattempts', 'completionminattemptsenabled', 'notchecked');

        $items[] = 'completionminattemptsgroup';

        return $items;
    }

    /**
     * Called during validation. Indicates whether a module-specific completion rule is selected.
     *
     * @param array $data Input data (not yet validated)
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data) {
        return  !empty($data['completionattemptsexhausted']) ||
                !empty($data['completionminattemptsenabled']);
    }

    /**
     * Get the maximum number of attempts that anyone might have due to a user
     * or group override. Used to decide whether disabledIf rules should be applied.
     * @return int the number of attempts allowed. For the purpose of this method,
     * unlimited is returned as 1000, not 0.
     */
    public function get_max_attempts_for_any_override() {
        global $DB;

        if (empty($this->_instance)) {
            // Thquiz not created yet, so no overrides.
            return 1;
        }

        if ($this->maxattemptsanyoverride === null) {
            $this->maxattemptsanyoverride = $DB->get_field_sql("
                    SELECT MAX(CASE WHEN attempts = 0 THEN 1000 ELSE attempts END)
                      FROM {thquiz_overrides}
                     WHERE thquiz = ?",
                    array($this->_instance));
            if ($this->maxattemptsanyoverride < 1) {
                // This happens when no override alters the number of attempts.
                $this->maxattemptsanyoverride = 1;
            }
        }

        return $this->maxattemptsanyoverride;
    }
}

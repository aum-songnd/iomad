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
 * Base class for the settings form for {@link thquiz_attempts_report}s.
 *
 * @package   mod_thquiz
 * @copyright 2012 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');


/**
 * Base class for the settings form for {@link thquiz_attempts_report}s.
 *
 * @copyright 2012 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class mod_thquiz_attempts_report_form extends moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'preferencespage',
                get_string('reportwhattoinclude', 'thquiz'));

        $this->standard_attempt_fields($mform);
        $this->other_attempt_fields($mform);

        $mform->addElement('header', 'preferencesuser',
                get_string('reportdisplayoptions', 'thquiz'));

        $this->standard_preference_fields($mform);
        $this->other_preference_fields($mform);

        $mform->addElement('submit', 'submitbutton',
                get_string('showreport', 'thquiz'));
    }

    protected function standard_attempt_fields(MoodleQuickForm $mform) {

        $mform->addElement('select', 'attempts', get_string('reportattemptsfrom', 'thquiz'), array(
                    thquiz_attempts_report::ENROLLED_WITH    => get_string('reportuserswith', 'thquiz'),
                    thquiz_attempts_report::ENROLLED_WITHOUT => get_string('reportuserswithout', 'thquiz'),
                    thquiz_attempts_report::ENROLLED_ALL     => get_string('reportuserswithorwithout', 'thquiz'),
                    thquiz_attempts_report::ALL_WITH        => get_string('reportusersall', 'thquiz'),
                 ));

        $stategroup = array(
            $mform->createElement('advcheckbox', 'stateinprogress', '',
                    get_string('stateinprogress', 'thquiz')),
            $mform->createElement('advcheckbox', 'stateoverdue', '',
                    get_string('stateoverdue', 'thquiz')),
            $mform->createElement('advcheckbox', 'statefinished', '',
                    get_string('statefinished', 'thquiz')),
            $mform->createElement('advcheckbox', 'stateabandoned', '',
                    get_string('stateabandoned', 'thquiz')),
        );
        $mform->addGroup($stategroup, 'stateoptions',
                get_string('reportattemptsthatare', 'thquiz'), array(' '), false);
        $mform->setDefault('stateinprogress', 1);
        $mform->setDefault('stateoverdue',    1);
        $mform->setDefault('statefinished',   1);
        $mform->setDefault('stateabandoned',  1);
        $mform->disabledIf('stateinprogress', 'attempts', 'eq', thquiz_attempts_report::ENROLLED_WITHOUT);
        $mform->disabledIf('stateoverdue',    'attempts', 'eq', thquiz_attempts_report::ENROLLED_WITHOUT);
        $mform->disabledIf('statefinished',   'attempts', 'eq', thquiz_attempts_report::ENROLLED_WITHOUT);
        $mform->disabledIf('stateabandoned',  'attempts', 'eq', thquiz_attempts_report::ENROLLED_WITHOUT);

        if (thquiz_report_can_filter_only_graded($this->_customdata['thquiz'])) {
            $gm = html_writer::tag('span',
                    thquiz_get_grading_option_name($this->_customdata['thquiz']->grademethod),
                    array('class' => 'highlight'));
            $mform->addElement('advcheckbox', 'onlygraded', '',
                    get_string('reportshowonlyfinished', 'thquiz', $gm));
            $mform->disabledIf('onlygraded', 'attempts', 'eq', thquiz_attempts_report::ENROLLED_WITHOUT);
            $mform->disabledIf('onlygraded', 'statefinished', 'notchecked');
        }
    }

    protected function other_attempt_fields(MoodleQuickForm $mform) {
    }

    protected function standard_preference_fields(MoodleQuickForm $mform) {
        $mform->addElement('text', 'pagesize', get_string('pagesize', 'thquiz'));
        $mform->setType('pagesize', PARAM_INT);
    }

    protected function other_preference_fields(MoodleQuickForm $mform) {
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['attempts'] != thquiz_attempts_report::ENROLLED_WITHOUT && !(
                $data['stateinprogress'] || $data['stateoverdue'] || $data['statefinished'] || $data['stateabandoned'])) {
            $errors['stateoptions'] = get_string('reportmustselectstate', 'thquiz');
        }

        return $errors;
    }
}

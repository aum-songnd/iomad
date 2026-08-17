<?php
namespace local_th_quizcluster\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class random_settings_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        // Hidden: cmid.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Hidden: quizid.
        $mform->addElement('hidden', 'quizid');
        $mform->setType('quizid', PARAM_INT);

        // Checkbox enable tag random.
        $mform->addElement('advcheckbox', 'enabletagrandom', get_string('enabletagrandom', 'local_th_quizcluster'));
        $mform->setDefault('enabletagrandom', 0);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}

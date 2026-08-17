<?php
namespace local_th_quizcluster\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class settings_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        // CMID (id của quiz trong course_modules) - bắt buộc để xử lý sau khi submit.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // ID của quiz (instance id).
        $mform->addElement('hidden', 'quizid');
        $mform->setType('quizid', PARAM_INT);

        // Checkbox cho phép chỉnh điểm từng câu bằng tay.
        $mform->addElement(
            'advcheckbox',
            'manualslotmarks',
            get_string('manualslotmarks', 'local_th_quizcluster'),
            get_string('manualslotmarks_label', 'local_th_quizcluster')
        );
        $mform->setDefault('manualslotmarks', 0);

        $this->add_action_buttons();
    }
}

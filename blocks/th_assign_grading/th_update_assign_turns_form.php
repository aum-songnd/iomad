<?php
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

class th_update_assign_turns_form extends moodleform {
    public function definition() {   
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement(
            'text',
            'assign_turns',
            get_string('assign_turns_label', 'block_th_assign_grading')
        );

        // Quan trọng: để raw để còn bắt "19dsadasd"
        $mform->setType('assign_turns', PARAM_RAW_TRIMMED);

        // Bắt buộc nhập
        $mform->addRule(
            'assign_turns',
            get_string('assign_turns_required', 'block_th_assign_grading'),
            'required',
            null,
            'client'
        );

        // (Khuyến nghị) Rule client-side bằng regex: chỉ số nguyên dương
        $mform->addRule(
            'assign_turns',
            get_string('assign_turns_positive_int', 'block_th_assign_grading'),
            'regex',
            '/^[1-9]\d*$/',
            'client'
        );

        // Nút submit mặc định
        $this->add_action_buttons(true, get_string('submit'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Server-side: chỉ chấp nhận số nguyên dương (không kí tự thừa)
        if (!preg_match('/^[1-9]\d*$/', (string)$data['assign_turns'])) {
            $errors['assign_turns'] = get_string('assign_turns_positive_int', 'block_th_assign_grading');
        }

        return $errors;
    }
}


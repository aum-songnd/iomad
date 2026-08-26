<?php

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/local/thlib/lib.php';


class th_grading_progress_form extends moodleform {
    public function definition() {
        global $CFG, $DB;

        $mform = $this->_form;
        // Lấy companyid từ customdata
        $companyid = $this->_customdata['companyid'] ?? 0;
        $mform->addElement('hidden', 'companyid');
        $mform->setType('companyid', PARAM_INT);
        $mform->setDefault('companyid', $companyid);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $users = $DB->get_records_sql("
            SELECT DISTINCT u.id, u.firstname, u.lastname, u.username, u.email,
                u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
            FROM {user} AS u
            JOIN {role_assignments} AS ra ON ra.userid = u.id
            JOIN {role} AS r ON r.id = ra.roleid
            WHERE r.shortname = :roleshortname
            AND u.deleted = 0
            AND u.suspended = 0
            ORDER BY u.firstname, u.lastname
        ", [
            'roleshortname' => get_config('block_th_assign_grading', 'role_grading'),
            'companyid' => $companyid
        ]);

        $user_arr = ["" => ""];
        foreach ($users as $userid => $user) {
            $user_arr[$userid] = fullname($user) . ', ' . $user->username . ', ' . $user->email;
        }

        $options_user = array(
            'multiple' => true,
            'noselectionstring' => 'Chọn CTV',
        );
        $mform->addElement('autocomplete', 'userid',  'Chọn CTV', $user_arr, $options_user);
        // $mform->addRule('userid', '', 'required', null, 'client', false, false);

        $from_date = get_string('from_date', 'block_th_assign_grading');
        $mform->addElement('date_selector', 'from_date', $from_date);

        $to_date = get_string('to_date', 'block_th_assign_grading');
        $mform->addElement('date_selector', 'to_date', $to_date);

        $options = array(
            0 => get_string('all', 'block_th_assign_grading'),
            1 => get_string('scored', 'block_th_assign_grading'),
            2 => get_string('not_scored_yet', 'block_th_assign_grading'),
        );

        $mform->addElement('select', 'status', get_string('status', 'block_th_assign_grading'), $options);
        $mform->setType('status', PARAM_INT);

        $this->add_action_buttons(true, get_string('submit', 'block_th_assign_grading'));
    }
}

<?php

require_once $CFG->dirroot . '/lib/formslib.php';

class approval_data_filter_form extends moodleform {

    function definition() {
        global $DB;

        $mform = $this->_form;
        $usercompany_id = $this->_customdata['companyid'] ?? 0;

        if (empty($usercompany_id)) {
            print_error('nocompany', 'block_th_assign_grading');
        }

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
            'companyid' => $usercompany_id
        ]);
        
        $user_arr = [];
        foreach ($users as $userid => $user) {
            $user_arr[$userid] = fullname($user) . ', ' . $user->username . ', ' . $user->email;
        }

        if (empty($user_arr)) {
            $user_arr[0] = get_string('noctv', 'block_th_assign_grading');
        }

        $mform->addElement('autocomplete', 'userid', 
            get_string('selectuser', 'block_th_assign_grading'), 
            $user_arr, [
                'multiple' => true,
                'noselectionstring' => get_string('noselection', 'block_th_assign_grading'),
            ]
        );

        // ========== OPTION COURSE - LỌC THEO COMPANY ==========
        // Lấy khóa học của company HOẶC được share với company
        $courses = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.fullname, c.shortname
            FROM {course} AS c
            JOIN {company_course} AS cc ON cc.courseid = c.id
            WHERE c.visible = 1
            AND c.id > 1
            AND cc.companyid = :companyid
            ORDER BY c.fullname
        ", ['companyid' => $usercompany_id]);

        $course_arr = [];
        foreach ($courses as $cid => $course) {
            $course_arr[$cid] = $course->fullname;
        }

        if (empty($course_arr)) {
            $course_arr[0] = get_string('nocourse', 'block_th_assign_grading');
        }

        $mform->addElement('autocomplete', 'coursename', 
            get_string('selectcourse', 'block_th_assign_grading'), 
            $course_arr, [
                'multiple' => true,
                'noselectionstring' => get_string('noselection', 'block_th_assign_grading'),
            ]
        );

        // Option date time
        $mform->addElement('date_selector', 'from_date', get_string('from_date', 'block_th_assign_grading'));
        $mform->addElement('date_selector', 'to_date', get_string('to_date', 'block_th_assign_grading'));

        // Option status
        $options = [
            0 => get_string('all', 'block_th_assign_grading'),
            1 => get_string('scored', 'block_th_assign_grading'),
            2 => get_string('not_scored_yet', 'block_th_assign_grading'),
        ];

        $mform->addElement('select', 'status', get_string('status', 'block_th_assign_grading'), $options);
        $mform->setType('status', PARAM_INT);


        $this->add_action_buttons(true, get_string('view', 'block_th_assign_grading'));
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['from_date']) && !empty($data['to_date']) && $data['from_date'] > $data['to_date']) {
            $errors['to_date'] = get_string('invaliddate', 'block_th_assign_grading');
        }

        return $errors;
    }
}

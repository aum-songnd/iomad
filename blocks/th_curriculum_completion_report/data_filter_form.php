<?php

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once $CFG->libdir . '/formslib.php';

class data_filter_form extends \moodleform {

    /**
     * Form definition. Abstract method - always override!
     */
    public function definition() {
        global $CFG, $DB;

        $mform = $this->_form;
        
        // Lấy companyid từ customdata (được truyền từ view.php)
        $usercompany_id = $this->_customdata['companyid'] ?? 0;

        $mform->addElement('header', 'displayinfo', get_string('filter'));

        // Select Student
         $users = $DB->get_records_sql("SELECT DISTINCT u.*
                    FROM {user} u
                    JOIN {role_assignments} ra ON u.id = ra.userid
                    JOIN {role} r ON ra.roleid = r.id
                    JOIN {company_users} cu ON u.id = cu.userid
                    WHERE r.shortname = 'student'
                    AND cu.companyid = ?
                    ORDER BY u.lastname, u.firstname;", [$usercompany_id]);

        

        $config = get_config('block_th_curriculum_completion_report');
        // Custom field name
        $th_student_cohort = $config->th_student_cohort;
        $th_student_cohort_field = 'profile_field_' . $th_student_cohort;

        $th_crm_code = $config->th_crm_code;
        $th_crm_code_field = 'profile_field_' . $th_crm_code;

        $th_student_class = $config->th_student_class;
        $th_student_class_field = 'profile_field_' . $th_student_class;

        $user_arr = ["" => ""];
        $cohort_arr = ["" => ""];
        $class_arr = ["" => ""];
        
        foreach ($users as $userid => $user) {
            profile_load_data($user);
            $user_arr[$userid] = fullname($user) . ', ' . $user->$th_crm_code_field;
            $cohort_arr[$user->$th_student_cohort_field] = $user->$th_student_cohort_field;
            $class_arr[$user->$th_student_class_field] = $user->$th_student_class_field;
        }    
        $options_user = array(
            'multiple' => true,
            'noselectionstring' => get_string('studentchoose', 'block_th_curriculum_completion_report'),
        );
        $mform->addElement('autocomplete', 'userid',  get_string('student', 'block_th_curriculum_completion_report'), $user_arr, $options_user);
        // END

        $options_cohort = array(
            'multiple' => true,
            'noselectionstring' => get_string('cohortchoose', 'block_th_curriculum_completion_report'),
        );
        $mform->addElement('autocomplete', 'student_cohort',  get_string('student_cohort', 'block_th_curriculum_completion_report'), $cohort_arr, $options_cohort);

        $options_class = array(
            'multiple' => true,
            'noselectionstring' => get_string('classchoose', 'block_th_curriculum_completion_report'),
        );
        $mform->addElement('autocomplete', 'student_class',  get_string('student_class', 'block_th_curriculum_completion_report'), $class_arr, $options_class);

        $this->add_action_buttons(true, get_string('view'));
    }
}

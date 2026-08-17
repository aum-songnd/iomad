<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use local_th_glossary_entries_count\th_glossary_entries_count;

class local_th_glossary_entries_count_external extends external_api {


  
    public static function get_course_glossary_entries_count_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }
    public static function get_course_glossary_entries_count($courseid) {

        $params = self::validate_parameters(
            self::get_course_glossary_entries_count_parameters(),
            ['courseid' => $courseid]
        );
    
        require_login($courseid);
    
        self::validate_context(\context_course::instance($courseid));
    
        return th_glossary_entries_count::get_course_glossary_entries_count(
            $params['courseid']
        );
    }
    public static function get_course_glossary_entries_count_returns() {
        return new external_single_structure([
            'totalentries' => new external_value(PARAM_INT, 'Total glossary entries'),
        ]);
    }  
}



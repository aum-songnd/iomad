<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use local_th_last_accessed_module\th_last_accessed_module;

class local_th_last_accessed_module_external extends external_api {

    public static function get_last_accessed_module_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'userid' => new external_value(PARAM_INT, 'User ID'),
        ]);
    }

    public static function get_last_accessed_module($courseid, $userid) {

        $params = self::validate_parameters(
            self::get_last_accessed_module_parameters(),
            [
                'courseid' => $courseid,
                'userid' => $userid,
            ]
        );

        return th_last_accessed_module::get_last_accessed_module(
            $params['courseid'],
            $params['userid']
        );
    }
    public static function get_last_accessed_module_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status'),
    
            'isfirstaccess' => new external_value(PARAM_BOOL, 'Is first access'),
    
            'data' => new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'modname' => new external_value(PARAM_TEXT, 'Module name'),
                'instance' => new external_value(PARAM_INT, 'Instance ID'),
                'name' => new external_value(PARAM_TEXT, 'Activity name'),
                'url' => new external_value(PARAM_TEXT, 'Activity URL'),
                'timecreated' => new external_value(PARAM_INT, 'Last access time'),
            ], 'Data', VALUE_OPTIONAL),
    
            'message' => new external_value(PARAM_TEXT, 'Message', VALUE_OPTIONAL),
        ]);
    }
    
}
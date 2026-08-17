<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use local_th_app_get_course_data\activity_service;

class local_th_app_get_course_data_external extends external_api {

    /**
     * Parameters.
     */
    public static function get_activity_id_numbers_parameters() {

        return new external_function_parameters([
            'courseid' => new external_value(
                PARAM_INT,
                'Course ID'
            ),
        ]);
    }

    /**
     * Execute.
     */
    public static function get_activity_id_numbers($courseid) {

        $params = self::validate_parameters(
            self::get_activity_id_numbers_parameters(),
            [
                'courseid' => $courseid,
            ]
        );

        return activity_service::get_activity_id_numbers(
            $params['courseid']
        );
    }

    /**
     * Returns.
     */
    public static function get_activity_id_numbers_returns() {

        return new external_single_structure([

            'status' => new external_value(
                PARAM_BOOL,
                'Status'
            ),

            'data' => new external_multiple_structure(

                new external_single_structure([

                    'cmid' => new external_value(
                        PARAM_INT,
                        'Course module ID'
                    ),

                    'courseid' => new external_value(
                        PARAM_INT,
                        'Course ID'
                    ),

                    'instance' => new external_value(
                        PARAM_INT,
                        'Instance ID'
                    ),

                    'modname' => new external_value(
                        PARAM_TEXT,
                        'Module name'
                    ),

                    'name' => new external_value(
                        PARAM_TEXT,
                        'Activity name'
                    ),

                    'idnumber' => new external_value(
                        PARAM_TEXT,
                        'ID number'
                    ),

                    'sectionnum' => new external_value(
                        PARAM_INT,
                        'Section number'
                    ),

                    'visible' => new external_value(
                        PARAM_BOOL,
                        'Visible'
                    ),

                ])
            ),

            'message' => new external_value(
                PARAM_TEXT,
                'Message',
                VALUE_OPTIONAL
            ),
        ]);
    }
}
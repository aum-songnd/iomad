<?php

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_th_app_get_course_data_get_activity_id_numbers' => [

        'classname'   => 'local_th_app_get_course_data_external',

        'methodname'  => 'get_activity_id_numbers',

        'classpath'   => 'local/th_app_get_course_data/externallib.php',

        'description' => 'Get all activity idnumbers in course',

        'type'        => 'read',

        'ajax'        => true,

        'capabilities' => '',
        
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],

    ],

];
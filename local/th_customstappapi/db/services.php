<?php

$functions = [
    'local_th_customstappapi_get_user_achievements' => [
        'classname'   => 'local_th_customstappapi_external',
        'methodname'  => 'get_user_achievements',
        'description' => 'Get user achievements: topics(section) completed/total',
        'type'        => 'read',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_th_customstappapi_get_roadmap_courses' => [
        'classname' => 'local_th_customstappapi_external',
        'methodname' => 'get_roadmap_courses',
        'description' => 'Get roadmap courses for a user with optional limit',
        'type' => 'read',
        'capabilities' => 'moodle/course:viewparticipants',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_th_customstappapi_get_assessment_courses' => [
        'classname' => 'local_th_customstappapi_external',
        'methodname' => 'get_assessment_courses',
        'description' => 'Get entrance test courses',
        'type' => 'read',
        'capabilities' => 'moodle/course:viewparticipants',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_th_customstappapi_get_roadmap_first_course' => [
        'classname' => 'local_th_customstappapi_external',
        'methodname' => 'get_roadmap_first_course',
        'description' => 'Get the first course in roadmap for a user',
        'type' => 'read',
        'capabilities' => 'moodle/course:viewparticipants',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_th_glossary_entries_count' => [  
        'classname'   => 'local_th_glossary_entries_count_external',
        'methodname'  => 'get_course_glossary_entries_count',
        'classpath'   => 'local/th_glossary_entries_count/externallib.php',
        'description' => 'Get total glossary entries in course',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];



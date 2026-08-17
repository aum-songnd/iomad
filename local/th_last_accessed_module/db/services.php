<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_th_last_accessed_module' => [
        'classname'   => 'local_th_last_accessed_module_external',
        'methodname'  => 'get_last_accessed_module',
        'classpath'   => 'local/th_last_accessed_module/externallib.php',
        'description' => 'Get last accessed module in course',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];



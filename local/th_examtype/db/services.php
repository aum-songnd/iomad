<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_th_examtype_get_quiz_by_cmid' => [
        'classname'   => 'local_th_examtype\external\get_quiz_by_cmid',
        'methodname'  => 'execute',
        'description' => 'Get quiz info and exam_type custom field by cmid',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
        'services'     => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ],
];
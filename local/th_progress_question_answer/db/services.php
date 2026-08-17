<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_th_progress_question_answer_get_attempt_summary' => [
        'classname'     => '\local_th_progress_question_answer\external\get_attempt_summary',
        'methodname'    => 'execute',
        'description'   => 'Get attempt summary with answer state and mark state for each question slot.',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'mod/quiz:attempt',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'local_th_progress_question_answer_get_attempt_review' => [
        'classname'     => '\local_th_progress_question_answer\external\get_attempt_review',
        'methodname'    => 'execute',
        'description'   => 'Get attempt review with children state for cluster questions.',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'mod/quiz:reviewmyattempts',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
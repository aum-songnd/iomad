<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    // Học viên bắt đầu làm bài thật.
    [
        'eventname'   => '\mod_quiz\event\attempt_started',
        'callback'    => '\local_th_quizcluster\observer::quiz_attempt_started',
        'includefile' => '/local/th_quizcluster/classes/observer.php',
        'internal'    => false,
        'priority'    => 9999,
    ],

    // Giáo viên / admin XEM TRƯỚC bài (preview).
    [
        'eventname'   => '\mod_quiz\event\attempt_preview_started',
        'callback'    => '\local_th_quizcluster\observer::quiz_attempt_started',
        'includefile' => '/local/th_quizcluster/classes/observer.php',
        'internal'    => false,
        'priority'    => 9999,
    ],

    // Khi nộp bài thì dùng snapshot trọng số của chính attempt để chấm.
    [
        'eventname'   => '\mod_quiz\event\attempt_submitted',
        'callback'    => '\local_th_quizcluster\observer::quiz_attempt_submitted',
        'includefile' => '/local/th_quizcluster/classes/observer.php',
        'internal'    => false,
        'priority'    => 9999,
    ],
];
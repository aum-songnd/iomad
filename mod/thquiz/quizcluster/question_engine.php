<?php

require_once __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/question/engine/lib.php';
require_once $CFG->dirroot . '/mod/thquiz/quizcluster/question_engine_data_mapper.php';
require_once $CFG->dirroot . '/mod/thquiz/quizcluster/behaviour.php';

class question_engine_cluster extends question_engine  {

    public static function load_questions_usage_by_activity($qubaid, moodle_database $db = null) {
        $dm = new question_cluster_engine_data_mapper($db);
        return $dm->load_questions_usage_by_activity($qubaid);
    }

    public static function make_behaviour($behaviour, question_attempt $qa, $preferredbehaviour) {
        try {
            self::load_behaviour_class($behaviour);
        } catch (Exception $e) {
            self::load_behaviour_class('missing');
            return new qbehaviour_missing($qa, $preferredbehaviour);
        }

        if($behaviour == 'deferredfeedback'){
            $class = 'qbehaviour1_' . $behaviour;
        } else {
            $class = 'qbehaviour_' . $behaviour;
        }

        return new $class($qa, $preferredbehaviour);
    }

}
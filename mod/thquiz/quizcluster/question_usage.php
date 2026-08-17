<?php
require_once __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/question/engine/questionusage.php';
require_once $CFG->dirroot . '/mod/thquiz/quizcluster/question_attempt.php';

class question_cluster_usage_by_activity extends question_usage_by_activity {
    public function render_question($slot, $options, $number = null) {
        $options->context = $this->context;
        return $this->get_question_attempt($slot)->render($options, $number);
    }

    public static function load_from_records($records, $qubaid) {
        $record = $records->current();
        while ($record->qubaid != $qubaid) {
            $records->next();
            if (!$records->valid()) {
                throw new coding_exception("Question usage {$qubaid} not found in the database.");
            }
            $record = $records->current();
        }

        $quba = new question_cluster_usage_by_activity($record->component,
            context::instance_by_id($record->contextid, IGNORE_MISSING));
        $quba->set_id_from_database($record->qubaid);
        $quba->set_preferred_behaviour($record->preferredbehaviour);

        $quba->observer = new question_engine_unit_of_work($quba);

        // If slot is null then the current pointer in $records will not be
        // advanced in the while loop below, and we get stuck in an infinite loop,
        // since this method is supposed to always consume at least one record.
        // Therefore, in this case, advance the record here.
        if (is_null($record->slot)) {
            $records->next();
        }

        while ($record && $record->qubaid == $qubaid && !is_null($record->slot)) {
            $quba->questionattempts[$record->slot] =
            question_cluster_attempt::load_from_records($records,
                    $record->questionattemptid, $quba->observer,
                    $quba->get_preferred_behaviour());
            if ($records->valid()) {
                $record = $records->current();
            } else {
                $record = false;
            }
        }

        return $quba;
    }

    
}
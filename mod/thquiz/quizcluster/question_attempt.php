<?php

require_once __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/question/engine/questionattempt.php';

class question_cluster_attempt extends question_attempt {

    public static function load_from_records($records, $questionattemptid,
            question_usage_observer $observer, $preferredbehaviour) {
        $record = $records->current();
        while ($record->questionattemptid != $questionattemptid) {
            $records->next();
            if (!$records->valid()) {
                throw new coding_exception("Question attempt {$questionattemptid} not found in the database.");
            }
            $record = $records->current();
        }

        try {
            $question = question_bank::load_question($record->questionid);
        } catch (Exception $e) {
            // The question must have been deleted somehow. Create a missing
            // question to use in its place.
            $question = question_bank::get_qtype('missingtype')->make_deleted_instance(
                    $record->questionid, $record->maxmark + 0);
        }

        $qa = new question_cluster_attempt($question, $record->questionusageid,
                null, $record->maxmark + 0);
        $qa->set_database_id($record->questionattemptid);
        $qa->set_slot($record->slot);
        $qa->variant = $record->variant + 0;
        $qa->minfraction = $record->minfraction + 0;
        $qa->maxfraction = $record->maxfraction + 0;
        $qa->set_flagged($record->flagged);
        $qa->questionsummary = $record->questionsummary;
        $qa->rightanswer = $record->rightanswer;
        $qa->responsesummary = $record->responsesummary;
        $qa->timemodified = $record->timemodified;

        $qa->behaviour = question_engine_cluster::make_behaviour(
                $record->behaviour, $qa, $preferredbehaviour);
        $qa->observer = $observer;

        // If attemptstepid is null (which should not happen, but has happened
        // due to corrupt data, see MDL-34251) then the current pointer in $records
        // will not be advanced in the while loop below, and we get stuck in an
        // infinite loop, since this method is supposed to always consume at
        // least one record. Therefore, in this case, advance the record here.
        if (is_null($record->attemptstepid)) {
            $records->next();
        }

        $i = 0;
        $autosavedstep = null;
        $autosavedsequencenumber = null;
        while ($record && $record->questionattemptid == $questionattemptid && !is_null($record->attemptstepid)) {
            $sequencenumber = $record->sequencenumber;
            $nextstep = question_attempt_step::load_from_records($records, $record->attemptstepid,
                    $qa->get_question(false)->get_type_name());

            if ($sequencenumber < 0) {
                if (!$autosavedstep) {
                    $autosavedstep = $nextstep;
                    $autosavedsequencenumber = -$sequencenumber;
                } else {
                    // Old redundant data. Mark it for deletion.
                    $qa->observer->notify_step_deleted($nextstep, $qa);
                }
            } else {
                $qa->steps[$i] = $nextstep;
                $i++;
            }

            if ($records->valid()) {
                $record = $records->current();
            } else {
                $record = false;
            }
        }

        if ($autosavedstep) {
            if ($autosavedsequencenumber >= $i) {
                $qa->autosavedstep = $autosavedstep;
                $qa->steps[$i] = $qa->autosavedstep;
            } else {
                $qa->observer->notify_step_deleted($autosavedstep, $qa);
            }
        }

        return $qa;
    }

    public function render($options, $number, $page = null) {
        global $CFG;
        $this->ensure_question_initialised();
        if (is_null($page)) {
            global $PAGE;
            $page = $PAGE;
        }
        $qoutput = $page->get_renderer('core', 'question');
        $qtoutput = $this->question->get_renderer($page);

        $question_text = $this->behaviour->render($options, $number, $qoutput, $qtoutput);
        $question_text = str_replace($CFG->wwwroot . '//pluginfile.php', $CFG->wwwroot . '/mod/thquiz//pluginfile.php', $question_text);

        // print_object($question_text);
        // exit;

        return $question_text;
    }

}
<?php
require_once __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/question/engine/datalib.php';
require_once $CFG->dirroot . '/mod/thquiz/quizcluster/question_usage.php';

class question_cluster_engine_data_mapper extends question_engine_data_mapper {
    public function load_questions_usage_by_activity($qubaid) {
        $records = $this->db->get_recordset_sql("
SELECT
    quba.id AS qubaid,
    quba.contextid,
    quba.component,
    quba.preferredbehaviour,
    qa.id AS questionattemptid,
    qa.questionusageid,
    qa.slot,
    qa.behaviour,
    qa.questionid,
    qa.variant,
    qa.maxmark,
    qa.minfraction,
    qa.maxfraction,
    qa.flagged,
    qa.questionsummary,
    qa.rightanswer,
    qa.responsesummary,
    qa.timemodified,
    qas.id AS attemptstepid,
    qas.sequencenumber,
    qas.state,
    qas.fraction,
    qas.timecreated,
    qas.userid,
    qasd.name,
    qasd.value

FROM      {question_usages}            quba
LEFT JOIN {question_attempts}          qa   ON qa.questionusageid    = quba.id
LEFT JOIN {question_attempt_steps}     qas  ON qas.questionattemptid = qa.id
LEFT JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid    = qas.id

WHERE
    quba.id = :qubaid

ORDER BY
    qa.slot,
    qas.sequencenumber
    ", array('qubaid' => $qubaid));

        if (!$records->valid()) {
            throw new coding_exception('Failed to load questions_usage_by_activity ' . $qubaid);
        }

        $quba = question_cluster_usage_by_activity::load_from_records($records, $qubaid);
        $records->close();

        return $quba;
    }
}
<?php

require_once '../../../config.php';
require_once $CFG->dirroot . "/question/engine/bank.php";
require_once "question_update_form.php";

global $PAGE, $OUTPUT, $COURSE;

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url('/question/type/thvstepcluster/question_update.php');
$PAGE->set_title($COURSE->shortname);
$PAGE->set_heading($COURSE->fullname);
$PAGE->set_pagelayout('standard');
$editurl = new moodle_url('/question/type/thvstepcluster/question_update.php');
$PAGE->navbar->add(get_string('pluginname', 'qtype_thvstepcluster'), $editurl);

require_login($COURSE);
require_capability('qtype/thvstepcluster:update', $context);

$mform = new question_update_form();
if ($mform->is_cancelled()) {
   
} else if ($fromform = $mform->get_data()) {

    $sql = "SELECT q.id,th.question_entries_id
			FROM {question_versions} qv
			JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
			JOIN {question} q ON q.id = qv.questionid
			JOIN {qtype_thvstepcluster} th ON th.question = qv.questionid
			WHERE qv.status = 'ready' AND q.qtype = 'thvstepcluster'";
	$records = $DB->get_records_sql($sql);

	foreach ($records as $key => $record) {

		$question_entries_id = json_decode($record->question_entries_id);
		$questionids = $question_entries_id->questionids;
		// print_object($questionids);
		if (!empty($questionids)) {
			foreach ($questionids as $question_entries_id => $question) {
				// print_object($question);
				if ($DB->record_exists('question', ['id' => $question->questionid]) && $DB->record_exists('question_bank_entries', ['id' => $question_entries_id])) {
					$data = array(
						'id' => $question_entries_id, 
						'idnumber' => $question->questionid
					);
					try {
					    $transaction = $DB->start_delegated_transaction();
					    $DB->update_record('question_bank_entries', $data);

					     // Assuming the both inserts work, we get to the following line.
					    $transaction->allow_commit();
	                	question_bank::notify_question_edited($question->questionid);

					} catch(Exception $e) {
					     $transaction->rollback($e);
					}
					
	                // Purge this question from the cache.
				} else {
					continue;
				}
			}
		}
	}
}


echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'qtype_thvstepcluster'));
echo $mform->display();
echo $OUTPUT->footer();

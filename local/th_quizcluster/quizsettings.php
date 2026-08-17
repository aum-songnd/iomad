<?php
require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT); // Course module id.

$cm = get_coursemodule_from_id('quiz', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quiz:manage', $context);

$PAGE->set_url('/local/th_quizcluster/quizsettings.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('clustergradesettings', 'local_th_quizcluster'));
$PAGE->set_heading($course->fullname);

// $formclass = '\local_th_quizcluster\form\settings_form';
// $mform = new $formclass();

// $record = $DB->get_record('local_th_quizcluster_cfg', ['quizid' => $quiz->id]);

// $toform = new stdClass();
// $toform->quizid = $quiz->id;
// $toform->manualslotmarks = $record ? (int)$record->manualslotmarks : 0;
// $mform->set_data($toform);

$formclass = '\local_th_quizcluster\form\settings_form';
$mform = new $formclass();

$record = $DB->get_record('local_th_quizcluster_cfg', ['quizid' => $quiz->id]);

$toform = new stdClass();

// Gán lại cmid cho hidden field 'id' để POST lên lần sau.
$toform->id = $cm->id;

$toform->quizid = $quiz->id;
$toform->manualslotmarks = $record ? (int)$record->manualslotmarks : 0;
$mform->set_data($toform);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/quiz/edit.php', ['cmid' => $cm->id]));
} else if ($data = $mform->get_data()) {
    $now = time();

    if ($record) {
        $record->manualslotmarks = empty($data->manualslotmarks) ? 0 : 1;
        $record->timemodified = $now;
        $DB->update_record('local_th_quizcluster_cfg', $record);
    } else {
        $record = (object)[
            'quizid'          => $quiz->id,
            'manualslotmarks' => empty($data->manualslotmarks) ? 0 : 1,
            'timecreated'     => $now,
            'timemodified'    => $now,
        ];
        $DB->insert_record('local_th_quizcluster_cfg', $record);
    }

    redirect(
        new moodle_url('/mod/quiz/edit.php', ['cmid' => $cm->id]),
        get_string('settingssaved', 'local_th_quizcluster')
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('clustergradesettings', 'local_th_quizcluster'));
$mform->display();
echo $OUTPUT->footer();

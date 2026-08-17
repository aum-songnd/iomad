<?php

require_once $CFG->dirroot . '/mod/quiz/locallib.php';
require_once $CFG->dirroot . '/mod/thquiz/quizcluster/attemptlib.php';
require_once($CFG->dirroot.'/mod/thquiz/locallib.php');

function quiz_cluster_create_attempt_handling_errors($attemptid, $cmid = null) {
	try {
		$attempobj = quiz_cluster_attempt::create($attemptid);

	} catch (moodle_exception $e) {
		if (!empty($cmid)) {
			list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');
			$continuelink      = new moodle_url('/mod/quiz/view.php', array('id' => $cmid));
			$context           = context_module::instance($cm->id);
			if (has_capability('mod/quiz:preview', $context)) {
				throw new moodle_exception('attempterrorcontentchange', 'quiz', $continuelink);
			} else {
				throw new moodle_exception('attempterrorcontentchangeforuser', 'quiz', $continuelink);
			}
		} else {
			throw new moodle_exception('attempterrorinvalid', 'quiz');
		}
	}
	if (!empty($cmid) && $attempobj->get_cmid() != $cmid) {
		throw new moodle_exception('invalidcoursemodule');
	} else {
		return $attempobj;
	}
}

function get_accessmanager_cluster($id){
	global $DB, $USER;
	$cm = get_coursemodule_from_id('thquiz', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('thquiz', array('id' => $cm->instance), '*', MUST_EXIST);

    $modulecontext = context_module::instance($cm->id);
	$timenow = time();
	$thquizobj = thquiz::create($cm->instance, $USER->id);
	$accessmanager = new thquiz_access_manager($thquizobj, $timenow,
        has_capability('mod/thquiz:ignoretimelimits', $modulecontext, null, false));

	return $accessmanager;
}

?>
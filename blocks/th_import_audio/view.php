<?php

require_once '../../config.php';
require_once $CFG->libdir . '/adminlib.php';
require_once 'th_import_audio_form.php';
require_once $CFG->dirroot . '/lib/filelib.php';
require_once $CFG->dirroot . '/lib/questionlib.php';
require_once "lib.php";

global $DB, $OUTPUT, $PAGE, $COURSE, $USER, $CFG;

// Check for all required variables.
$courseid = $COURSE->id;
$th_import_audio_key = optional_param('key', 0, PARAM_ALPHANUMEXT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
	print_error('invalidcourse', 'block_th_import_audio', $courseid);
}

if(!has_capability('block/th_import_audio:view', context_course::instance($courseid))){
	$cap = 'block/th_import_audio:view';
	$courses = get_user_capability_course($cap, $USER->id, true, 'id, fullname, shortname', 'sortorder', 0, 0, true);
	
	// Nếu chỉ cần kiểm tra “có/không”:
	if (empty($courses)) {
		print_error('nopermissions', 'error', '', $cap);
	} else {
		$first = reset($courses);
		$courseid = $first->id;
		require_login();
		require_capability('block/th_import_audio:view', context_course::instance($courseid));
	}
} else {
	require_login($courseid);
	require_capability('block/th_import_audio:view', context_course::instance($courseid));
}

$pageurl = '/blocks/th_import_audio/view.php';
$title = get_string('title', 'block_th_import_audio');
$PAGE->set_url('/blocks/th_import_audio/view.php');
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('th_import_audio', 'block_th_import_audio'));
$PAGE->set_title($SITE->fullname . ': ' . get_string('title', 'block_th_import_audio'));

$editurl = new moodle_url('/blocks/th_import_audio/view.php');
$settingsnode = $PAGE->navbar->add(get_string('breadcrumb', 'block_th_import_audio'), $editurl);
$settingsnode->make_active();

$context = context_system::instance();
$companyid = 0;
if (class_exists('iomad')) {
	// Set the companyid
	$companyid = iomad::get_my_companyid($context);
	if (empty($companyid)) {
		print_error('nocompany', 'block_th_import_audio');
	}
}

if (empty($th_import_audio_key)) {

	$th_import_audio_form = new th_import_audio_form(null, ['companyid' => $companyid]);

	if ($th_import_audio_form->is_cancelled()) {
		// Cancelled forms redirect to the course main page.
		$courseurl = new moodle_url('/my');
		redirect($courseurl);

	} else if ($fromform = $th_import_audio_form->get_data()) {

		$course_id = $fromform->course_id;
		$realfilename = $th_import_audio_form->get_new_filename('newfile');
		$importfile = make_request_directory() . "/{$realfilename}";

		if (!$result = $th_import_audio_form->save_file('newfile', $importfile, true)) {
			throw new moodle_exception('uploadproblem');
		}

		//save log

		$context = context_user::instance($USER->id);
		$log_import_zip = new stdClass();
		$log_import_zip->contextid = $context->id;
		$log_import_zip->option = 0;
		$log_import_zip->itemid = $fromform->newfile;
		$log_import_zip->filename = $realfilename;
		$log_import_zip->questionname = null;
		$log_import_zip->courseid = $course_id;
		$log_import_zip->timecreated = time();
		$DB->insert_record('th_log_import_audio', $log_import_zip);

		$zip = new ZipArchive();
		if ($zip->open($importfile) !== true) {
			throw new \moodle_exception('cannotusezip', 'error');
		}
		$fs = get_file_storage();

		$stt = 0;
		$check_import = new stdClass();
		$check_import->error_messages = array();
		$check_import->import_audio = array();
		$check_import->valid_import_found = 0;

		// Sử dụng thư mục tạm của Moodle
		$path = $CFG->tempdir . '/th_import_audio';

		// Tạo thư mục nếu chưa có
		if (!file_exists($path)) {
			mkdir($path, $CFG->directorypermissions, true);
		}

		$version = substr($CFG->release, 0, 1);
		if ($version == 3) {
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$stat = $zip->statIndex($i);
				if ($stat === false) {
					$zip->close();
					throw new \moodle_exception('errorunzippingfiles', 'error');
				}
				$stt = $stt + 1;

				$zefilename = $stat['name'];
				$zefilesize = $stat['size'];
				$filedata = $zip->getFromIndex($i);
				if ($filedata === false) {
					$zip->close();
					throw new \moodle_exception('errorunzippingfiles', 'error');
				}
				$audioname = basename($zefilename);

				$tempdir = $CFG->tempdir . '/th_import_audio/user_' . $USER->id;
				// Tạo thư mục nếu chưa có
				if (!file_exists($tempdir)) {
					mkdir($tempdir, $CFG->directorypermissions, true);
				}
				// Đường dẫn file audio
				$file_path = $tempdir . '/' . $audioname;
				// Ghi dữ liệu vào file
				file_put_contents($file_path, $filedata);

				$filename = substr($audioname, 0, -4);

				$pos = strpos($audioname, '+');
				if ($pos !== false) {
					$filename_arr = explode('+', $filename);

					foreach ($filename_arr as $filename) {
						$filename = trim($filename);
						$list_question = $DB->get_record_sql("SELECT q.*, c.id as contextid 
															FROM {question} as q
															JOIN {question_categories} as qc ON q.category = qc.id
															JOIN {context} as c ON qc.contextid = c.id
															JOIN {company_course} as cc ON c.instanceid = cc.courseid
															WHERE q.name = '$filename' 
																AND c.contextlevel = '50' 
																AND c.instanceid = '$course_id'
																AND cc.companyid = $companyid
															LIMIT 1");

						if (!empty($list_question)) {

							$formatoptions = new stdClass();
							$formatoptions->noclean = true;
							$formatoptions->para = false;

							$text = question_rewrite_question_preview_urls($list_question->questiontext, $list_question->id,
							$list_question->contextid, 'question', 'questiontext', $list_question->id,
							$list_question->contextid, 'core_question');
							$text = format_text($text, $list_question->questiontextformat,
							$formatoptions);
							if ($text == '') {
								$text = '&#160;';
							}

							$list_question->questiontext1 = $text;

							$check_import->valid_import_found += 1;
							$data = new stdClass();
							$data->audioname = basename($zefilename);
							$data->filedata = $file_path;
							$data->list_question = $list_question;
							$data->filename = $filename;
							$data->courseid = $course_id;
							$check_import->import_audio[] = $data;

						} else {
							$sql = "SELECT fullname FROM {course} WHERE id = $course_id";
							$fullname_course = $DB->get_field_sql($sql);
							$link_course = new moodle_url('/course/view.php', ['id' => $course_id]);
							$link = html_writer::link($link_course, $fullname_course);
							$check_import->error_messages[] = get_string('questionnotfound', 'block_th_import_audio', [
								'filename' => $filename,
								'link' => $link
							]);

						}
					}

				} else {
					$list_question = $DB->get_record_sql("SELECT q.*, c.id as contextid FROM {question} as q, {context} as c, {question_categories} as qc WHERE q.name = '$filename' AND q.category= qc.id
							AND qc.contextid = c.id AND c.contextlevel = '50' AND c.instanceid = '$course_id'");

					if (!empty($list_question)) {
						$formatoptions = new stdClass();
						$formatoptions->noclean = true;
						$formatoptions->para = false;

						$text = question_rewrite_question_preview_urls($list_question->questiontext, $list_question->id,
						$list_question->contextid, 'question', 'questiontext', $list_question->id,
						$list_question->contextid, 'core_question');
						$text = format_text($text, $list_question->questiontextformat,
						$formatoptions);
						if ($text == '') {
							$text = '&#160;';
						}

						$list_question->questiontext1 = $text;
						$check_import->valid_import_found += 1;
						$data = new stdClass();
						$data->audioname = $audioname;
						$data->filedata = $file_path;
						$data->list_question = $list_question;
						$data->filename = $filename;
						$data->courseid = $course_id;
						$check_import->import_audio[] = $data;

					} else {
						$sql = "SELECT fullname FROM {course} WHERE id = $course_id";
						$fullname_course = $DB->get_field_sql($sql);
						$link_course = new moodle_url('/course/view.php', ['id' => $course_id]);
						$link = html_writer::link($link_course, $fullname_course);
						$check_import->error_messages[] = get_string('questionnotfound', 'block_th_import_audio', [
							'filename' => $filename,
							'link' => $link
						]);

					}
				}	
			}

			$zip->close();

			$th_import_audio_key = $courseid . '_' . time();
			$SESSION->block_th_import_audio[$th_import_audio_key] = $check_import;
		} else if ($version == 4) {
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$stat = $zip->statIndex($i);
				if ($stat === false) {
					$zip->close();
					throw new \moodle_exception('errorunzippingfiles', 'error');
				}
				$stt = $stt + 1;
	
				$zefilename = $stat['name'];
				$zefilesize = $stat['size'];
				$filedata = $zip->getFromIndex($i);
				if ($filedata === false) {
					$zip->close();
					throw new \moodle_exception('errorunzippingfiles', 'error');
				}
				$audioname = basename($zefilename);

				$tempdir = $CFG->tempdir . '/th_import_audio/user_' . $USER->id;
				// Tạo thư mục nếu chưa có
				if (!file_exists($tempdir)) {
					mkdir($tempdir, $CFG->directorypermissions, true);
				}
				// Đường dẫn file audio
				$file_path = $tempdir . '/' . $audioname;
				// Ghi dữ liệu vào file
				file_put_contents($file_path, $filedata);
	
				$filename = substr($audioname, 0, -4);
	
				$pos = strpos($audioname, '+');
				if ($pos !== false) {
					$filename_arr = explode('+', $filename);
	
					foreach($filename_arr as $filename){
						$filename = trim($filename);
						$sql = "SELECT DISTINCT q.*, c.id as contextid
								FROM {question_versions} qv
								JOIN {question} q ON q.id = qv.questionid
								JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
								JOIN {question_categories} as qct ON qbe.questioncategoryid = qct.id
            					JOIN {context} as c ON qct.contextid = c.id
								WHERE q.name = '$filename'
								AND qbe.questioncategoryid 
								IN 
								(SELECT DISTINCT qc.id
								FROM {question_categories} qc
								JOIN {question_bank_entries} qb ON qb.questioncategoryid = qc.id
								JOIN {context} ctx ON ctx.id = qc.contextid
								WHERE ctx.instanceid = '$course_id' AND ctx.contextlevel = 50)
								AND (qv.questionbankentryid, qv.version) IN (
	  								SELECT questionbankentryid, MAX(version) AS version 
								  FROM {question_versions}
								  WHERE status = 'ready'
								  GROUP BY questionbankentryid
								)";
						$list_question = $DB->get_record_sql($sql);
	
						if (!empty($list_question)) {
							$formatoptions = new stdClass();
							$formatoptions->noclean = true;
							$formatoptions->para = false;
		
							$text = question_rewrite_question_preview_urls($list_question->questiontext, $list_question->id,
							$list_question->contextid, 'question', 'questiontext', $list_question->id,
							$list_question->contextid, 'core_question');
							$text = format_text($text, $list_question->questiontextformat,
							$formatoptions);
							if ($text == '') {
								$text = '&#160;';
							}
		
							$list_question->questiontext1 = $text;

							$check_import->valid_import_found += 1;
							$data = new stdClass();
							$data->audioname = basename($zefilename);
							$data->filedata = $file_path;
							$data->list_question = $list_question;
							$data->filename = $filename;
							$data->courseid = $course_id;
							$check_import->import_audio[] = $data;
	
						} else {
							$sql = "SELECT fullname FROM {course} WHERE id = $course_id";
							$fullname_course = $DB->get_field_sql($sql);
							$link_course = new moodle_url('/course/view.php', ['id' => $course_id]);
							$link = html_writer::link($link_course, $fullname_course);
							$check_import->error_messages[] = get_string('question_not_found', 'block_th_import_audio', ['filename' => $filename, 'link' => $link]);
						}
					}
	
				} else {
					$filename = trim($filename);
					$sql = "SELECT DISTINCT q.*, c.id as contextid
								FROM {question_versions} qv
								JOIN {question} q ON q.id = qv.questionid
								JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
								JOIN {question_categories} as qct ON qbe.questioncategoryid = qct.id
            					JOIN {context} as c ON qct.contextid = c.id
								WHERE q.name = '$filename'
								AND qbe.questioncategoryid 
								IN 
								(SELECT DISTINCT qc.id
								FROM {question_categories} qc
								JOIN {question_bank_entries} qb ON qb.questioncategoryid = qc.id
								JOIN {context} ctx ON ctx.id = qc.contextid
								WHERE ctx.instanceid = '$course_id' AND ctx.contextlevel = 50)
								AND (qv.questionbankentryid, qv.version) IN (
	  								SELECT questionbankentryid, MAX(version) AS version 
								  FROM {question_versions}
								  WHERE status = 'ready'
								  GROUP BY questionbankentryid
								)";
					$list_question = $DB->get_record_sql($sql);
	
					if (!empty($list_question)) {
						$formatoptions = new stdClass();
						$formatoptions->noclean = true;
						$formatoptions->para = false;
		
						$text = question_rewrite_question_preview_urls($list_question->questiontext, $list_question->id,
						$list_question->contextid, 'question', 'questiontext', $list_question->id,
						$list_question->contextid, 'core_question');
						$text = format_text($text, $list_question->questiontextformat,
						$formatoptions);
						if ($text == '') {
							$text = '&#160;';
						}
		
						$list_question->questiontext1 = $text;

						$check_import->valid_import_found += 1;
						$data = new stdClass();
						$data->audioname = $audioname;
						$data->filedata = $file_path;
						$data->list_question = $list_question;
						$data->filename = $filename;
						$data->courseid = $course_id;
						$check_import->import_audio[] = $data;
	
					} else {
						$sql = "SELECT fullname FROM {course} WHERE id = $course_id";
						$fullname_course = $DB->get_field_sql($sql);
						$link_course = new moodle_url('/course/view.php', ['id' => $course_id]);
						$link = html_writer::link($link_course, $fullname_course);
						$check_import->error_messages[] = get_string('question_not_found', 'block_th_import_audio', ['filename' => $filename, 'link' => $link]);
					}
				}	
			}
	
			$zip->close();
	
			$th_import_audio_key = $courseid . '_' . time();
			$SESSION->block_th_import_audio[$th_import_audio_key] = $check_import;
		}
	} else {
		echo $OUTPUT->header();
		echo $OUTPUT->heading('<center>' . get_string('title', 'block_th_import_audio') . '</center>');
		$th_import_audio_form->display();
		echo $OUTPUT->footer();
	}
}

if ($th_import_audio_key) {
	$form2 = new confirm_form(null, array('th_import_audio_key' => $th_import_audio_key));

	if ($form2->is_cancelled()) {

		$files = glob("$CFG->tempdir/th_import_audio/user_$USER->id/*"); // get all file names

		foreach ($files as $file) {
			// iterate files
			if (is_file($file)) {
				unlink($file); // delete file
			}
		}
		// Cancelled forms redirect to the course main page.
		$courseurl = new moodle_url('/blocks/th_import_audio/view.php');
		redirect($courseurl);
	} else if ($formdata = $form2->get_data()) {

		if (
			!empty($th_import_audio_key) && !empty($SESSION->block_th_import_audio) &&
			array_key_exists($th_import_audio_key, $SESSION->block_th_import_audio)
		) {

			$import_audio_data = $SESSION->block_th_import_audio[$th_import_audio_key];
			$import_audio = $import_audio_data->import_audio;

			$version = substr($CFG->release, 0, 1);
			if ($version == 3) {
				foreach ($import_audio as $import) {

					$audioname = $import->audioname;
					$filedata = $import->filedata;
					$filename = $import->filename;
					$list_question = $import->list_question;
					$question_id = $list_question->id;

					$usercontext = context_user::instance($USER->id);
					$contextid = $usercontext->id;
					$component = 'user';
					$filearea = 'draft';

					// save
					$question = $DB->get_record('question', array('id' => $list_question->id));
					get_question_options($question, true, [$COURSE]);
					$category = $DB->get_record('question_categories', array('id' => $question->category));

					$toform = fullclone($question);
					$toform->category = "{$category->id},{$category->contextid}";
					$toform->scrollpos = 0;
					$toform->categorymoveto = $toform->category;
					$toform->appendqnumstring = null;
					$toform->returnurl = null;
					$toform->makecopy = 0;
					$toform->courseid = $import->courseid;
					$toform->inpopup = 0;
					$thiscontext = context_course::instance($import->courseid);
					$contexts = new question_edit_contexts($thiscontext);
					$categorycontext = context::instance_by_id($category->contextid);
					$addpermission = has_capability('moodle/question:add', $categorycontext);
					$question->formoptions = new stdClass();
					$question->formoptions->canedit = question_has_capability_on($question, 'edit');
					$question->formoptions->canmove = $addpermission && question_has_capability_on($question, 'move');
					$question->formoptions->cansaveasnew = $addpermission &&
						(question_has_capability_on($question, 'view') || $question->formoptions->canedit);
					$question->formoptions->repeatelements = $question->formoptions->canedit || $question->formoptions->cansaveasnew;
					$formeditable = $question->formoptions->canedit || $question->formoptions->cansaveasnew || $question->formoptions->canmove;
					$qtypeobj = question_bank::get_qtype($question->qtype);

					$mform = $qtypeobj->create_editing_form('question.php', $question, $category, $contexts, $formeditable);
					$mform->set_data($toform);

					$fs = get_file_storage();

					if(isset($toform->questiontext['itemid'])){
						$draftitemid = $toform->questiontext['itemid'];
					} else {
						$fileoptions = array(
							'subdirs' => 1,
							'maxfiles' => -1,
							'maxbytes' => -1
						);
						$draftid = file_get_submitted_draft_itemid('questiontext');
						$questiontext = $question->questiontext;
						$questiontext = file_prepare_draft_area($draftid, $thiscontext->id,
								'question', 'questiontext', empty($question->id) ? null : (int) $question->id,
								$fileoptions, $questiontext);

						$toform->questiontext = array();
						$toform->questiontext['text'] = $questiontext;
						$toform->questiontext['format'] = empty($question->questiontextformat) ?
								editors_get_preferred_format() : $question->questiontextformat;
						$toform->questiontext['itemid'] = $draftid;
						$draftitemid = $toform->questiontext['itemid'];
					}
					
					$filerecord = array(
						'contextid' => $contextid,
						'component' => 'user',
						'filearea' => 'draft',
						'itemid' => $draftitemid,
						'filepath' => '/',
						'filename' => $audioname,
					);

					$file_audio_content = file_get_contents($filedata);

					if(draftfile_exists($draftitemid, '/', $audioname)){
						$unused_filename = $fs->get_unused_filename($contextid, 'user', 'draft', $draftitemid, '/', $audioname);
						$filerecord['filename'] = $unused_filename;
						$stored_file = $fs->create_file_from_string($filerecord, $file_audio_content);
					} else {
						$fs->create_file_from_string($filerecord, $file_audio_content);
					}

					$contextid1 = context_course::instance($import->courseid)->id;

					//save log
					$log_import_audio = new stdClass();
					$log_import_audio->contextid = $contextid1;
					$log_import_audio->option = 1;
					$log_import_audio->itemid = $list_question->id;
					$log_import_audio->filename = $filerecord['filename'];
					$log_import_audio->questionname = $question->name;
					$log_import_audio->courseid = $import->courseid;
					$log_import_audio->timecreated = time();
					$DB->insert_record('th_log_import_audio', $log_import_audio);

					$questiontext = "<audio controls='true'><source src='@@PLUGINFILE@@/$audioname'>@@PLUGINFILE@@/$audioname</audio>";
					$questiontext1 = file_rewrite_pluginfile_urls($questiontext, 'draftfile.php',
						context_user::instance($USER->id)->id, 'user', 'draft', $draftitemid);

					$pos = strpos($list_question->questiontext, "[audio]");

					if ($pos !== false) {
						$question_text = str_replace("[audio]", '</br>' . $questiontext . '</br>', $list_question->questiontext);
						$toform->questiontext['text'] = $question_text;
						$toform->questiontext['itemid'] = $draftitemid;
					} else {
						$question_text = $list_question->questiontext . $questiontext;
						$toform->questiontext['text'] = file_rewrite_pluginfile_urls($question_text, 'draftfile.php',
						context_user::instance($USER->id)->id, 'user', 'draft', $toform->questiontext['itemid']);
					}

					$question = $qtypeobj->save_question($question, $toform);

					if (isset($toform->tags)) {
						// If we have any question context level tags then set those tags now.
						core_tag_tag::set_item_tags('core_question', 'question', $question->id,
							context::instance_by_id($contextid), $toform->tags, 0);
					}

					if (isset($toform->coursetags)) {
						// If we have and course context level tags then set those now.
						core_tag_tag::set_item_tags('core_question', 'question', $question->id,
							context_course::instance($toform->courseid), $toform->coursetags, 0);
					}

					// Purge this question from the cache.
					question_bank::notify_question_edited($question->id);
				}
			} else if ($version == 4) {
				foreach ($import_audio as $import) {

					$audioname = $import->audioname;
					$filedata = $import->filedata;
					$filename = $import->filename;
					$list_question = $import->list_question;
					$question_id = $list_question->id;

					$usercontext = context_user::instance($USER->id);
					$contextid = $usercontext->id;
					$component = 'user';
					$filearea = 'draft';

					// save
					$question = $DB->get_record('question', array('id' => $list_question->id));
					get_question_options($question, true, [$COURSE]);
					$sql = "SELECT qc.*
						from {question_bank_entries} qb
						JOIN {question_categories} qc ON qc.id = qb.questioncategoryid
						JOIN {question_versions} qv ON qv.questionbankentryid = qb.id
						where qv.questionid = $list_question->id";
					$category = $DB->get_record_sql($sql);

					$toform = fullclone($question);
					$toform->category = "{$category->id},{$category->contextid}";
					$toform->scrollpos = 0;
					$toform->categorymoveto = $toform->category;
					$toform->appendqnumstring = null;
					$toform->returnurl = null;
					$toform->makecopy = 0;
					$toform->courseid = $import->courseid;
					$toform->inpopup = 0;
					$thiscontext = context_course::instance($import->courseid);
					$contexts = new core_question\local\bank\question_edit_contexts($thiscontext);
					$categorycontext = context::instance_by_id($category->contextid);
					$addpermission = has_capability('moodle/question:add', $categorycontext);
					$question->formoptions = new stdClass();
					$question->formoptions->canedit = question_has_capability_on($question, 'edit');
					$question->formoptions->canmove = $addpermission && question_has_capability_on($question, 'move');
					$question->formoptions->cansaveasnew = $addpermission &&
						(question_has_capability_on($question, 'view') || $question->formoptions->canedit);
					$question->formoptions->repeatelements = $question->formoptions->canedit || $question->formoptions->cansaveasnew;
					$question->beingcopied = false;
					$formeditable = $question->formoptions->canedit || $question->formoptions->cansaveasnew || $question->formoptions->canmove;
					$qtypeobj = question_bank::get_qtype($question->qtype);

					$mform = $qtypeobj->create_editing_form('question.php', $question, $category, $contexts, $formeditable);
					$mform->set_data($toform);

					$fs = get_file_storage();

					if(isset($toform->questiontext['itemid'])){
						$draftitemid = $toform->questiontext['itemid'];
					} else {
						$fileoptions = array(
							'subdirs' => 1,
							'maxfiles' => -1,
							'maxbytes' => -1
						);
						$draftid = file_get_submitted_draft_itemid('questiontext');
						$questiontext = $question->questiontext;
						$questiontext = file_prepare_draft_area($draftid, $thiscontext->id,
								'question', 'questiontext', empty($question->id) ? null : (int) $question->id,
								$fileoptions, $questiontext);

						$toform->questiontext = array();
						$toform->questiontext['text'] = $questiontext;
						$toform->questiontext['format'] = empty($question->questiontextformat) ?
								editors_get_preferred_format() : $question->questiontextformat;
						$toform->questiontext['itemid'] = $draftid;
						$draftitemid = $toform->questiontext['itemid'];
					}
					
					$filerecord = array(
						'contextid' => $contextid,
						'component' => 'user',
						'filearea' => 'draft',
						'itemid' => $draftitemid,
						'filepath' => '/',
						'filename' => $audioname,
					);

					$file_audio_content = file_get_contents($filedata);

					if(draftfile_exists($draftitemid, '/', $audioname)){
						$unused_filename = $fs->get_unused_filename($contextid, 'user', 'draft', $draftitemid, '/', $audioname);
						$filerecord['filename'] = $unused_filename;
						$stored_file = $fs->create_file_from_string($filerecord, $file_audio_content);
					} else {
						$fs->create_file_from_string($filerecord, $file_audio_content);
					}

					$contextid1 = context_course::instance($import->courseid)->id;

					//save log
					$log_import_audio = new stdClass();
					$log_import_audio->contextid = $contextid1;
					$log_import_audio->option = 1;
					$log_import_audio->itemid = $list_question->id;
					$log_import_audio->filename = $filerecord['filename'];
					$log_import_audio->questionname = $question->name;
					$log_import_audio->courseid = $import->courseid;
					$log_import_audio->timecreated = time();
					$DB->insert_record('th_log_import_audio', $log_import_audio);

					$questiontext = "<audio controls='true'><source src='@@PLUGINFILE@@/$audioname'>@@PLUGINFILE@@/$audioname</audio>";
					$questiontext1 = file_rewrite_pluginfile_urls($questiontext, 'draftfile.php',
						context_user::instance($USER->id)->id, 'user', 'draft', $draftitemid);

					$pos = strpos($list_question->questiontext, "[audio]");

					if ($pos !== false) {
						$question_text = str_replace("[audio]", '</br>' . $questiontext . '</br>', $list_question->questiontext);
						$toform->questiontext['text'] = $question_text;
						$toform->questiontext['itemid'] = $draftitemid;
					} else {
						$question_text = $list_question->questiontext . $questiontext;
						$toform->questiontext['text'] = file_rewrite_pluginfile_urls($question_text, 'draftfile.php',
						context_user::instance($USER->id)->id, 'user', 'draft', $toform->questiontext['itemid']);
					}

					$question = $qtypeobj->save_question($question, $toform);

					if (isset($toform->tags)) {
						// If we have any question context level tags then set those tags now.
						core_tag_tag::set_item_tags('core_question', 'question', $question->id,
							context::instance_by_id($contextid), $toform->tags, 0);
					}

					if (isset($toform->coursetags)) {
						// If we have and course context level tags then set those now.
						core_tag_tag::set_item_tags('core_question', 'question', $question->id,
							context_course::instance($toform->courseid), $toform->coursetags, 0);
					}

					// Purge this question from the cache.
					question_bank::notify_question_edited($question->id);
				}
			}

			$files = glob("$CFG->tempdir/th_import_audio/user_$USER->id/*"); // get all file names

			foreach ($files as $file) {
				// iterate files
				if (is_file($file)) {
					unlink($file); // delete file
				}
			}

			redirect($CFG->wwwroot . "/blocks/th_import_audio/view.php", get_string('add_audio_success', 'block_th_import_audio'), null, \core\output\notification::NOTIFY_SUCCESS);
		} else {
			redirect($CFG->wwwroot . "/blocks/th_import_audio/view.php", get_string('add_audio_fail', 'block_th_import_audio'), null, \core\output\notification::NOTIFY_ERROR);
		}

	} else {
		echo $OUTPUT->header();
		echo $OUTPUT->heading('<center>' . get_string('title', 'block_th_import_audio') . '</center>');

		if (
			!empty($th_import_audio_key) && !empty($SESSION->block_th_import_audio) &&
			array_key_exists($th_import_audio_key, $SESSION->block_th_import_audio)
		) {

			$import_audio_data = $SESSION->block_th_import_audio[$th_import_audio_key];

			if (!empty($import_audio_data->error_messages)) {
				$errors = $import_audio_data->error_messages;

				$table = new html_table();
				$table->head = array(get_string('stt', 'block_th_import_audio'), get_string('suggestion', 'block_th_import_audio'));
				$stt = 0;

				foreach ($errors as $k => $error) {
					$stt = $stt + 1;
					$row = new html_table_row();
					$cell = new html_table_cell($stt);
					$row->cells[] = $cell;
					$cell = new html_table_cell($error);
					$row->cells[] = $cell;
					$table->data[] = $row;
				}

				$html = html_writer::table($table);
				echo $OUTPUT->heading("Gợi ý");
				echo $html;
			}

			if (!empty($import_audio_data->import_audio)) {

				$import_audio = $import_audio_data->import_audio;
				$html1 = th_display_table_import_audio($import_audio);
				echo $OUTPUT->heading(get_string('questions_with_audio', 'block_th_import_audio'), 3, 'text-center');
				echo $html1;
			}
		}

		if (empty($import_audio_data->valid_import_found)) {
			$a = new stdClass();
			$url = new moodle_url('/blocks/th_import_audio/view.php');
			$a->url = $url->out();
			$wn = get_string('error_no_valid_time_in_list', 'block_th_import_audio', $a);

			$notification = new \core\output\notification(
				$wn,
				\core\output\notification::NOTIFY_WARNING
			);
			$notification->set_show_closebutton(false);
			echo $OUTPUT->render($notification);
		} else {
			echo $form2->display();
		}

		echo $OUTPUT->footer();
	}
}

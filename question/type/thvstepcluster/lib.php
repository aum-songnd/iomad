<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/engine/bank.php');

function qtype_thvstepcluster_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {

    if ($args[0] == 'from_question') {
        array_shift($args);
        global $DB, $CFG;
        if ($filearea === 'export') {
            list($context, $course, $cm) = get_context_info_array($context->id);
            require_login($course, false, $cm);

            require_once($CFG->dirroot . '/question/editlib.php');
            $contexts = new question_edit_contexts($context);
            // check export capability
            $contexts->require_one_edit_tab_cap('export');
            $category_id = (int)array_shift($args);
            $format      = array_shift($args);
            $cattofile   = array_shift($args);
            $contexttofile = array_shift($args);
            $filename    = array_shift($args);

            // load parent class for import/export
            require_once($CFG->dirroot . '/question/format.php');
            require_once($CFG->dirroot . '/question/editlib.php');
            require_once($CFG->dirroot . '/question/format/' . $format . '/format.php');

            $classname = 'qformat_' . $format;
            if (!class_exists($classname)) {
                send_file_not_found();
            }

            $qformat = new $classname();

            if (!$category = $DB->get_record('question_categories', array('id' => $category_id))) {
                send_file_not_found();
            }

            $qformat->setCategory($category);
            $qformat->setContexts($contexts->having_one_edit_tab_cap('export'));
            $qformat->setCourse($course);

            if ($cattofile == 'withcategories') {
                $qformat->setCattofile(true);
            } else {
                $qformat->setCattofile(false);
            }

            if ($contexttofile == 'withcontexts') {
                $qformat->setContexttofile(true);
            } else {
                $qformat->setContexttofile(false);
            }

            if (!$qformat->exportpreprocess()) {
                send_file_not_found();
                print_error('exporterror', 'question', $thispageurl->out());
            }

            // export data to moodle file pool
            if (!$content = $qformat->exportprocess()) {
                send_file_not_found();
            }

            send_file($content, $filename, 0, 0, true, true, $qformat->mime_type());
        }
        // Normal case, a file belonging to a question.
        $qubaidorpreview = array_shift($args);

        // Two sub-cases: 1. A question being previewed outside an attempt/usage.
        if ($qubaidorpreview === 'preview') {
            $previewcontextid = (int)array_shift($args);
            $previewcomponent = array_shift($args);
            $questionid = (int) array_shift($args);
            $previewcontext = context_helper::instance_by_id($previewcontextid);

            $result = component_callback($previewcomponent, 'question_preview_pluginfile', array(
                    $previewcontext, $questionid,
                    $context, $component, $filearea, $args,
                    $forcedownload, $options), 'callbackmissing');

            if ($result === 'callbackmissing') {
                throw new coding_exception("Component {$previewcomponent} does not define the callback " .
                        "{$previewcomponent}_question_preview_pluginfile callback. " .
                        "Which is required if you are using question_rewrite_question_preview_urls.", DEBUG_DEVELOPER);
            }

            send_file_not_found();
        }

        // 2. A question being attempted in the normal way.
        $qubaid = (int)$qubaidorpreview;
        $slot = (int)array_shift($args);

        $module = $DB->get_field('question_usages', 'component',
                array('id' => $qubaid));

        if (!$module) {
            send_file_not_found();
        }

        if ($module === 'core_question_preview') {
            require_once($CFG->dirroot . '/question/previewlib.php');
            return question_preview_question_pluginfile($course, $context,
                    'question', $filearea, $qubaid, $slot, $args, $forcedownload, $options);

        } else {
            $dir = core_component::get_component_directory($module);
            if (!file_exists("$dir/lib.php")) {
                send_file_not_found();
            }
            include_once("$dir/lib.php");

            $filefunction = $module . '_question_pluginfile';
            if (function_exists($filefunction)) {
                $filefunction($course, $context, $component, $filearea, $qubaid, $slot,
                    $args, $forcedownload, $options);
            }

            // Okay, we're here so lets check for function without 'mod_'. quiz_question_pluginfile
            if (strpos($module, 'mod_') === 0) {
                $filefunctionold  = substr($module, 4) . '_question_pluginfile';
                if (function_exists($filefunctionold)) {
                    if($module == 'mod_quiz'){                           
                        include_once($CFG->dirroot ."/mod/thquiz/lib.php");
                        $filefunctionold = 'thquiz_question_pluginfile';              
                    }
                    $filefunctionold($course, $context, 'question', $filearea, $qubaid, $slot,
                        $args, $forcedownload, $options);
                }
            }

            send_file_not_found();
        }
    } else {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        question_pluginfile($course, $context, 'qtype_thvstepcluster', $filearea, $args, $forcedownload, $options);
    }
}

function qtype_thvstepcluster_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('qtype/thvstepcluster:create_tags', $context)) {
        $url = new moodle_url('/question/type/thvstepcluster/create_tags.php', array('id' => $course->id));
        $navigation->add(get_string('create_tags', 'qtype_thvstepcluster'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/settings', ''));
    }
    if (has_capability('qtype/thvstepcluster:delete_tags', $context)) {
        $url = new moodle_url('/question/type/thvstepcluster/delete_tags.php', array('id' => $course->id));
        $navigation->add(get_string('delete_tags', 'qtype_thvstepcluster'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/settings', ''));
    }
}

function qtype_thvstepcluster_create_tags($id) {

    global $DB, $COURSE;

    $sql = "SELECT q.id,q.name,c.id as courseid,c.fullname,c.shortname, th.question_entries_id
        FROM {question_versions} qv
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question} q ON q.id = qv.questionid
        JOIN {qtype_thvstepcluster} th ON th.question = qv.questionid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        JOIN {context} ctx ON ctx.id = qc.contextid and ctx.contextlevel = 50
        JOIN {course} c ON c.id = ctx.instanceid and ctx.contextlevel = 50 AND c.id = $id
        WHERE qv.status = 'ready' AND q.qtype = 'thvstepcluster' order by q.id";

    $records = $DB->get_records_sql($sql);

    $sql1 = "SELECT q.id
        FROM {question_versions} qv
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question} q ON q.id = qv.questionid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        JOIN {context} ctx ON ctx.id = qc.contextid and ctx.contextlevel = 50
        JOIN {course} c ON c.id = ctx.instanceid and ctx.contextlevel = 50
        WHERE q.id = :questionid and c.id = :courseid";
    $contextid = $DB->get_field('context', 'id', array('contextlevel' => CONTEXT_COURSE, 'instanceid' => $COURSE->id));

    foreach ($records as $key => $record) {
        $question_entries_id = json_decode($record->question_entries_id);
        $questionids = (array) $question_entries_id->questionids;
        $question_entry_items = (array) $question_entries_id->question_entry_items;

        foreach ($question_entry_items as $item) { 
            if ($DB->record_exists_sql($sql1, array('questionid' => $questionids[$item]->questionid, 'courseid' => $id))) {
                core_tag_tag::add_item_tag('core_question', 'question', $questionids[$item]->questionid,
                    context::instance_by_id($contextid), 'id_' . $questionids[$item]->questionid, 0);
            }
        }
    }
    return true;
}

function qtype_thvstepcluster_delete_tags($id) {

    global $DB, $COURSE;

    $contextid = $DB->get_field('context', 'id', array('contextlevel' => CONTEXT_COURSE, 'instanceid' => $COURSE->id));
    $sql = "SELECT ti.tagid
             FROM {tag} t
             JOIN {tag_instance} ti ON ti.tagid = t.id
             WHERE ti.contextid = :contextid AND ti.component = :component 
             AND ti.itemtype = :itemtype AND t.name LIKE 'id\_%'
             GROUP BY ti.tagid";
    $params = array(
     'contextid' => $contextid,
     'component' => 'core_question',
     'itemtype' => 'question'
    );
    $tags = $DB->get_records_sql($sql, $params); 

    if ($tags) {
        $tagid = array_keys($tags);
        core_tag_tag::delete_tags($tagid);

    }

    $sql = "SELECT q.id,q.name,c.id as courseid,c.fullname,c.shortname, th.question_entries_id
        FROM {question_versions} qv
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        JOIN {question} q ON q.id = qv.questionid
        JOIN {qtype_thvstepcluster} th ON th.question = qv.questionid
        JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
        JOIN {context} ctx ON ctx.id = qc.contextid and ctx.contextlevel = 50
        JOIN {course} c ON c.id = ctx.instanceid and ctx.contextlevel = 50 AND c.id = $id
        WHERE qv.status = 'ready' AND q.qtype = 'thvstepcluster' order by q.id";

    $records = $DB->get_records_sql($sql);

    foreach ($records as $key => $record) {
        question_bank::notify_question_edited($record->id);
    }

    return true;
}
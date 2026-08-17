<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/lib/questionlib.php');

function question_cluster_pluginfile($course, $context, $component, $filearea, $args, $forcedownload, $options = []) {
    global $DB, $CFG;

    // Special case, sending a question bank export.
    if ($filearea === 'export') {
        list($context, $course, $cm) = get_context_info_array($context->id);
        require_login($course, false, $cm);

        require_once($CFG->dirroot . '/question/editlib.php');
        $contexts = new core_question\local\bank\question_edit_contexts($context);
        // Check export capability.
        $contexts->require_one_edit_tab_cap('export');
        $categoryid = (int)array_shift($args);
        $format      = array_shift($args);
        $cattofile   = array_shift($args);
        $contexttofile = array_shift($args);
        $filename    = array_shift($args);

        // Load parent class for import/export.
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/editlib.php');
        require_once($CFG->dirroot . '/question/format/' . $format . '/format.php');

        $classname = 'qformat_' . $format;
        if (!class_exists($classname)) {
            send_file_not_found();
        }

        $qformat = new $classname();

        if (!$category = $DB->get_record('question_categories', array('id' => $categoryid))) {
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
            throw new moodle_exception('exporterror', 'question', $thispageurl->out());
        }

        // Export data to moodle file pool.
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
        return qbank_previewquestion\helper::question_preview_question_pluginfile($course, $context,
                $component, $filearea, $qubaid, $slot, $args, $forcedownload, $options);

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

        // Okay, we're here so lets check for function without 'mod_'.
        if (strpos($module, 'mod_') === 0) {
            $filefunctionold  = substr($module, 4) . '_question_pluginfile';
            if (function_exists($filefunctionold)) {
                if($module == 'mod_quiz'){
                    include_once($CFG->dirroot ."/mod/thquiz/lib.php");
                    $filefunctionold = 'thquiz_question_pluginfile';
                }
                $filefunctionold($course, $context, $component, $filearea, $qubaid, $slot,
                    $args, $forcedownload, $options);
            }
        }

        send_file_not_found();
    }
}
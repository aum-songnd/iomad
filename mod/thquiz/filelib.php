<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/lib/filelib.php');

function file_cluster_pluginfile($relativepath, $forcedownload, $preview = null, $offline = false, $embed = false) {
    global $DB, $CFG, $USER;
    // relative path must start with '/'
    if (!$relativepath) {
        throw new \moodle_exception('invalidargorconf');
    } else if ($relativepath[0] != '/') {
        throw new \moodle_exception('pathdoesnotstartslash');
    }

    // extract relative path components
    $args = explode('/', ltrim($relativepath, '/'));

    if (count($args) < 3) { // always at least context, component and filearea
        throw new \moodle_exception('invalidarguments');
    }

    $contextid = (int)array_shift($args);
    $component = clean_param(array_shift($args), PARAM_COMPONENT);
    $filearea  = clean_param(array_shift($args), PARAM_AREA);

    list($context, $course, $cm) = get_context_info_array($contextid);

    $fs = get_file_storage();

    $sendfileoptions = ['preview' => $preview, 'offline' => $offline, 'embed' => $embed];

    // ========================================================================================================================
    if ($component === 'blog') {
        // Blog file serving
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            send_file_not_found();
        }
        if ($filearea !== 'attachment' and $filearea !== 'post') {
            send_file_not_found();
        }

        if (empty($CFG->enableblogs)) {
            throw new \moodle_exception('siteblogdisable', 'blog');
        }

        $entryid = (int)array_shift($args);
        if (!$entry = $DB->get_record('post', array('module'=>'blog', 'id'=>$entryid))) {
            send_file_not_found();
        }
        if ($CFG->bloglevel < BLOG_GLOBAL_LEVEL) {
            require_login();
            if (isguestuser()) {
                throw new \moodle_exception('noguest');
            }
            if ($CFG->bloglevel == BLOG_USER_LEVEL) {
                if ($USER->id != $entry->userid) {
                    send_file_not_found();
                }
            }
        }

        if ($entry->publishstate === 'public') {
            if ($CFG->forcelogin) {
                require_login();
            }

        } else if ($entry->publishstate === 'site') {
            require_login();
            //ok
        } else if ($entry->publishstate === 'draft') {
            require_login();
            if ($USER->id != $entry->userid) {
                send_file_not_found();
            }
        }

        $filename = array_pop($args);
        $filepath = $args ? '/'.implode('/', $args).'/' : '/';

        if (!$file = $fs->get_file($context->id, $component, $filearea, $entryid, $filepath, $filename) or $file->is_directory()) {
            send_file_not_found();
        }

        send_stored_file($file, 10*60, 0, true, $sendfileoptions); // download MUST be forced - security!

    // ========================================================================================================================
    } else if ($component === 'grade') {

        require_once($CFG->libdir . '/grade/constants.php');

        if (($filearea === 'outcome' or $filearea === 'scale') and $context->contextlevel == CONTEXT_SYSTEM) {
            // Global gradebook files
            if ($CFG->forcelogin) {
                require_login();
            }

            $fullpath = "/$context->id/$component/$filearea/".implode('/', $args);

            if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else if ($filearea == GRADE_FEEDBACK_FILEAREA || $filearea == GRADE_HISTORY_FEEDBACK_FILEAREA) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                send_file_not_found();
            }

            require_login($course, false);

            $gradeid = (int) array_shift($args);
            $filename = array_pop($args);
            if ($filearea == GRADE_HISTORY_FEEDBACK_FILEAREA) {
                $grade = $DB->get_record('grade_grades_history', ['id' => $gradeid]);
            } else {
                $grade = $DB->get_record('grade_grades', ['id' => $gradeid]);
            }

            if (!$grade) {
                send_file_not_found();
            }

            $iscurrentuser = $USER->id == $grade->userid;

            if (!$iscurrentuser) {
                $coursecontext = context_course::instance($course->id);
                if (!has_capability('moodle/grade:viewall', $coursecontext)) {
                    send_file_not_found();
                }
            }

            $fullpath = "/$context->id/$component/$filearea/$gradeid/$filename";

            if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);
        } else {
            send_file_not_found();
        }

    // ========================================================================================================================
    } else if ($component === 'tag') {
        if ($filearea === 'description' and $context->contextlevel == CONTEXT_SYSTEM) {

            // All tag descriptions are going to be public but we still need to respect forcelogin
            if ($CFG->forcelogin) {
                require_login();
            }

            $fullpath = "/$context->id/tag/description/".implode('/', $args);

            if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, true, $sendfileoptions);

        } else {
            send_file_not_found();
        }
    // ========================================================================================================================
    } else if ($component === 'badges') {
        require_once($CFG->libdir . '/badgeslib.php');

        $badgeid = (int)array_shift($args);
        $badge = new badge($badgeid);
        $filename = array_pop($args);

        if ($filearea === 'badgeimage') {
            if ($filename !== 'f1' && $filename !== 'f2' && $filename !== 'f3') {
                send_file_not_found();
            }
            if (!$file = $fs->get_file($context->id, 'badges', 'badgeimage', $badge->id, '/', $filename.'.png')) {
                send_file_not_found();
            }

            \core\session\manager::write_close();
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);
        } else if ($filearea === 'userbadge'  and $context->contextlevel == CONTEXT_USER) {
            if (!$file = $fs->get_file($context->id, 'badges', 'userbadge', $badge->id, '/', $filename.'.png')) {
                send_file_not_found();
            }

            \core\session\manager::write_close();
            send_stored_file($file, 60*60, 0, true, $sendfileoptions);
        }
    // ========================================================================================================================
    } else if ($component === 'calendar') {
        if ($filearea === 'event_description'  and $context->contextlevel == CONTEXT_SYSTEM) {

            // All events here are public the one requirement is that we respect forcelogin
            if ($CFG->forcelogin) {
                require_login();
            }

            // Get the event if from the args array
            $eventid = array_shift($args);

            // Load the event from the database
            if (!$event = $DB->get_record('event', array('id'=>(int)$eventid, 'eventtype'=>'site'))) {
                send_file_not_found();
            }

            // Get the file and serve if successful
            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, $component, $filearea, $eventid, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else if ($filearea === 'event_description' and $context->contextlevel == CONTEXT_USER) {

            // Must be logged in, if they are not then they obviously can't be this user
            require_login();

            // Don't want guests here, potentially saves a DB call
            if (isguestuser()) {
                send_file_not_found();
            }

            // Get the event if from the args array
            $eventid = array_shift($args);

            // Load the event from the database - user id must match
            if (!$event = $DB->get_record('event', array('id'=>(int)$eventid, 'userid'=>$USER->id, 'eventtype'=>'user'))) {
                send_file_not_found();
            }

            // Get the file and serve if successful
            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, $component, $filearea, $eventid, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 0, 0, true, $sendfileoptions);

        } else if ($filearea === 'event_description' and $context->contextlevel == CONTEXT_COURSECAT) {
            if ($CFG->forcelogin) {
                require_login();
            }

            // Get category, this will also validate access.
            $category = core_course_category::get($context->instanceid);

            // Get the event ID from the args array, load event.
            $eventid = array_shift($args);
            $event = $DB->get_record('event', [
                'id' => (int) $eventid,
                'eventtype' => 'category',
                'categoryid' => $category->id,
            ]);

            if (!$event) {
                send_file_not_found();
            }

            // Retrieve file from storage, and serve.
            $filename = array_pop($args);
            $filepath = $args ? '/' . implode('/', $args) .'/' : '/';
            $file = $fs->get_file($context->id, $component, $filearea, $eventid, $filepath, $filename);
            if (!$file || $file->is_directory()) {
                send_file_not_found();
            }

            // Unlock session during file serving.
            \core\session\manager::write_close();
            send_stored_file($file, HOURSECS, 0, $forcedownload, $sendfileoptions);
        } else if ($filearea === 'event_description' and $context->contextlevel == CONTEXT_COURSE) {

            // Respect forcelogin and require login unless this is the site.... it probably
            // should NEVER be the site
            if ($CFG->forcelogin || $course->id != SITEID) {
                require_login($course);
            }

            // Must be able to at least view the course. This does not apply to the front page.
            if ($course->id != SITEID && (!is_enrolled($context)) && (!is_viewing($context))) {
                //TODO: hmm, do we really want to block guests here?
                send_file_not_found();
            }

            // Get the event id
            $eventid = array_shift($args);

            // Load the event from the database we need to check whether it is
            // a) valid course event
            // b) a group event
            // Group events use the course context (there is no group context)
            if (!$event = $DB->get_record('event', array('id'=>(int)$eventid, 'courseid'=>$course->id))) {
                send_file_not_found();
            }

            // If its a group event require either membership of view all groups capability
            if ($event->eventtype === 'group') {
                if (!has_capability('moodle/site:accessallgroups', $context) && !groups_is_member($event->groupid, $USER->id)) {
                    send_file_not_found();
                }
            } else if ($event->eventtype === 'course' || $event->eventtype === 'site') {
                // Ok. Please note that the event type 'site' still uses a course context.
            } else {
                // Some other type.
                send_file_not_found();
            }

            // If we get this far we can serve the file
            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, $component, $filearea, $eventid, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else {
            send_file_not_found();
        }

    // ========================================================================================================================
    } else if ($component === 'user') {
        if ($filearea === 'icon' and $context->contextlevel == CONTEXT_USER) {
            if (count($args) == 1) {
                $themename = theme_config::DEFAULT_THEME;
                $filename = array_shift($args);
            } else {
                $themename = array_shift($args);
                $filename = array_shift($args);
            }

            // fix file name automatically
            if ($filename !== 'f1' and $filename !== 'f2' and $filename !== 'f3') {
                $filename = 'f1';
            }

            if ((!empty($CFG->forcelogin) and !isloggedin()) ||
                    (!empty($CFG->forceloginforprofileimage) && (!isloggedin() || isguestuser()))) {
                // protect images if login required and not logged in;
                // also if login is required for profile images and is not logged in or guest
                // do not use require_login() because it is expensive and not suitable here anyway
                $theme = theme_config::load($themename);
                redirect($theme->image_url('u/'.$filename, 'moodle')); // intentionally not cached
            }

            if (!$file = $fs->get_file($context->id, 'user', 'icon', 0, '/', $filename.'.png')) {
                if (!$file = $fs->get_file($context->id, 'user', 'icon', 0, '/', $filename.'.jpg')) {
                    if ($filename === 'f3') {
                        // f3 512x512px was introduced in 2.3, there might be only the smaller version.
                        if (!$file = $fs->get_file($context->id, 'user', 'icon', 0, '/', 'f1.png')) {
                            $file = $fs->get_file($context->id, 'user', 'icon', 0, '/', 'f1.jpg');
                        }
                    }
                }
            }
            if (!$file) {
                // bad reference - try to prevent future retries as hard as possible!
                if ($user = $DB->get_record('user', array('id'=>$context->instanceid), 'id, picture')) {
                    if ($user->picture > 0) {
                        $DB->set_field('user', 'picture', 0, array('id'=>$user->id));
                    }
                }
                // no redirect here because it is not cached
                $theme = theme_config::load($themename);
                $imagefile = $theme->resolve_image_location('u/'.$filename, 'moodle', null);
                send_file($imagefile, basename($imagefile), 60*60*24*14);
            }

            $options = $sendfileoptions;
            if (empty($CFG->forcelogin) && empty($CFG->forceloginforprofileimage)) {
                // Profile images should be cache-able by both browsers and proxies according
                // to $CFG->forcelogin and $CFG->forceloginforprofileimage.
                $options['cacheability'] = 'public';
            }
            send_stored_file($file, 60*60*24*365, 0, false, $options); // enable long caching, there are many images on each page

        } else if ($filearea === 'private' and $context->contextlevel == CONTEXT_USER) {
            require_login();

            if (isguestuser()) {
                send_file_not_found();
            }

            if ($USER->id !== $context->instanceid) {
                send_file_not_found();
            }

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, $component, $filearea, 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 0, 0, true, $sendfileoptions); // must force download - security!

        } else if ($filearea === 'profile' and $context->contextlevel == CONTEXT_USER) {

            if ($CFG->forcelogin) {
                require_login();
            }

            $userid = $context->instanceid;

            if (!empty($CFG->forceloginforprofiles)) {
                require_once("{$CFG->dirroot}/user/lib.php");

                require_login();

                // Verify the current user is able to view the profile of the supplied user anywhere.
                $user = core_user::get_user($userid);
                if (!user_can_view_profile($user, null, $context)) {
                    send_file_not_found();
                }
            }

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, $component, $filearea, 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 0, 0, true, $sendfileoptions); // must force download - security!

        } else if ($filearea === 'profile' and $context->contextlevel == CONTEXT_COURSE) {
            $userid = (int)array_shift($args);
            $usercontext = context_user::instance($userid);

            if ($CFG->forcelogin) {
                require_login();
            }

            if (!empty($CFG->forceloginforprofiles)) {
                require_once("{$CFG->dirroot}/user/lib.php");

                require_login();

                // Verify the current user is able to view the profile of the supplied user in current course.
                $user = core_user::get_user($userid);
                if (!user_can_view_profile($user, $course, $usercontext)) {
                    send_file_not_found();
                }
            }

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($usercontext->id, 'user', 'profile', 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 0, 0, true, $sendfileoptions); // must force download - security!

        } else if ($filearea === 'backup' and $context->contextlevel == CONTEXT_USER) {
            require_login();

            if (isguestuser()) {
                send_file_not_found();
            }
            $userid = $context->instanceid;

            if ($USER->id != $userid) {
                send_file_not_found();
            }

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'user', 'backup', 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 0, 0, true, $sendfileoptions); // must force download - security!

        } else {
            send_file_not_found();
        }

    // ========================================================================================================================
    } else if ($component === 'coursecat') {
        if ($context->contextlevel != CONTEXT_COURSECAT) {
            send_file_not_found();
        }

        if ($filearea === 'description') {
            if ($CFG->forcelogin) {
                // no login necessary - unless login forced everywhere
                require_login();
            }

            // Check if user can view this category.
            if (!core_course_category::get($context->instanceid, IGNORE_MISSING)) {
                send_file_not_found();
            }

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'coursecat', 'description', 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);
        } else {
            send_file_not_found();
        }

    // ========================================================================================================================
    } else if ($component === 'course') {
        if ($context->contextlevel != CONTEXT_COURSE) {
            send_file_not_found();
        }

        if ($filearea === 'summary' || $filearea === 'overviewfiles') {
            if ($CFG->forcelogin) {
                require_login();
            }

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'course', $filearea, 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else if ($filearea === 'section') {
            if ($CFG->forcelogin) {
                require_login($course);
            } else if ($course->id != SITEID) {
                require_login($course);
            }

            $sectionid = (int)array_shift($args);

            if (!$section = $DB->get_record('course_sections', array('id'=>$sectionid, 'course'=>$course->id))) {
                send_file_not_found();
            }

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'course', 'section', $sectionid, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else {
            send_file_not_found();
        }

    } else if ($component === 'cohort') {

        $cohortid = (int)array_shift($args);
        $cohort = $DB->get_record('cohort', array('id' => $cohortid), '*', MUST_EXIST);
        $cohortcontext = context::instance_by_id($cohort->contextid);

        // The context in the file URL must be either cohort context or context of the course underneath the cohort's context.
        if ($context->id != $cohort->contextid &&
            ($context->contextlevel != CONTEXT_COURSE || !in_array($cohort->contextid, $context->get_parent_context_ids()))) {
            send_file_not_found();
        }

        // User is able to access cohort if they have view cap on cohort level or
        // the cohort is visible and they have view cap on course level.
        $canview = has_capability('moodle/cohort:view', $cohortcontext) ||
                ($cohort->visible && has_capability('moodle/cohort:view', $context));

        if ($filearea === 'description' && $canview) {
            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (($file = $fs->get_file($cohortcontext->id, 'cohort', 'description', $cohort->id, $filepath, $filename))
                    && !$file->is_directory()) {
                \core\session\manager::write_close(); // Unlock session during file serving.
                send_stored_file($file, 60 * 60, 0, $forcedownload, $sendfileoptions);
            }
        }

        send_file_not_found();

    } else if ($component === 'group') {
        if ($context->contextlevel != CONTEXT_COURSE) {
            send_file_not_found();
        }

        require_course_login($course, true, null, false);

        $groupid = (int)array_shift($args);

        $group = $DB->get_record('groups', array('id'=>$groupid, 'courseid'=>$course->id), '*', MUST_EXIST);
        if (($course->groupmodeforce and $course->groupmode == SEPARATEGROUPS) and !has_capability('moodle/site:accessallgroups', $context) and !groups_is_member($group->id, $USER->id)) {
            // do not allow access to separate group info if not member or teacher
            send_file_not_found();
        }

        if ($filearea === 'description') {

            require_login($course);

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'group', 'description', $group->id, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else if ($filearea === 'icon') {
            $filename = array_pop($args);

            if ($filename !== 'f1' and $filename !== 'f2') {
                send_file_not_found();
            }
            if (!$file = $fs->get_file($context->id, 'group', 'icon', $group->id, '/', $filename.'.png')) {
                if (!$file = $fs->get_file($context->id, 'group', 'icon', $group->id, '/', $filename.'.jpg')) {
                    send_file_not_found();
                }
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, false, $sendfileoptions);

        } else {
            send_file_not_found();
        }

    } else if ($component === 'grouping') {
        if ($context->contextlevel != CONTEXT_COURSE) {
            send_file_not_found();
        }

        require_login($course);

        $groupingid = (int)array_shift($args);

        // note: everybody has access to grouping desc images for now
        if ($filearea === 'description') {

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'grouping', 'description', $groupingid, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else {
            send_file_not_found();
        }

    // ========================================================================================================================
    } else if ($component === 'backup') {
        if ($filearea === 'course' and $context->contextlevel == CONTEXT_COURSE) {
            require_login($course);
            require_capability('moodle/backup:downloadfile', $context);

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'backup', 'course', 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 0, 0, $forcedownload, $sendfileoptions);

        } else if ($filearea === 'section' and $context->contextlevel == CONTEXT_COURSE) {
            require_login($course);
            require_capability('moodle/backup:downloadfile', $context);

            $sectionid = (int)array_shift($args);

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'backup', 'section', $sectionid, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close();
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else if ($filearea === 'activity' and $context->contextlevel == CONTEXT_MODULE) {
            require_login($course, false, $cm);
            require_capability('moodle/backup:downloadfile', $context);

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'backup', 'activity', 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close();
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);

        } else if ($filearea === 'automated' and $context->contextlevel == CONTEXT_COURSE) {
            // Backup files that were generated by the automated backup systems.

            require_login($course);
            require_capability('moodle/backup:downloadfile', $context);
            require_capability('moodle/restore:userinfo', $context);

            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'backup', 'automated', 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 0, 0, $forcedownload, $sendfileoptions);

        } else {
            send_file_not_found();
        }

    // ========================================================================================================================
    } else if ($component === 'question') {
        require_once($CFG->dirroot . '/mod/thquiz/questionlib.php');
        question_cluster_pluginfile($course, $context, 'question', $filearea, $args, $forcedownload, $sendfileoptions);
        send_file_not_found();

    // ========================================================================================================================
    } else if ($component === 'grading') {
        if ($filearea === 'description') {
            // files embedded into the form definition description

            if ($context->contextlevel == CONTEXT_SYSTEM) {
                require_login();

            } else if ($context->contextlevel >= CONTEXT_COURSE) {
                require_login($course, false, $cm);

            } else {
                send_file_not_found();
            }

            $formid = (int)array_shift($args);

            $sql = "SELECT ga.id
                FROM {grading_areas} ga
                JOIN {grading_definitions} gd ON (gd.areaid = ga.id)
                WHERE gd.id = ? AND ga.contextid = ?";
            $areaid = $DB->get_field_sql($sql, array($formid, $context->id), IGNORE_MISSING);

            if (!$areaid) {
                send_file_not_found();
            }

            $fullpath = "/$context->id/$component/$filearea/$formid/".implode('/', $args);

            if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                send_file_not_found();
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, 60*60, 0, $forcedownload, $sendfileoptions);
        }
    } else if ($component === 'contentbank') {
        if ($filearea != 'public' || isguestuser()) {
            send_file_not_found();
        }

        if ($context->contextlevel == CONTEXT_SYSTEM || $context->contextlevel == CONTEXT_COURSECAT) {
            require_login();
        } else if ($context->contextlevel == CONTEXT_COURSE) {
            require_login($course);
        } else {
            send_file_not_found();
        }

        $componentargs = fullclone($args);
        $itemid = (int)array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/'.implode('/', $args).'/' : '/';

        \core\session\manager::write_close(); // Unlock session during file serving.

        $contenttype = $DB->get_field('contentbank_content', 'contenttype', ['id' => $itemid]);
        if (component_class_callback("\\{$contenttype}\\contenttype", 'pluginfile',
                [$course, null, $context, $filearea, $componentargs, $forcedownload, $sendfileoptions], false) === false) {

            if (!$file = $fs->get_file($context->id, $component, $filearea, $itemid, $filepath, $filename) or

                $file->is_directory()) {
                send_file_not_found();

            } else {
                send_stored_file($file, 0, 0, true, $sendfileoptions); // Must force download - security!
            }
        }
    } else if (strpos($component, 'mod_') === 0) {
        $modname = substr($component, 4);
        if (!file_exists("$CFG->dirroot/mod/$modname/lib.php")) {
            send_file_not_found();
        }
        require_once("$CFG->dirroot/mod/$modname/lib.php");

        if ($context->contextlevel == CONTEXT_MODULE) {
            if ($cm->modname !== $modname) {
                // somebody tries to gain illegal access, cm type must match the component!
                send_file_not_found();
            }
        }

        if ($filearea === 'intro') {
            if (!plugin_supports('mod', $modname, FEATURE_MOD_INTRO, true)) {
                send_file_not_found();
            }

            // Require login to the course first (without login to the module).
            require_course_login($course, true);

            // Now check if module is available OR it is restricted but the intro is shown on the course page.
            $cminfo = cm_info::create($cm);
            if (!$cminfo->uservisible) {
                if (!$cm->showdescription || !$cminfo->is_visible_on_course_page()) {
                    // Module intro is not visible on the course page and module is not available, show access error.
                    require_course_login($course, true, $cminfo);
                }
            }

            // all users may access it
            $filename = array_pop($args);
            $filepath = $args ? '/'.implode('/', $args).'/' : '/';
            if (!$file = $fs->get_file($context->id, 'mod_'.$modname, 'intro', 0, $filepath, $filename) or $file->is_directory()) {
                send_file_not_found();
            }

            // finally send the file
            send_stored_file($file, null, 0, false, $sendfileoptions);
        }

        $filefunction = $component.'_pluginfile';
        $filefunctionold = $modname.'_pluginfile';
        if (function_exists($filefunction)) {
            // if the function exists, it must send the file and terminate. Whatever it returns leads to "not found"
            $filefunction($course, $cm, $context, $filearea, $args, $forcedownload, $sendfileoptions);
        } else if (function_exists($filefunctionold)) {
            // if the function exists, it must send the file and terminate. Whatever it returns leads to "not found"
            $filefunctionold($course, $cm, $context, $filearea, $args, $forcedownload, $sendfileoptions);
        }

        send_file_not_found();

    // ========================================================================================================================
    } else if (strpos($component, 'block_') === 0) {
        $blockname = substr($component, 6);
        // note: no more class methods in blocks please, that is ....
        if (!file_exists("$CFG->dirroot/blocks/$blockname/lib.php")) {
            send_file_not_found();
        }
        require_once("$CFG->dirroot/blocks/$blockname/lib.php");

        if ($context->contextlevel == CONTEXT_BLOCK) {
            $birecord = $DB->get_record('block_instances', array('id'=>$context->instanceid), '*',MUST_EXIST);
            if ($birecord->blockname !== $blockname) {
                // somebody tries to gain illegal access, cm type must match the component!
                send_file_not_found();
            }

            if ($context->get_course_context(false)) {
                // If block is in course context, then check if user has capability to access course.
                require_course_login($course);
            } else if ($CFG->forcelogin) {
                // If user is logged out, bp record will not be visible, even if the user would have access if logged in.
                require_login();
            }

            $bprecord = $DB->get_record('block_positions', array('contextid' => $context->id, 'blockinstanceid' => $context->instanceid));
            // User can't access file, if block is hidden or doesn't have block:view capability
            if (($bprecord && !$bprecord->visible) || !has_capability('moodle/block:view', $context)) {
                send_file_not_found();
            }
        } else {
            $birecord = null;
        }

        $filefunction = $component.'_pluginfile';
        if (function_exists($filefunction)) {
            // if the function exists, it must send the file and terminate. Whatever it returns leads to "not found"
            $filefunction($course, $birecord, $context, $filearea, $args, $forcedownload, $sendfileoptions);
        }

        send_file_not_found();

    // ========================================================================================================================
    } else if (strpos($component, '_') === false) {
        // all core subsystems have to be specified above, no more guessing here!
        send_file_not_found();

    } else {
        // try to serve general plugin file in arbitrary context
        $dir = core_component::get_component_directory($component);
        if (!file_exists("$dir/lib.php")) {
            send_file_not_found();
        }
        include_once("$dir/lib.php");

        $filefunction = $component.'_pluginfile';
        if (function_exists($filefunction)) {
            // if the function exists, it must send the file and terminate. Whatever it returns leads to "not found"
            $filefunction($course, $cm, $context, $filearea, $args, $forcedownload, $sendfileoptions);
        }

        send_file_not_found();
    }

}


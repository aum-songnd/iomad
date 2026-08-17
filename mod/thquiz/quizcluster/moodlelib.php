<?php

require_once $CFG->dirroot . '/lib/moodlelib.php'; 

function require_login_cluster($courseorid = null, $autologinguest = true, $cm = null, $setwantsurltome = true, $preventredirect = false) {
    global $CFG, $SESSION, $USER, $PAGE, $SITE, $DB, $OUTPUT;

    // Must not redirect when byteserving already started.
    if (!empty($_SERVER['HTTP_RANGE'])) {
        $preventredirect = true;
    }

    if (AJAX_SCRIPT) {
        // We cannot redirect for AJAX scripts either.
        $preventredirect = true;
    }

    // Setup global $COURSE, themes, language and locale.
    if (!empty($courseorid)) {
        if (is_object($courseorid)) {
            $course = $courseorid;
        } else if ($courseorid == SITEID) {
            $course = clone($SITE);
        } else {
            $course = $DB->get_record('course', array('id' => $courseorid), '*', MUST_EXIST);
        }
        if ($cm) {
            if ($cm->course != $course->id) {
                throw new coding_exception('course and cm parameters in require_login() call do not match!!');
            }
            // Make sure we have a $cm from get_fast_modinfo as this contains activity access details.
            if (!($cm instanceof cm_info)) {
                // Note: nearly all pages call get_fast_modinfo anyway and it does not make any
                // db queries so this is not really a performance concern, however it is obviously
                // better if you use get_fast_modinfo to get the cm before calling this.
                $modinfo = get_fast_modinfo($course);
                $cm = $modinfo->get_cm($cm->id);
            }
        }
    } else {
        // Do not touch global $COURSE via $PAGE->set_course(),
        // the reasons is we need to be able to call require_login() at any time!!
        $course = $SITE;
        if ($cm) {
            throw new coding_exception('cm parameter in require_login() requires valid course parameter!');
        }
    }

    // If this is an AJAX request and $setwantsurltome is true then we need to override it and set it to false.
    // Otherwise the AJAX request URL will be set to $SESSION->wantsurl and events such as self enrolment in the future
    // risk leading the user back to the AJAX request URL.
    if ($setwantsurltome && defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
        $setwantsurltome = false;
    }

    // Redirect to the login page if session has expired, only with dbsessions enabled (MDL-35029) to maintain current behaviour.
    if ((!isloggedin() or isguestuser()) && !empty($SESSION->has_timed_out) && !empty($CFG->dbsessions)) {
        if ($preventredirect) {
            throw new require_login_session_timeout_exception();
        } else {
            if ($setwantsurltome) {
                $SESSION->wantsurl = qualified_me();
            }
            redirect(get_login_url());
        }
    }

    // If the user is not even logged in yet then make sure they are.
    if (!isloggedin()) {
        if ($autologinguest && !empty($CFG->autologinguests)) {
            if (!$guest = get_complete_user_data('id', $CFG->siteguest)) {
                // Misconfigured site guest, just redirect to login page.
                redirect(get_login_url());
                exit; // Never reached.
            }
            $lang = isset($SESSION->lang) ? $SESSION->lang : $CFG->lang;
            complete_user_login($guest);
            $USER->autologinguest = true;
            $SESSION->lang = $lang;
        } else {
            // NOTE: $USER->site check was obsoleted by session test cookie, $USER->confirmed test is in login/index.php.
            if ($preventredirect) {
                throw new require_login_exception('You are not logged in');
            }

            if ($setwantsurltome) {
                $SESSION->wantsurl = qualified_me();
            }

            // Give auth plugins an opportunity to authenticate or redirect to an external login page
            $authsequence = get_enabled_auth_plugins(); // Auths, in sequence.
            foreach($authsequence as $authname) {
                $authplugin = get_auth_plugin($authname);
                $authplugin->pre_loginpage_hook();
                if (isloggedin()) {
                    if ($cm) {
                        $modinfo = get_fast_modinfo($course);
                        $cm = $modinfo->get_cm($cm->id);
                    }
                    set_access_log_user();
                    break;
                }
            }

            // If we're still not logged in then go to the login page
            if (!isloggedin()) {
                redirect(get_login_url());
                exit; // Never reached.
            }
        }
    }

    // Loginas as redirection if needed.
    if ($course->id != SITEID and \core\session\manager::is_loggedinas()) {
        if ($USER->loginascontext->contextlevel == CONTEXT_COURSE) {
            if ($USER->loginascontext->instanceid != $course->id) {
                throw new \moodle_exception('loginasonecourse', '',
                    $CFG->wwwroot.'/course/view.php?id='.$USER->loginascontext->instanceid);
            }
        }
    }

    // Check whether the user should be changing password (but only if it is REALLY them).
    if (get_user_preferences('auth_forcepasswordchange') && !\core\session\manager::is_loggedinas()) {
        $userauth = get_auth_plugin($USER->auth);
        if ($userauth->can_change_password() and !$preventredirect) {
            if ($setwantsurltome) {
                $SESSION->wantsurl = qualified_me();
            }
            if ($changeurl = $userauth->change_password_url()) {
                // Use plugin custom url.
                redirect($changeurl);
            } else {
                // Use moodle internal method.
                redirect($CFG->wwwroot .'/login/change_password.php');
            }
        } else if ($userauth->can_change_password()) {
            throw new moodle_exception('forcepasswordchangenotice');
        } else {
            throw new moodle_exception('nopasswordchangeforced', 'auth');
        }
    }

    // Check that the user account is properly set up. If we can't redirect to
    // edit their profile and this is not a WS request, perform just the lax check.
    // It will allow them to use filepicker on the profile edit page.

    if ($preventredirect && !WS_SERVER) {
        $usernotfullysetup = user_not_fully_set_up($USER, false);
    } else {
        $usernotfullysetup = user_not_fully_set_up($USER, true);
    }

    if ($usernotfullysetup) {
        if ($preventredirect) {
            throw new moodle_exception('usernotfullysetup');
        }
        if ($setwantsurltome) {
            $SESSION->wantsurl = qualified_me();
        }
        redirect($CFG->wwwroot .'/user/edit.php?id='. $USER->id .'&amp;course='. SITEID);
    }

    // Make sure the USER has a sesskey set up. Used for CSRF protection.
    sesskey();

    if (\core\session\manager::is_loggedinas()) {
        // During a "logged in as" session we should force all content to be cleaned because the
        // logged in user will be viewing potentially malicious user generated content.
        // See MDL-63786 for more details.
        $CFG->forceclean = true;
    }

    $afterlogins = get_plugins_with_function('after_require_login', 'lib.php');

    // Do not bother admins with any formalities, except for activities pending deletion.
    if (is_siteadmin() && !($cm && $cm->deletioninprogress)) {
        // Set the global $COURSE.
        if ($cm) {
            $PAGE->set_cm($cm, $course);
            $PAGE->set_pagelayout('incourse');
        } else if (!empty($courseorid)) {
            $PAGE->set_course($course);
        }
        // Set accesstime or the user will appear offline which messes up messaging.
        // Do not update access time for webservice or ajax requests.
        if (!WS_SERVER && !AJAX_SCRIPT) {
            user_accesstime_log($course->id);
        }

        foreach ($afterlogins as $plugintype => $plugins) {
            foreach ($plugins as $pluginfunction) {
                $pluginfunction($courseorid, $autologinguest, $cm, $setwantsurltome, $preventredirect);
            }
        }
        return;
    }

    // Scripts have a chance to declare that $USER->policyagreed should not be checked.
    // This is mostly for places where users are actually accepting the policies, to avoid the redirect loop.
    if (!defined('NO_SITEPOLICY_CHECK')) {
        define('NO_SITEPOLICY_CHECK', false);
    }

    // Check that the user has agreed to a site policy if there is one - do not test in case of admins.
    // Do not test if the script explicitly asked for skipping the site policies check.
    // Or if the user auth type is webservice.
    if (!$USER->policyagreed && !is_siteadmin() && !NO_SITEPOLICY_CHECK && $USER->auth !== 'webservice') {
        $manager = new \core_privacy\local\sitepolicy\manager();
        if ($policyurl = $manager->get_redirect_url(isguestuser())) {
            if ($preventredirect) {
                throw new moodle_exception('sitepolicynotagreed', 'error', '', $policyurl->out());
            }
            if ($setwantsurltome) {
                $SESSION->wantsurl = qualified_me();
            }
            redirect($policyurl);
        }
    }

    // Fetch the system context, the course context, and prefetch its child contexts.
    $sysctx = context_system::instance();
    $coursecontext = context_course::instance($course->id, MUST_EXIST);
    if ($cm) {
        $cmcontext = context_module::instance($cm->id, MUST_EXIST);
    } else {
        $cmcontext = null;
    }

    // If the site is currently under maintenance, then print a message.
    if (!empty($CFG->maintenance_enabled) and !has_capability('moodle/site:maintenanceaccess', $sysctx)) {
        if ($preventredirect) {
            throw new require_login_exception('Maintenance in progress');
        }
        $PAGE->set_context(null);
        print_maintenance_message();
    }

    // Make sure the course itself is not hidden.
    if ($course->id == SITEID) {
        // Frontpage can not be hidden.
    } else {
        if (is_role_switched($course->id)) {
            // When switching roles ignore the hidden flag - user had to be in course to do the switch.
        } else {
            if (!$course->visible and !has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
                // Originally there was also test of parent category visibility, BUT is was very slow in complex queries
                // involving "my courses" now it is also possible to simply hide all courses user is not enrolled in :-).
                if ($preventredirect) {
                    throw new require_login_exception('Course is hidden');
                }
                $PAGE->set_context(null);
                // We need to override the navigation URL as the course won't have been added to the navigation and thus
                // the navigation will mess up when trying to find it.
                navigation_node::override_active_url(new moodle_url('/'));
                notice(get_string('coursehidden'), $CFG->wwwroot .'/');
            }
        }
    }

    // Is the user enrolled?
    if ($course->id == SITEID) {
        // Everybody is enrolled on the frontpage.
    } else {
        if (\core\session\manager::is_loggedinas()) {
            // Make sure the REAL person can access this course first.
            $realuser = \core\session\manager::get_realuser();
            if (!is_enrolled($coursecontext, $realuser->id, '', true) and
                !is_viewing($coursecontext, $realuser->id) and !is_siteadmin($realuser->id)) {
                if ($preventredirect) {
                    throw new require_login_exception('Invalid course login-as access');
                }
                $PAGE->set_context(null);
                echo $OUTPUT->header();
                notice(get_string('studentnotallowed', '', fullname($USER, true)), $CFG->wwwroot .'/');
            }
        }

        $access = false;

        if (is_role_switched($course->id)) {
            // Ok, user had to be inside this course before the switch.
            $access = true;

        } else if (is_viewing($coursecontext, $USER)) {
            // Ok, no need to mess with enrol.
            $access = true;

        } else {
            if (isset($USER->enrol['enrolled'][$course->id])) {
                if ($USER->enrol['enrolled'][$course->id] > time()) {
                    $access = true;
                    if (isset($USER->enrol['tempguest'][$course->id])) {
                        unset($USER->enrol['tempguest'][$course->id]);
                        remove_temp_course_roles($coursecontext);
                    }
                } else {
                    // Expired.
                    unset($USER->enrol['enrolled'][$course->id]);
                }
            }
            if (isset($USER->enrol['tempguest'][$course->id])) {
                if ($USER->enrol['tempguest'][$course->id] == 0) {
                    $access = true;
                } else if ($USER->enrol['tempguest'][$course->id] > time()) {
                    $access = true;
                } else {
                    // Expired.
                    unset($USER->enrol['tempguest'][$course->id]);
                    remove_temp_course_roles($coursecontext);
                }
            }

            if (!$access) {
                // Cache not ok.
                $until = enrol_get_enrolment_end($coursecontext->instanceid, $USER->id);
                if ($until !== false) {
                    // Active participants may always access, a timestamp in the future, 0 (always) or false.
                    if ($until == 0) {
                        $until = ENROL_MAX_TIMESTAMP;
                    }
                    $USER->enrol['enrolled'][$course->id] = $until;
                    $access = true;

                } else if (core_course_category::can_view_course_info($course)) {
                    $params = array('courseid' => $course->id, 'status' => ENROL_INSTANCE_ENABLED);
                    $instances = $DB->get_records('enrol', $params, 'sortorder, id ASC');
                    $enrols = enrol_get_plugins(true);
                    // First ask all enabled enrol instances in course if they want to auto enrol user.
                    foreach ($instances as $instance) {
                        if (!isset($enrols[$instance->enrol])) {
                            continue;
                        }
                        // Get a duration for the enrolment, a timestamp in the future, 0 (always) or false.
                        $until = $enrols[$instance->enrol]->try_autoenrol($instance);
                        if ($until !== false) {
                            if ($until == 0) {
                                $until = ENROL_MAX_TIMESTAMP;
                            }
                            $USER->enrol['enrolled'][$course->id] = $until;
                            $access = true;
                            break;
                        }
                    }
                    // If not enrolled yet try to gain temporary guest access.
                    if (!$access) {
                        foreach ($instances as $instance) {
                            if (!isset($enrols[$instance->enrol])) {
                                continue;
                            }
                            // Get a duration for the guest access, a timestamp in the future or false.
                            $until = $enrols[$instance->enrol]->try_guestaccess($instance);
                            if ($until !== false and $until > time()) {
                                $USER->enrol['tempguest'][$course->id] = $until;
                                $access = true;
                                break;
                            }
                        }
                    }
                } else {
                    // User is not enrolled and is not allowed to browse courses here.
                    if ($preventredirect) {
                        throw new require_login_exception('Course is not available');
                    }
                    $PAGE->set_context(null);
                    // We need to override the navigation URL as the course won't have been added to the navigation and thus
                    // the navigation will mess up when trying to find it.
                    navigation_node::override_active_url(new moodle_url('/'));
                    notice(get_string('coursehidden'), $CFG->wwwroot .'/');
                }
            }
        }

        if (!$access) {
            if ($preventredirect) {
                throw new require_login_exception('Not enrolled');
            }
            if ($setwantsurltome) {
                $SESSION->wantsurl = qualified_me();
            }
            redirect($CFG->wwwroot .'/enrol/index.php?id='. $course->id);
        }
    }

    // Check whether the activity has been scheduled for deletion. If so, then deny access, even for admins.
    if ($cm && $cm->deletioninprogress) {
        if ($preventredirect) {
            throw new moodle_exception('activityisscheduledfordeletion');
        }
        require_once($CFG->dirroot . '/course/lib.php');
        redirect(course_get_url($course), get_string('activityisscheduledfordeletion', 'error'));
    }

    // Check visibility of activity to current user; includes visible flag, conditional availability, etc.
    // if ($cm && !$cm->uservisible) {
    //     if ($preventredirect) {
    //         throw new require_login_exception('Activity is hidden');
    //     }
    //     // Get the error message that activity is not available and why (if explanation can be shown to the user).
    //     $PAGE->set_course($course);
    //     $renderer = $PAGE->get_renderer('course');
    //     $message = $renderer->course_section_cm_unavailable_error_message($cm);
    //     redirect(course_get_url($course), $message, null, \core\output\notification::NOTIFY_ERROR);
    // }

    // Set the global $COURSE.
    if ($cm) {
        $PAGE->set_cm($cm, $course);
        $PAGE->set_pagelayout('incourse');
    } else if (!empty($courseorid)) {
        $PAGE->set_course($course);
    }

    foreach ($afterlogins as $plugintype => $plugins) {
        foreach ($plugins as $pluginfunction) {
            $pluginfunction($courseorid, $autologinguest, $cm, $setwantsurltome, $preventredirect);
        }
    }

    // Finally access granted, update lastaccess times.
    // Do not update access time for webservice or ajax requests.
    if (!WS_SERVER && !AJAX_SCRIPT) {
        user_accesstime_log($course->id);
    }
}

?>
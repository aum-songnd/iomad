<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/classes/helper/roadmap_helper.php');
require_once(__DIR__ . '/classes/helper/achievement_helper.php');

use \local_th_customstappapi\helper\roadmap_helper;
use \local_th_customstappapi\helper\achievement_helper;

class local_th_customstappapi_external extends external_api {

    public static function get_user_achievements_parameters() {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User id')
        ]);
    }

    public static function get_user_achievements($userid) {
        global $USER;

        $params = self::validate_parameters(self::get_user_achievements_parameters(), [
            'userid' => $userid
        ]);
        $userid = (int)$params['userid'];

        $context = context_system::instance();
        self::validate_context($context);
        if ($USER->id != $userid && !is_siteadmin()) {
            throw new moodle_exception('nopermissions', 'error');
        }

        $config_idle = get_config('local_th_customstappapi', 'idletime');
        $idlelimit = ($config_idle === false || !is_numeric($config_idle)) ? 600 : (int)$config_idle;

        $usercourses = roadmap_helper::get_user_roadmap_courseids($userid);
        $courseids = roadmap_helper::get_assessment_courseids($userid, roadmap_helper::ENTRY_ONLY);
        $entrance_cid = !empty($courseids) ? reset($courseids) : -1;

        $sections = achievement_helper::get_completed_sections($userid, $usercourses);
        $avggrade = achievement_helper::get_avg_grade($userid, $usercourses, $entrance_cid);
        $studym = achievement_helper::get_study_minutes_last7days($userid, $idlelimit, $usercourses);

        return [
            'topics' => [
                'completed' => (int)($sections->completedsections ?? 0),
                'total' => (int)($sections->totalsections ?? 0),
            ],
            'avg_grade' => $avggrade,
            'study_minutes_last7days' => $studym,
        ];

    }

    public static function get_user_achievements_returns() {
        return new external_single_structure([
            'topics' => new external_single_structure([
                'completed' => new external_value(PARAM_INT, 'Completed topics (sections)'),
                'total'     => new external_value(PARAM_INT, 'Total topics (sections)'),
            ]),
            'avg_grade' => new external_single_structure([
                'practice' => new external_single_structure([
                    'value' => new external_value(
                        PARAM_FLOAT,
                        'Điểm trung bình bài luyện tập',
                        VALUE_OPTIONAL
                    )
                ]),
                'skills' => new external_single_structure([
                    'overall' => new external_value(PARAM_FLOAT, 'Điểm trung bình chung 4 kỹ năng', VALUE_OPTIONAL),
                    'listening' => new external_value(PARAM_FLOAT, 'Điểm trung bình Listening', VALUE_OPTIONAL),
                    'speaking' => new external_value(PARAM_FLOAT, 'Điểm trung bình Speaking', VALUE_OPTIONAL),
                    'reading' => new external_value(PARAM_FLOAT, 'Điểm trung bình Reading', VALUE_OPTIONAL),
                    'writing' => new external_value(PARAM_FLOAT, 'Điểm trung bình Writing', VALUE_OPTIONAL),
                ])
            ]),
            'study_minutes_last7days' => new external_single_structure([
                'total' => new external_value(PARAM_FLOAT, 'Tổng phút học trong 7 ngày gần nhất'),
                'avg_per_day' => new external_value(PARAM_FLOAT, 'Trung bình phút học mỗi ngày'),
                'daily' => new external_multiple_structure(
                    new external_single_structure([
                        'date' => new external_value(PARAM_TEXT, 'Ngày (YYYY-MM-DD)'),
                        'minutes' => new external_value(PARAM_FLOAT, 'Số phút học trong ngày'),
                    ])
                )
            ])
        ]);
    }

    private static function build_courses_output(array $courseids, int $userid): array {
        global $DB, $USER;

        if (empty($courseids)) {
            return [];
        }

        $sameuser = ($USER->id === $userid);
        $user = get_complete_user_data('id', $userid);

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $courses = $DB->get_records_sql(
            "SELECT * FROM {course} WHERE id $insql",
            $inparams
        );

        if (!$courses) {
            return [];
        }

        // Favourite
        $favouritecourseids = [];
        if ($sameuser) {
            $ufservice = \core_favourites\service_factory::get_service_for_user_context(
                \context_user::instance($userid)
            );
            foreach ($ufservice->find_favourites_by_type('core_course', 'courses') ?? [] as $fav) {
                $favouritecourseids[$fav->itemid] = true;
            }
        }

        $result = [];

        foreach ($courseids as $courseid) {
            if (!isset($courses[$courseid])) {
                continue;
            }

            $course = $courses[$courseid];
            $context = context_course::instance($courseid, IGNORE_MISSING);

            try {
                self::validate_context($context);
            } catch (Exception $e) {
                continue;
            }

            if (
                !$sameuser &&
                (!course_can_view_participants($context) ||
                !user_can_view_profile($user, $course))
            ) {
                continue;
            }

            list($summary, $summaryformat) = external_format_text(
                $course->summary,
                $course->summaryformat,
                $context->id,
                'course',
                'summary',
                null
            );

            // Completion
            $progress = null;
            $completed = null;
            $completionhascriteria = null;
            $completionusertracked = null;

            if ($sameuser || completion_can_view_data($userid, $course)) {
                if ($course->enablecompletion) {
                    $completion = new completion_info($course);
                    $completed = (bool)$completion->is_course_complete($userid);
                    $completionhascriteria = (bool)$completion->has_criteria();
                    $completionusertracked = (bool)$completion->is_tracked_user($userid);
                    $progress = \core_completion\progress::get_course_progress_percentage($course, $userid);
                }
            }

            // Last access
            $lastaccess = null;
            if ($sameuser && isset($user->lastcourseaccess[$courseid])) {
                $lastaccess = $user->lastcourseaccess[$courseid];
            }

            // Hidden
            $hidden = $sameuser
                ? (bool)get_user_preferences('block_myoverview_hidden_course_' . $courseid, 0)
                : false;

            // Overview files
            $overviewfiles = [];
            $courselist = new core_course_list_element($course);
            foreach ($courselist->get_course_overviewfiles() as $file) {
                $overviewfiles[] = [
                    'filename' => $file->get_filename(),
                    'filepath' => $file->get_filepath(),
                    'filesize' => $file->get_filesize(),
                    'fileurl'  => moodle_url::make_webservice_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        null,
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(false),
                    'timemodified' => $file->get_timemodified(),
                    'mimetype' => $file->get_mimetype(),
                    'isexternalfile' => $file->is_external_file(),
                    'repositorytype' => $file->get_repository_type(),
                ];
            }

            $result[] = [
                'id' => $course->id,
                'shortname' => $course->shortname,
                'fullname' => $course->fullname,
                'displayname' => get_course_display_name_for_list($course),
                'idnumber' => $course->idnumber,
                'visible' => (int)$course->visible,
                'summary' => $summary,
                'summaryformat' => $summaryformat,
                'format' => $course->format,
                'showgrades' => (bool)$course->showgrades,
                'lang' => clean_param($course->lang, PARAM_LANG),
                'enablecompletion' => (bool)$course->enablecompletion,
                'completionhascriteria' => $completionhascriteria,
                'completionusertracked' => $completionusertracked,
                'category' => (int)$course->category,
                'progress' => $progress,
                'completed' => $completed,
                'startdate' => (int)$course->startdate,
                'enddate' => (int)$course->enddate,
                'marker' => (int)$course->marker,
                'lastaccess' => $lastaccess,
                'isfavourite' => isset($favouritecourseids[$courseid]),
                'hidden' => $hidden,
                'overviewfiles' => $overviewfiles,
                'showactivitydates' => (bool)$course->showactivitydates,
                'showcompletionconditions' => (bool)$course->showcompletionconditions,
                'timemodified' => (int)$course->timemodified,
            ];
        }

        return $result;
    }

    public static function get_roadmap_courses_parameters() {
        return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_INT, 'user id'),
                'limit' => new external_value(PARAM_INT,'Number of courses to be returned',4, true),
            )
        );
    }

    public static function get_roadmap_courses(int $userid, int $limit) {
        global $DB, $USER, $CFG;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/user/lib.php');

        // 1. Validate parameters
        $params = self::validate_parameters(
            self::get_roadmap_courses_parameters(),
            ['userid' => $userid, 'limit' => $limit]
        );

        $userid = (int)$params['userid'];
        $limit  = (int)$params['limit'];

        if ($limit <= 0) {
            $limit = 4;
        }

        if (!$DB->record_exists('user', ['id' => $userid])) {
            throw new moodle_exception('invaliduser', 'error');
        }

        // 2. Lấy courseid từ roadmap helper (đã cache)
        $courseids = roadmap_helper::get_user_roadmap_courseids($userid);

        // 3. Build full course output bằng helper chung
        $courses = self::build_courses_output($courseids, $userid);
        if (empty($courses)) {
            return [];
        }

        // 4. Phân loại theo trạng thái học
        $inprogress = [];
        $notstarted = [];
        $completed  = [];

        foreach ($courses as $course) {
            if (!empty($course['completed'])) {
                $completed[] = $course;
            } elseif (!empty($course['progress']) && $course['progress'] > 0) {
                $inprogress[] = $course;
            } else {
                $notstarted[] = $course;
            }
        }

        // 5. Ưu tiên + fill đủ limit
        $final = [];

        foreach ($inprogress as $course) {
            if (count($final) >= $limit) break;
            $final[] = $course;
        }

        if (count($final) < $limit) {
            foreach ($notstarted as $course) {
                if (count($final) >= $limit) break;
                $final[] = $course;
            }
        }

        if (count($final) < $limit) {
            foreach ($completed as $course) {
                if (count($final) >= $limit) break;
                $final[] = $course;
            }
        }

        return $final;
    }


    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_roadmap_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id'        => new external_value(PARAM_INT, 'id of course'),
                    'shortname' => new external_value(PARAM_RAW, 'short name of course'),
                    'fullname'  => new external_value(PARAM_RAW, 'long name of course'),
                    'displayname' => new external_value(PARAM_RAW, 'course display name for lists.', VALUE_OPTIONAL),
                    'enrolledusercount' => new external_value(PARAM_INT, 'Number of enrolled users in this course',
                            VALUE_OPTIONAL),
                    'idnumber'  => new external_value(PARAM_RAW, 'id number of course'),
                    'visible'   => new external_value(PARAM_INT, '1 means visible, 0 means not yet visible course'),
                    'summary'   => new external_value(PARAM_RAW, 'summary', VALUE_OPTIONAL),
                    'summaryformat' => new external_format_value('summary', VALUE_OPTIONAL),
                    'format'    => new external_value(PARAM_PLUGIN, 'course format: weeks, topics, social, site', VALUE_OPTIONAL),
                    'showgrades' => new external_value(PARAM_BOOL, 'true if grades are shown, otherwise false', VALUE_OPTIONAL),
                    'lang'      => new external_value(PARAM_LANG, 'forced course language', VALUE_OPTIONAL),
                    'enablecompletion' => new external_value(PARAM_BOOL, 'true if completion is enabled, otherwise false',
                                                                VALUE_OPTIONAL),
                    'completionhascriteria' => new external_value(PARAM_BOOL, 'If completion criteria is set.', VALUE_OPTIONAL),
                    'completionusertracked' => new external_value(PARAM_BOOL, 'If the user is completion tracked.', VALUE_OPTIONAL),
                    'category' => new external_value(PARAM_INT, 'course category id', VALUE_OPTIONAL),
                    'progress' => new external_value(PARAM_FLOAT, 'Progress percentage', VALUE_OPTIONAL),
                    'completed' => new external_value(PARAM_BOOL, 'Whether the course is completed.', VALUE_OPTIONAL),
                    'startdate' => new external_value(PARAM_INT, 'Timestamp when the course start', VALUE_OPTIONAL),
                    'enddate' => new external_value(PARAM_INT, 'Timestamp when the course end', VALUE_OPTIONAL),
                    'marker' => new external_value(PARAM_INT, 'Course section marker.', VALUE_OPTIONAL),
                    'lastaccess' => new external_value(PARAM_INT, 'Last access to the course (timestamp).', VALUE_OPTIONAL),
                    'isfavourite' => new external_value(PARAM_BOOL, 'If the user marked this course a favourite.', VALUE_OPTIONAL),
                    'hidden' => new external_value(PARAM_BOOL, 'If the user hide the course from the dashboard.', VALUE_OPTIONAL),
                    'overviewfiles' => new external_files('Overview files attached to this course.', VALUE_OPTIONAL),
                    'showactivitydates' => new external_value(PARAM_BOOL, 'Whether the activity dates are shown or not'),
                    'showcompletionconditions' => new external_value(PARAM_BOOL, 'Whether the activity completion conditions are shown or not'),
                    'timemodified' => new external_value(PARAM_INT, 'Last time course settings were updated (timestamp).',
                        VALUE_OPTIONAL),
                )
            )
        );
    }

    public static function get_assessment_courses_parameters() {
        return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_INT, 'user id'),
            )
        );
    }

    public static function get_assessment_courses(int $userid) {
        global $DB, $USER, $CFG;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/user/lib.php');

        $params = self::validate_parameters(
            self::get_assessment_courses_parameters(),
            ['userid' => $userid]
        );

        $userid = (int)$params['userid'];

        if (!$DB->record_exists('user', ['id' => $userid])) {
            throw new moodle_exception('invaliduser', 'error');
        }

        $courseids = roadmap_helper::get_assessment_courseids($userid);

        if (empty($courseids)) {
            return [];
        }

        return self::build_courses_output($courseids, $userid);
        
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_assessment_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id'        => new external_value(PARAM_INT, 'id of course'),
                    'shortname' => new external_value(PARAM_RAW, 'short name of course'),
                    'fullname'  => new external_value(PARAM_RAW, 'long name of course'),
                    'displayname' => new external_value(PARAM_RAW, 'course display name for lists.', VALUE_OPTIONAL),
                    'enrolledusercount' => new external_value(PARAM_INT, 'Number of enrolled users in this course',
                            VALUE_OPTIONAL),
                    'idnumber'  => new external_value(PARAM_RAW, 'id number of course'),
                    'visible'   => new external_value(PARAM_INT, '1 means visible, 0 means not yet visible course'),
                    'summary'   => new external_value(PARAM_RAW, 'summary', VALUE_OPTIONAL),
                    'summaryformat' => new external_format_value('summary', VALUE_OPTIONAL),
                    'format'    => new external_value(PARAM_PLUGIN, 'course format: weeks, topics, social, site', VALUE_OPTIONAL),
                    'showgrades' => new external_value(PARAM_BOOL, 'true if grades are shown, otherwise false', VALUE_OPTIONAL),
                    'lang'      => new external_value(PARAM_LANG, 'forced course language', VALUE_OPTIONAL),
                    'enablecompletion' => new external_value(PARAM_BOOL, 'true if completion is enabled, otherwise false',
                                                                VALUE_OPTIONAL),
                    'completionhascriteria' => new external_value(PARAM_BOOL, 'If completion criteria is set.', VALUE_OPTIONAL),
                    'completionusertracked' => new external_value(PARAM_BOOL, 'If the user is completion tracked.', VALUE_OPTIONAL),
                    'category' => new external_value(PARAM_INT, 'course category id', VALUE_OPTIONAL),
                    'progress' => new external_value(PARAM_FLOAT, 'Progress percentage', VALUE_OPTIONAL),
                    'completed' => new external_value(PARAM_BOOL, 'Whether the course is completed.', VALUE_OPTIONAL),
                    'startdate' => new external_value(PARAM_INT, 'Timestamp when the course start', VALUE_OPTIONAL),
                    'enddate' => new external_value(PARAM_INT, 'Timestamp when the course end', VALUE_OPTIONAL),
                    'marker' => new external_value(PARAM_INT, 'Course section marker.', VALUE_OPTIONAL),
                    'lastaccess' => new external_value(PARAM_INT, 'Last access to the course (timestamp).', VALUE_OPTIONAL),
                    'isfavourite' => new external_value(PARAM_BOOL, 'If the user marked this course a favourite.', VALUE_OPTIONAL),
                    'hidden' => new external_value(PARAM_BOOL, 'If the user hide the course from the dashboard.', VALUE_OPTIONAL),
                    'overviewfiles' => new external_files('Overview files attached to this course.', VALUE_OPTIONAL),
                    'showactivitydates' => new external_value(PARAM_BOOL, 'Whether the activity dates are shown or not'),
                    'showcompletionconditions' => new external_value(PARAM_BOOL, 'Whether the activity completion conditions are shown or not'),
                    'timemodified' => new external_value(PARAM_INT, 'Last time course settings were updated (timestamp).',
                        VALUE_OPTIONAL),
                )
            )
        );
    }

    public static function get_roadmap_first_course_parameters() {
        return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_INT, 'user id'),
            )
        );
    }

    public static function get_roadmap_first_course(int $userid) {
        global $DB, $USER, $CFG;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/user/lib.php');

        $params = self::validate_parameters(
            self::get_roadmap_first_course_parameters(),
            ['userid' => $userid]
        );
        $userid = (int)$params['userid'];

        $courseids = roadmap_helper::get_user_roadmap_courseids($userid);

        // Get the first course of roadmap
        $firstcourseid = reset($courseids);
        if (!$firstcourseid) {
            // throw new moodle_exception(
            //     'noroadmapcourse',
            //     'local_th_customstappapi',
            //     '',
            //     null,
            //     'Unable to resolve the first course from roadmap.'
            // );
            return [];
        }

        return self::build_courses_output([$firstcourseid], $userid);
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_roadmap_first_course_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id'        => new external_value(PARAM_INT, 'id of course'),
                    'shortname' => new external_value(PARAM_RAW, 'short name of course'),
                    'fullname'  => new external_value(PARAM_RAW, 'long name of course'),
                    'displayname' => new external_value(PARAM_RAW, 'course display name for lists.', VALUE_OPTIONAL),
                    'enrolledusercount' => new external_value(PARAM_INT, 'Number of enrolled users in this course',
                            VALUE_OPTIONAL),
                    'idnumber'  => new external_value(PARAM_RAW, 'id number of course'),
                    'visible'   => new external_value(PARAM_INT, '1 means visible, 0 means not yet visible course'),
                    'summary'   => new external_value(PARAM_RAW, 'summary', VALUE_OPTIONAL),
                    'summaryformat' => new external_format_value('summary', VALUE_OPTIONAL),
                    'format'    => new external_value(PARAM_PLUGIN, 'course format: weeks, topics, social, site', VALUE_OPTIONAL),
                    'showgrades' => new external_value(PARAM_BOOL, 'true if grades are shown, otherwise false', VALUE_OPTIONAL),
                    'lang'      => new external_value(PARAM_LANG, 'forced course language', VALUE_OPTIONAL),
                    'enablecompletion' => new external_value(PARAM_BOOL, 'true if completion is enabled, otherwise false',
                                                                VALUE_OPTIONAL),
                    'completionhascriteria' => new external_value(PARAM_BOOL, 'If completion criteria is set.', VALUE_OPTIONAL),
                    'completionusertracked' => new external_value(PARAM_BOOL, 'If the user is completion tracked.', VALUE_OPTIONAL),
                    'category' => new external_value(PARAM_INT, 'course category id', VALUE_OPTIONAL),
                    'progress' => new external_value(PARAM_FLOAT, 'Progress percentage', VALUE_OPTIONAL),
                    'completed' => new external_value(PARAM_BOOL, 'Whether the course is completed.', VALUE_OPTIONAL),
                    'startdate' => new external_value(PARAM_INT, 'Timestamp when the course start', VALUE_OPTIONAL),
                    'enddate' => new external_value(PARAM_INT, 'Timestamp when the course end', VALUE_OPTIONAL),
                    'marker' => new external_value(PARAM_INT, 'Course section marker.', VALUE_OPTIONAL),
                    'lastaccess' => new external_value(PARAM_INT, 'Last access to the course (timestamp).', VALUE_OPTIONAL),
                    'isfavourite' => new external_value(PARAM_BOOL, 'If the user marked this course a favourite.', VALUE_OPTIONAL),
                    'hidden' => new external_value(PARAM_BOOL, 'If the user hide the course from the dashboard.', VALUE_OPTIONAL),
                    'overviewfiles' => new external_files('Overview files attached to this course.', VALUE_OPTIONAL),
                    'showactivitydates' => new external_value(PARAM_BOOL, 'Whether the activity dates are shown or not'),
                    'showcompletionconditions' => new external_value(PARAM_BOOL, 'Whether the activity completion conditions are shown or not'),
                    'timemodified' => new external_value(PARAM_INT, 'Last time course settings were updated (timestamp).',
                        VALUE_OPTIONAL),
                )
            )
        );
    }

}
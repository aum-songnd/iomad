<?php
namespace local_th_last_accessed_module;

defined('MOODLE_INTERNAL') || die();

use context_course;

class th_last_accessed_module {

    public static function get_last_accessed_module(int $courseid, int $userid): array {
        global $DB;

        // ✅ Check course tồn tại
        $course = $DB->get_record('course', ['id' => $courseid]);

        if (!$course) {
            return [
                'status' => false,
            'isfirstaccess' => false,
                'message' => 'Course not found',
            ];
        }

        // ✅ Validate context
        $context = context_course::instance($courseid);
        self::validate_context($context);

        // ==============================
        // 🔍 LẤY MODULE GẦN NHẤT
        // ==============================
        $record = $DB->get_record_sql("
        SELECT contextinstanceid, timecreated, component, action, target
        FROM {logstore_standard_log}
        WHERE userid = :userid
          AND courseid = :courseid
          AND component LIKE :component
          AND (
                (action = 'viewed' AND target = 'course_module')
                OR
                (component = 'mod_scorm' AND action = 'launched')
              )
        ORDER BY timecreated DESC
    ", [
        'userid' => $userid,
        'courseid' => $courseid,
        'component' => 'mod_%',
    ]);    

        // ❗ Không có activity nào được mở
        if (!$record) {
            return [
                'status' => true,
                'isfirstaccess' => true,
                'message' => 'No activity found',
            ];
        }

        // ==============================
        // 🔗 MAP CMID → MODULE
        // ==============================
        $cmid = $record->contextinstanceid;

        $cm = get_coursemodule_from_id(
            null,
            $cmid,
            $courseid,
            false,
            IGNORE_MISSING
        );

        if (!$cm) {
            return [
                'status' => false,
                'isfirstaccess' => false,
                'message' => 'Course module not found',
            ];
        }

        $module = $DB->get_record(
            'modules',
            ['id' => $cm->module],
            '*',
            MUST_EXIST
        );

        $instance = $DB->get_record(
            $module->name,
            ['id' => $cm->instance]
        );

        $name = $instance->name ?? '';

        $url = (new \moodle_url(
            '/mod/' . $module->name . '/view.php',
            ['id' => $cm->id]
        ))->out(false);

        return [
            'status' => true,
            'isfirstaccess' => false,
            'data' => [
                'cmid' => $cm->id,
                'courseid' => $cm->course,
                'modname' => $module->name,
                'instance' => $cm->instance,
                'name' => $name,
                'url' => $url,
                'timecreated' => $record->timecreated,
            ]
        ];
    }

    protected static function validate_context($context) {
        \external_api::validate_context($context);
    }
}
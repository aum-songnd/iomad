<?php

namespace local_th_app_get_course_data;

defined('MOODLE_INTERNAL') || die();

use context_course;

class activity_service {

    public static function get_activity_id_numbers(int $courseid): array {

        global $DB;

        // ✅ Check course tồn tại.
        $course = $DB->get_record(
            'course',
            ['id' => $courseid]
        );

        if (!$course) {
            return [
                'status' => false,
                'message' => 'Course not found',
            ];
        }

        // ✅ Validate context.
        $context = context_course::instance($courseid);
        self::validate_context($context);

        require_login($course);

        // ✅ Load all activities nhanh bằng modinfo.
        $modinfo = get_fast_modinfo($course);

        $activities = [];

        $gradeitems = $DB->get_records(
            'grade_items',
            [
                'courseid' => $courseid,
            ]
        );
        
        $gradeMap = [];
        
        foreach ($gradeitems as $gi) {
        
            if (empty($gi->itemmodule) || empty($gi->iteminstance)) {
                continue;
            }
        
            $key = $gi->itemmodule . '_' . $gi->iteminstance;
        
            $gradeMap[$key] = $gi->idnumber ?? '';
        }
        
        foreach ($modinfo->get_cms() as $cm) {
        
            // ưu tiên course_modules.idnumber
            $idnumber = trim($cm->idnumber ?? '');
        
            // fallback sang grade_items.idnumber
            if (empty($idnumber)) {
        
                $key = $cm->modname . '_' . $cm->instance;
        
                $idnumber = trim($gradeMap[$key] ?? '');
            }
        
            $activities[] = [
                'cmid' => $cm->id,
                'courseid' => $courseid,
                'instance' => $cm->instance,
                'modname' => $cm->modname,
                'name' => $cm->name,
                'idnumber' => $idnumber,
                'sectionnum' => $cm->sectionnum,
                'visible' => (bool) $cm->visible,
            ];
        }

        return [
            'status' => true,
            'data' => $activities,
        ];
    }

    protected static function validate_context($context) {
        \external_api::validate_context($context);
    }
}

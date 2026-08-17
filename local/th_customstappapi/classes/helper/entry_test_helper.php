<?php
namespace local_th_customstappapi\helper;

defined('MOODLE_INTERNAL') || die();

class entry_test_helper {

    private static function get_courses_by_category_idnumber(string $configname): array {
        global $DB;

        $idnumber = get_config('local_th_customstappapi', $configname);
        if (empty($idnumber)) {
            return [];
        }

        $sql = "
            SELECT c.id
            FROM {course} c
            JOIN {course_categories} cc ON cc.id = c.category
            WHERE cc.idnumber = :idnumber
            AND c.visible = 1
            AND c.id <> 1
        ";

        return $DB->get_fieldset_sql($sql, ['idnumber' => $idnumber]);
    }

    public static function get_entry_test_courses(): array {
        return self::get_courses_by_category_idnumber('entrance_cate');
    }

    public static function get_trial_test_courses(): array {
        return self::get_courses_by_category_idnumber('trial_test_cate');
    }

}

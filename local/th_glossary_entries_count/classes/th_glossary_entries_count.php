<?php

namespace local_th_glossary_entries_count;

defined('MOODLE_INTERNAL') || die();


class th_glossary_entries_count{


    public static function get_course_glossary_entries_count(int $courseid): array {
        global $DB;
    
        $sql = "
            SELECT COUNT(ge.id)
            FROM {thglossary_entries} ge
            JOIN {thglossary} g ON g.id = ge.thglossaryid
            WHERE g.course = :courseid
              AND ge.approved = 1
        ";
    
        $count = $DB->count_records_sql($sql, ['courseid' => $courseid]);
    
        return [
            'totalentries' => $count,
        ];
    }
    
}

<?php

defined('MOODLE_INTERNAL') || die;

function xmldb_block_th_curriculum_completion_report_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025053000) {
        // Define table block_th_curriculum_courses to be created.
        $table = new xmldb_table('th_curriculum_courses');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid_unique', XMLDB_KEY_UNIQUE, ['courseid']);

        // Conditionally launch create table.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Insert default courses if they do not already exist.
        $shortnames = [
            'A1.01G', 'A2.02G', 'A3.03G', 'A4.04G',
            'B1.01', 'B1.02', 'B2.03', 'B2.04', 'B2.05', 'B2.06',
            'B3.07', 'B3.08', 'B3.09', 'B3.10',
            'C4.05', 'C4.06', 'C4.07', 'C4.08'
        ];
        $courses = $DB->get_records_list('course', 'shortname', $shortnames);
        $now = time();

        foreach ($courses as $course) {
            if (!$DB->record_exists('th_curriculum_courses', ['courseid' => $course->id])) {
                $DB->insert_record('th_curriculum_courses', [
                    'courseid' => $course->id,
                    'timemodified' => $now,
                ]);
            }
        }

        // Upgrade savepoint.
        upgrade_block_savepoint(true, 2025053000, 'th_curriculum_completion_report');
    }

    return true;
}

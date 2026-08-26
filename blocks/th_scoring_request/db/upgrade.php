<?php

defined('MOODLE_INTERNAL') || die;

function xmldb_block_th_scoring_request_upgrade($oldversion) {
    global $CFG, $DB;

    if ($oldversion < 2025010200) {
        
        $dbman = $DB->get_manager();
        $table = new xmldb_table('block_th_scoring_request');
        $field = new xmldb_field('time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status');
    
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }


        upgrade_block_savepoint(true, 2025010200, 'th_scoring_request');
    }

    return true;
}
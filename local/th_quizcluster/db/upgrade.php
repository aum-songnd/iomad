<?php
defined('MOODLE_INTERNAL') || die;

function xmldb_local_th_quizcluster_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026010600) {

        $table = new xmldb_table('local_th_quizcluster_cfg');
        $field = new xmldb_field('enabletagrandom', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'manualslotmarks');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026010600, 'local', 'th_quizcluster');
    }

    if ($oldversion < 2026031800) {

        $table = new xmldb_table('local_th_qc_attemptmk');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('questionusageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('slot', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('maxmark', XMLDB_TYPE_NUMBER, '12', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('attempt_slot_uniq', XMLDB_INDEX_UNIQUE, ['attemptid', 'slot']);
        $table->add_index('attemptid_idx', XMLDB_INDEX_NOTUNIQUE, ['attemptid']);
        $table->add_index('qubaid_idx', XMLDB_INDEX_NOTUNIQUE, ['questionusageid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026031800, 'local', 'th_quizcluster');
    }

    return true;
}
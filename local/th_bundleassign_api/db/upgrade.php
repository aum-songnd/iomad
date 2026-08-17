<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_th_bundleassign_api.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_th_bundleassign_api_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026041000) {
        $table = new xmldb_table('th_company');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('company_code', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lms_company_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('company_code_uix', XMLDB_INDEX_UNIQUE, ['company_code']);
        $table->add_index('lms_company_id_uix', XMLDB_INDEX_UNIQUE, ['lms_company_id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026041000, 'local', 'th_bundleassign_api');
    }

    if ($oldversion < 2026041502) {
        if ($DB->record_exists('external_services', ['shortname' => 'th_bundleassign_api'])) {
            $DB->set_field('external_services', 'downloadfiles', 1, ['shortname' => 'th_bundleassign_api']);
            $DB->set_field('external_services', 'uploadfiles', 1, ['shortname' => 'th_bundleassign_api']);
        }

        upgrade_plugin_savepoint(true, 2026041500, 'local', 'th_bundleassign_api');
    }

    return true;
}

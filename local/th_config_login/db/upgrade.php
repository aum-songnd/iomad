<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade steps for the plugintype_pluginname plugin.
 *
 * @package   plugintype_pluginname
 * @copyright Year, You Name <your@email.address>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_local_th_config_login_upgrade($oldversion = 0) {
	 global $DB;
	if ($oldversion < 2025040200) {
		$dbman = $DB->get_manager();

		if (!$dbman->table_exists('local_th_user_login')) {

	        // Define field id to be added to local_th_user_login.
	        $table = new xmldb_table('local_th_user_login');

	        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
	        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
	        $table->add_field('timerequested', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'userid');
	        // $table->add_field('timererequested', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timerequested');
	        $table->add_field('type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null, 'timererequested');
	        $table->add_field('otp', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, null, 'type');

	        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
	        $table->add_index('idx_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

	        $dbman->create_table($table);
	    }
    }

    if ($oldversion < 2025042304) {
		$dbman = $DB->get_manager();

		if ($dbman->table_exists('local_th_user_login')) {

			$table = new xmldb_table('local_th_user_login');
	        $field = new xmldb_field('code', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'otp');
	        if (!$dbman->field_exists($table, $field)) {
	            $dbman->add_field($table, $field);
	        }
	    }
	    unset_config('selectsecret', 'local_th_config_login');
		set_config('selectsecret', 'cloudflare,recaptcha,email', 'local_th_config_login');

		upgrade_plugin_savepoint(true, 2025042304, 'local', 'th_config_login');
    }

    if ($oldversion < 2025061100) {
		$dbman = $DB->get_manager();

		if ($dbman->table_exists('local_th_user_login')) {

			$table = new xmldb_table('local_th_user_login');
	        $field = new xmldb_field('typesend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'type');
	        if (!$dbman->field_exists($table, $field)) {
	            $dbman->add_field($table, $field);
	        }
	        $DB->delete_records('local_th_user_login');
	        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
	        if (!$dbman->field_exists($table, $field)) {
	            $dbman->change_field_type($table, $field);
	        }
	    }


	    // unset_config('selectsecret', 'local_th_config_login');
		// set_config('selectsecret', 'cloudflare,recaptcha,email', 'local_th_config_login');

		upgrade_plugin_savepoint(true, 2025061100, 'local', 'th_config_login');
    }

    if ($oldversion < 2026060903) {
		$dbman = $DB->get_manager();
		$table = new xmldb_table('local_th_user_login');
		$field = new xmldb_field('type');

		if ($dbman->field_exists($table, $field)) {
			$dbman->drop_field($table, $field);
		}
		upgrade_plugin_savepoint(true, 2026060903, 'local', 'th_config_login');
    }

    return true;
}
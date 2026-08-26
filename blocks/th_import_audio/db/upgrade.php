<?php

defined('MOODLE_INTERNAL') || die;

function xmldb_block_th_import_audio_upgrade($oldversion) {

  global $CFG, $DB;

  if ($oldversion < 2024062500) {

      $dbman = $DB->get_manager();
      $table = new xmldb_table('th_log_import_audio');
      $field = new xmldb_field('questionname', XMLDB_TYPE_CHAR, '500');
      if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
      }
  }
  return true;
}
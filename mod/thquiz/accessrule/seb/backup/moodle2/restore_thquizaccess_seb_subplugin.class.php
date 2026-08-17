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
 * Restore instructions for the seb (Safe Exam Browser) thquiz access subplugin.
 *
 * @package    thquizaccess_seb
 * @category   backup
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2020 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use thquizaccess_seb\thquiz_settings;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/thquiz/backup/moodle2/restore_mod_thquiz_access_subplugin.class.php');

/**
 * Restore instructions for the seb (Safe Exam Browser) thquiz access subplugin.
 *
 * @copyright  2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_thquizaccess_seb_subplugin extends restore_mod_thquiz_access_subplugin {

    /**
     * Provides path structure required to restore data for seb thquiz access plugin.
     *
     * @return array
     */
    protected function define_thquiz_subplugin_structure() {
        $paths = [];

        // Thquiz settings.
        $path = $this->get_pathfor('/thquizaccess_seb_settings'); // Subplugin root path.
        $paths[] = new restore_path_element('thquizaccess_seb_settings', $path);

        // Template settings.
        $path = $this->get_pathfor('/thquizaccess_seb_settings/thquizaccess_seb_template');
        $paths[] = new restore_path_element('thquizaccess_seb_template', $path);

        return $paths;
    }

    /**
     * Process the restored data for the thquizaccess_seb_settings table.
     *
     * @param stdClass $data Data for thquizaccess_seb_settings retrieved from backup xml.
     */
    public function process_thquizaccess_seb_settings($data) {
        global $DB, $USER;

        // Process thquizsettings.
        $data = (object) $data;
        $data->thquizid = $this->get_new_parentid('thquiz'); // Update thquizid with new reference.
        $data->cmid = $this->task->get_moduleid();

        unset($data->id);
        $data->timecreated = $data->timemodified = time();
        $data->usermodified = $USER->id;
        $DB->insert_record(thquizaccess_seb\thquiz_settings::TABLE, $data);

        // Process attached files.
        $this->add_related_files('thquizaccess_seb', 'filemanager_sebconfigfile', null);
    }

    /**
     * Process the restored data for the thquizaccess_seb_template table.
     *
     * @param stdClass $data Data for thquizaccess_seb_template retrieved from backup xml.
     */
    public function process_thquizaccess_seb_template($data) {
        global $DB;

        $data = (object) $data;

        $thquizid = $this->get_new_parentid('thquiz');

        $template = null;
        if ($this->task->is_samesite()) {
            $template = \thquizaccess_seb\template::get_record(['id' => $data->id]);
        } else {
            // In a different site, try to find existing template with the same name and content.
            $candidates = \thquizaccess_seb\template::get_records(['name' => $data->name]);
            foreach ($candidates as $candidate) {
                if ($candidate->get('content') == $data->content) {
                    $template = $candidate;
                    break;
                }
            }
        }

        if (empty($template)) {
            unset($data->id);
            $template = new \thquizaccess_seb\template(0, $data);
            $template->save();
        }

        // Update the restored thquiz settings to use restored template.
        $DB->set_field(\thquizaccess_seb\thquiz_settings::TABLE, 'templateid', $template->get('id'), ['thquizid' => $thquizid]);
    }

}


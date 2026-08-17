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
 * Restore code for qtype_thddwtos.
 *
 * @package   qtype_thddwtos
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Restore plugin class that provides the necessary information needed to restore one thddwtos qtype plugin.
 *
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_thddwtos_plugin extends restore_qtype_plugin {

    /**
     * Returns the paths to be handled by the plugin at question level.
     *
     * @return array
     */
    protected function define_question_plugin_structure() {

        $paths = array();

        // This qtype uses question_answers, add them.
        $this->add_question_question_answers($paths);

        // Add own qtype stuff.
        $elename = 'thddwtos';
        $elepath = $this->get_pathfor('/thddwtos'); // We used get_recommended_name() so this works.
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths.
    }

    /**
     * Process the qtype/thddwtos element.
     *
     * @param array|object $data thddwtos object to work with.
     */
    public function process_thddwtos($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, we need to create its question_thddwtos too.
        if ($questioncreated) {
            // Adjust some columns.
            $data->questionid = $newquestionid;
            // Insert record.
            $newitemid = $DB->insert_record('question_thddwtos', $data);
            // Create mapping (needed for decoding links).
            $this->set_mapping('question_thddwtos', $oldid, $newitemid);
        }
    }

    /**
     * Return the contents of this qtype to be processed by the links decoder.
     *
     * @return array
     */
    public static function define_decode_contents() {

        $contents = array();

        $fields = array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback');
        $contents[] = new restore_decode_content('question_thddwtos', $fields, 'question_thddwtos');

        return $contents;
    }
}

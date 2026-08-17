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
 * Restore code for qtype_ddwtos.
 *
 * @package   qtype_ddwtos
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Restore plugin class that provides the necessary information needed to restore one ddwtos qtype plugin.
 *
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_thvstepcluster_plugin extends restore_qtype_plugin {
    public $cluster = array();

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
        $elename = 'thvstepcluster';
        $elepath = $this->get_pathfor('/thvstepcluster'); // We used get_recommended_name() so this works.
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths.
    }

    /**
     * Process the qtype/ddwtos element.
     *
     * @param array|object $data ddwtos object to work with.
     */
    public function process_thvstepcluster($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;
        // $questioncreated = true;

        // If the question has been created by restore, we need to create its question_ddwtos too.
        if ($questioncreated) {
            // Adjust some columns.
            $data->question = $newquestionid;
            // Insert record.
            $newitemid = $DB->insert_record('qtype_thvstepcluster', $data);
            // Create mapping (needed for decoding links).
            $this->set_mapping('qtype_thvstepcluster', $oldid, $newitemid);

            $sql = "SELECT a.courseid
                    FROM {question_versions} qv
                    JOIN {question} q ON q.id = qv.questionid
                    JOIN (SELECT qbe.id,ctx.instanceid as courseid 
                        FROM {question_categories} qc
                        JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
                        JOIN {context} ctx ON ctx.id = qc.contextid
                        WHERE ctx.contextlevel = :contextlevel) a ON a.id = qv.questionbankentryid 
                    WHERE q.id = :id
                    LIMIT 1";
                $params = array('id' => $newquestionid, 'contextlevel' => CONTEXT_COURSE);
                $courseid = $DB->get_field_sql($sql, $params);
            $this->cluster[] = array('courseid' => $courseid, 'questionid' => $newitemid);

        }
    }

    public function after_execute_question() {
        global $DB;

        foreach ($this->cluster as $key => $record) {
            $event = \qtype_thvstepcluster\event\cluster_created::create(array('context' => context_course::instance($record['courseid']), 'objectid' => $record['questionid'], 'other' => ''));
            $event->trigger();
        }
    }

    // /**
    //  * Return the contents of this qtype to be processed by the links decoder.
    //  *
    //  * @return array
    //  */
    // public static function define_decode_contents() {

    //     $contents = array();

    //     $fields = array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback');
    //     $contents[] = new restore_decode_content('question_ddwtos', $fields, 'question_ddwtos');

    //     return $contents;
    // }
}

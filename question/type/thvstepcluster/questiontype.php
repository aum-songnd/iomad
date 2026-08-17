<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Question type class for thvstepcluster is defined here.
 *
 * @package     qtype_thvstepcluster
 * @copyright   phamleminh1812@gmail.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir . '/questionlib.php';

/**
 * Class that represents a thvstepcluster question type.
 *
 * The class loads, saves and deletes questions of the type thvstepcluster
 * to and from the database and provides methods to help with editing questions
 * of this type. It can also provide the implementation for import and export
 * in various formats.
 */
class qtype_thvstepcluster extends question_type {

	// Override functions as necessary from the parent class located at
	// /question/type/questiontype.php.

	public function save_question_options($questionform) {
		// mform_element

		global $DB, $COURSE;
		$th_question = trim(get_config('qtype_thvstepcluster', 'th_question'));
		$question_entry_items = $questionform->question_entry_items;
		$questionids = [];

		foreach ($question_entry_items as $key => $value) {
			$idname = "question_entry_version$value";
			$sequencename = "sequence$value";

			$a = [];
			$a['questionid'] = $questionform->$idname;
			$a['sequence'] = $questionform->$sequencename;

			$questionids[$value] = $a;
		}

		uasort($questionids, function ($a, $b) {
			$sequence_a = $a['sequence'];
			$sequence_b = $b['sequence'];
			if ($sequence_a == $sequence_b) {
				return 0;
			}
			return $sequence_a > $sequence_b ? 1 : -1;
		});

		$question_entry_items = array_keys($questionids);

		$question_items = [];
		$question_items["question_entry_items"] = $question_entry_items;
		$question_items["questionids"] = $questionids;

		$question_options = $DB->get_record('qtype_thvstepcluster', $arrayName = array('question' => $questionform->id));

		if ($question_options) {
			
		} else {
			$question_options = new stdClass();
			$question_options->question = $questionform->id;
			$question_options->question_entries_id = json_encode($question_items);

			$id = $DB->insert_record('qtype_thvstepcluster', $question_options);
			$event = \qtype_thvstepcluster\event\cluster_created::create(array('context' => context_course::instance($COURSE->id), 'objectid' => $id, 'other' => 'ZZZ'));
			$event->trigger();
		}

		return true;
	}

	public function get_question_options($question) {

		global $DB, $USER;
		//TODO
		parent::get_question_options($question);

		$question_items = [];
		$question_entries_id = $DB->get_field('qtype_thvstepcluster', 'question_entries_id', ['question' => $question->id]);
		if ($question_entries_id) {
			$question_items = json_decode($question_entries_id, true);
		}

		$questionids = [];
		if (array_key_exists("question_entry_items", $question_items)) {
			$question->question_entry_items = $question_items["question_entry_items"];
			$questionids = $question_items["questionids"];
		} else {
			$question->question_entry_items = [];
			$questionids = [];
		}

		$question_items = [];

		foreach ($questionids as $key => $value) {
			$questionidname = "question_entry_version$key";
			$sequencename = "sequence$key";

			$question->$questionidname = $value['questionid'];
			$question->$sequencename = $value['sequence'];
			$question_items[] = $value;
		}

		$question->question_items = $question_items;
		return true;
	}

	protected function initialise_question_instance(question_definition $question, $questiondata) {
		global $USER, $DB;

		parent::initialise_question_instance($question, $questiondata);

		$question_items = $question->question_items = $questiondata->question_items;

		$question_items_instance = [];
		foreach ($question_items as $key => $value) {
			if ($value === null) {
				continue;
			}
			$questionitemid = $value['questionid'];
			if ($DB->record_exists('question', array('id' => $questionitemid))) {
				$questionitem = question_bank::load_question($questionitemid);

			}
			$question_items_instance[] = $questionitem;
		}

		$question->question_items_instance = $question_items_instance;

	}

	public function save_question($question, $form) {
		$question_entry_items = $form->question_entry_items;
		$defaultmark = 0;
		foreach ($question_entry_items as $key => $entryid) {

			$keyname = "question_entry_version$entryid";
			$question_item_id = $form->$keyname;

			$questionitem = question_bank::load_question($question_item_id);
			$defaultmark += $questionitem->defaultmark;
		}
		$form->defaultmark = $defaultmark;

		return parent::save_question($question, $form);
	}

	public function move_files($questionid, $oldcontextid, $newcontextid) {
		parent::move_files($questionid, $oldcontextid, $newcontextid);
		$this->move_files_in_answers($questionid, $oldcontextid, $newcontextid, true);
		$this->move_files_in_combined_feedback($questionid, $oldcontextid, $newcontextid);
		$this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
	}

	protected function delete_files($questionid, $contextid) {
		parent::delete_files($questionid, $contextid);
		$this->delete_files_in_answers($questionid, $contextid, true);
		$this->delete_files_in_combined_feedback($questionid, $contextid);
		$this->delete_files_in_hints($questionid, $contextid);
	}

	public function delete_question($questionid, $contextid) {
        global $DB;
        $DB->delete_records('qtype_thvstepcluster', array('question' => $questionid));

        parent::delete_question($questionid, $contextid);
    }

    public function export_to_xml($question, qformat_xml $format, $extra = null): string {

		global $DB, $COURSE;
		
		$question_entry_items = $question->question_entry_items;
		$questionids = [];

		foreach ($question_entry_items as $key => $value) {
			$idname = "question_entry_version$value";
			$sequencename = "sequence$value";

			$a = [];
			$a['questionid'] = $question->$idname;
			$a['sequence'] = $question->$sequencename;

			$questionids[$value] = $a;
		}

		uasort($questionids, function ($a, $b) {
			$sequence_a = $a['sequence'];
			$sequence_b = $b['sequence'];
			if ($sequence_a == $sequence_b) {
				return 0;
			}
			return $sequence_a > $sequence_b ? 1 : -1;
		});

		$question_entry_items = array_keys($questionids);

		$question_items = [];
		$question_items["question_entry_items"] = $question_entry_items;
		$question_items["questionids"] = $questionids;

		$question_options = $DB->get_record('qtype_thvstepcluster', array('question' => $question->id));

        $output = '    <question_entries_id>' . json_encode($question_items) . "</question_entries_id>\n";
        $output .= $format->write_answers($question->options->answers);
        return $output;
    }

    public function import_from_xml($data, $question, qformat_xml $format, $extra = null) {
    	global $COURSE, $DB;
        $questiontype = $data['@']['type'];
        if ($questiontype != $this->name()) {
            return false;
        }
   
        $qo = $format->import_headers($data);
        $qo->qtype = $questiontype;
        $qo->question_entry_items = json_decode($data['#']['question_entries_id'][0]['#']);

        // Load any answers and simulate the corresponding form data.
        if (isset($data['#']['question_entries_id'])) {
            $abc = json_decode($data['#']['question_entries_id'][0]['#']);
            
            $qo->question_entry_items = (array) $abc->question_entry_items;
            foreach ((array) $abc->questionids as $key => $value) {

				$idname = "question_entry_version".$key;
				$sequencename = "sequence".$key;
        		$qo->$idname = $value->questionid;
    			$qo->$sequencename = $value->sequence;
            }
        }

         // Load any answers and simulate the corresponding form data.
        if (isset($data['#']['answer'])) {
            foreach ($data['#']['answer'] as $answer) {
                $ans = $format->import_answer($answer);
                $fieldname = 'feedbackfor' . $ans->answer['text'];
                $qo->$fieldname = $ans->feedback;
            }
        }
        return $qo;
    }
}

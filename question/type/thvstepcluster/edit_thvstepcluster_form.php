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
 * The editing form for thvstepcluster question type is defined here.
 *
 * @package     qtype_thvstepcluster
 * @copyright   phamleminh1812@gmail.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once $CFG->libdir . '/form/text.php';
defined('MOODLE_INTERNAL') || die();

/**
 * thvstepcluster question editing form defition.
 *
 * You should override functions as necessary from the parent class located at
 * /question/type/edit_question_form.php.
 */

class custom_text_input extends \MoodleQuickForm_text {
	/**
	 * Constructor
	 */
	public function __construct($elementName = null, $elementLabel = null, $options = null, $attributes = null) {
		parent::__construct($elementName, $elementLabel, $options, $attributes);

		$this->setType("custom_text_input");
	}

	/**
	 * Returns HTML for the form element
	 *
	 * @return string
	 */
	function toHtml() {

		$html = 'Custom text';
		$html .= parent::toHtml();

		return $html;
	}

}

class qtype_thvstepcluster_edit_form extends question_edit_form {

	protected function definition_inner($mform) {

		global $DB, $CFG, $COURSE, $PAGE;

       	$mform = $this->_form;
		$mform->setAttributes(array('id' => 'qtype_thvstepcluster-overview-form') + $mform->getAttributes());

		$options = array(
			'multiple' => true,
			'noselectionstring' => get_string('no_selection', 'qtype_thvstepcluster'),
		);

		$sql = "select a.questionbankentryid, a.* , c.*
				from {question_versions} as a
				inner join
				(
				select *, max(version) as maxversion
				from {question_versions}
				group by questionbankentryid
				order by version desc) b
				on a.questionbankentryid = b.questionbankentryid and a.version = b.maxversion
				inner join {question} as c
				on a.questionid = c.id
				inner join (select distinct qbe.*,qc.id as qid from {question_categories} qc
					join {question_bank_entries} qbe 
					on qbe.questioncategoryid = qc.id
                    join {context} as ctx 
                    on ctx.id = qc.contextid
                    where ctx.instanceid = $COURSE->id and ctx.contextlevel = 50) as d
                   on d.id = a.questionbankentryid
                where a.status = 'ready' and c.parent = 0 and c.qtype in ('recordrtc', 'essay', 'multichoice', 'description', 'truefalse', 'shortanswer', 'multianswer', 'match', 'gapselect', 'ddwtos', 'thddwtos')
                ";

		$records = $DB->get_records_sql($sql);

		$question_ids = [];

		foreach ($records as $key => $value) {
			$question_name = $value->name;
			$question_text = $value->questiontext;
			$value->text = substr($question_name . "\n" . $question_text, 0, 250);
			$question_ids[$value->questionbankentryid] = strip_tags($value->text);
		}
		// $mform->addElement('autocomplete', 'question_entry_items', get_string('question_item', 'qtype_thvstepcluster'), $question_ids, $options);
		$mform->addElement('hidden', 'search');
        $mform->setType('search', PARAM_TEXT);
		
		$attributes = array('multiple' => 'multiple', 'size' => 15, 'data-search' => 'question_entry_items', 'style' => 'max-width: 799px;');
		$select = $mform->addElement('select', 'question_entry_items', get_string('question_item', 'qtype_thvstepcluster'), $question_ids, $attributes);
		$mform->setType('question_entry_items', PARAM_TEXT);
		$mform->addRule('question_entry_items', null, 'required', null, 'client');

		$PAGE->requires->yui_module(
    		'moodle-qtype_thvstepcluster-search',
    		'M.qtype_thvstepcluster.init_capability_search',
    		array(array('strsearch' => get_string('search')))
		);

		$mform->addElement('submit', 'showselectversionform', get_string('update_form', 'qtype_thvstepcluster'));
		$mform->registerNoSubmitButton('showselectversionform');

		$showselectversionform = optional_param('showselectversionform', false, PARAM_BOOL);
		$question_entry_items = optional_param_array('question_entry_items', [], PARAM_INT);

		if ($showselectversionform || $question_entry_items ||
			isset($this->question->question_entry_items)) {

			if (!$question_entry_items) {
				$question_entry_items = $this->question->question_entry_items;
			} else {
				$prev = isset($this->question->question_entry_items) ? $this->question->question_entry_items : [];
				$intersect = array_intersect($prev, $question_entry_items);
				$diff = array_diff($question_entry_items, $intersect);
				$question_entry_items = array_merge($intersect, $diff);
			}

			$mform->addElement('header', "ABC", "DRAG TO CHANGE ORDER");
			$mform->setExpanded("ABC");

			foreach ($question_entry_items as $key => $question_entry_id) {
				if (isset($records[$question_entry_id])) {
					$record = $records[$question_entry_id];

					$questionid = $record->questionid;
					$versions = question_bank::get_all_versions_of_question($questionid);
					if ($versions) {
						$headername = "header$question_entry_id";
						$textstring = strip_tags(substr($record->text, 0, 200));

						$mform->addElement('html', "<div class='ABCDEF'>");
						$mform->addElement('html', "<h1>$textstring</h1>");

						// $mform->addElement('header', $headername, $textstring);
						// $mform->setExpanded($headername);

						$verops = [];
						foreach ($versions as $verid => $version) {

							$qid = $version->questionid;
							$vernumber = $version->version;

							$ver_question = question_bank::load_question($qid);
							$verops[$qid] = "Version $vernumber - $ver_question->questiontext";
						}

						$mform->addElement('select', "question_entry_version$question_entry_id", get_string('select_version', 'qtype_thvstepcluster'), $verops);
						$mform->addElement('text', "sequence$question_entry_id", get_string('sequence', 'qtype_thvstepcluster'));
						$mform->setType("sequence$question_entry_id", PARAM_INT);

						$mform->addElement('html', "</div>");
					}
				}
			}
		}
		$PAGE->requires->js('/question/type/thvstepcluster/amd/src/draganddrop.js', true);
	}
	// moodleform
	/**
	 * Returns the question type name.
	 *
	 * @return string The question type name.
	 */
	public function qtype() {
		return 'thvstepcluster';
	}

	public $question = null;

	public function data_preprocessing($question) {

		$this->question = $question;
		$question = parent::data_preprocessing($question);
		return $question;
	}

	public function validation($data, $files) {

		$question_entry_items = $data["question_entry_items"];

		$result = [];

		foreach ($question_entry_items as $key => $value) {
			$keyname = "question_entry_version$value";
			if (isset($data[$keyname]) && $data[$keyname] > 0) {

			} else {
				$result["question_entry_items"] = "something differ, need to select question version";

				$result[$keyname] = "Are you want to select this version?";
			}
		}

		return $result;
	}

	private function update_record(){

	}
}

<?php

// This file is part of the Certificate module for Moodle - http://moodle.org/
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
 * This page lists all the instances of certificate in a particular course
 *
 * @package    mod
 * @subpackage certificate
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_thvstepcluster\event;

defined('MOODLE_INTERNAL') || die();

class cluster_created extends \core\event\base {
	protected function init() {
		$this->data['crud'] = 'c';
		$this->data['edulevel'] = self::LEVEL_OTHER;
		$this->data['objecttable'] = 'qtype_thvstepcluster';
	}

	public static function get_name() {
		return get_string('pluginname', 'qtype_thvstepcluster');
	}

	/**
	 * Returns non-localised event description with id's for admin use only.
	 *
	 * @return string
	 */
	public function get_description() {

		$other = json_encode($this->other);

		return "The user with id '$this->userid' has created question with id '$this->objectid' in " .
			"course with id '$this->courseid'.<br>
			<b>Other:</b> $other";
	}

	/**
	 * Get URL related to the action.
	 *
	 * @return \moodle_url
	 */
	public function get_url() {
		// Entry does not exist any more, returning link to the module view page in the mode it was before deleting entry.
		$params = array('courseid' => $this->courseid, 'id' => $this->objectid);
		// if (isset($this->other['hook'])) {
		// 	$params['hook'] = $this->other['hook'];
		// }
		// if (isset($this->other['mode'])) {
		// 	$params['mode'] = $this->other['mode'];
		// }
		return new \moodle_url("question/bank/editquestion/question.php", $params);
	}
}
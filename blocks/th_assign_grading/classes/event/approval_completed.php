<?php
namespace block_th_assign_grading\event;

defined('MOODLE_INTERNAL') || die();

use core\event\base;

class approval_completed extends base {

    public static function get_name() {
        return get_string('eventapprovalcompleted', 'block_th_assign_grading');
    }

    public function get_description() {
        return "User with id '{$this->userid}' approved {$this->other['approved_count']} row(s) via {$this->other['mode']} mode.";
    }

    public function get_url() {
        return new \moodle_url('/blocks/th_assign_grading/view.php');
    }

    protected function init() {
        $this->data['crud'] = 'u'; // update
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'block_th_assign_grading'; 
    }

    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['approved_count'])) {
            throw new \coding_exception('The "approved_count" value must be set in other.');
        }
        if (!isset($this->other['approved_ids']) || !is_array($this->other['approved_ids'])) {
            throw new \coding_exception('The "approved_ids" array must be set in other.');
        }
        if (empty($this->other['mode'])) {
            throw new \coding_exception('The "mode" (bulk|single) must be set in other.');
        }
    }
}

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

namespace mod_thquiz\local\structure;

/**
 * Class slot_random, represents a random question slot type.
 *
 * @package    mod_thquiz
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @author     2021 Safat Shahin <safatshahin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slot_random {

    /** @var \stdClass Slot's properties. A record retrieved from the thquiz_slots table. */
    protected $record;

    /**
     * @var \stdClass set reference record
     */
    protected $referencerecord;

    /**
     * @var \stdClass The thquiz this question slot belongs to.
     */
    protected $thquiz = null;

    /**
     * @var \core_tag_tag[] List of tags for this slot.
     */
    protected $tags = [];

    /**
     * @var string filter condition
     */
    protected $filtercondition = null;

    /**
     * slot_random constructor.
     *
     * @param \stdClass $slotrecord Represents a record in the thquiz_slots table.
     */
    public function __construct($slotrecord = null) {
        $this->record = new \stdClass();
        $this->referencerecord = new \stdClass();

        $slotproperties = ['id', 'slot', 'thquizid', 'page', 'requireprevious', 'maxmark'];
        $setreferenceproperties = ['usingcontextid', 'questionscontextid'];

        foreach ($slotproperties as $property) {
            if (isset($slotrecord->$property)) {
                $this->record->$property = $slotrecord->$property;
            }
        }

        foreach ($setreferenceproperties as $referenceproperty) {
            if (isset($slotrecord->$referenceproperty)) {
                $this->referencerecord->$referenceproperty = $slotrecord->$referenceproperty;
            }
        }
    }

    /**
     * Returns the thquiz for this question slot.
     * The thquiz is fetched the first time it is requested and then stored in a member variable to be returned each subsequent time.
     *
     * @return mixed
     * @throws \coding_exception
     */
    public function get_thquiz() {
        global $DB;

        if (empty($this->thquiz)) {
            if (empty($this->record->thquizid)) {
                throw new \coding_exception('thquizid is not set.');
            }
            $this->thquiz = $DB->get_record('thquiz', array('id' => $this->record->thquizid));
        }

        return $this->thquiz;
    }

    /**
     * Sets the thquiz object for the thquiz slot.
     * It is not mandatory to set the thquiz as the thquiz slot can fetch it the first time it is accessed,
     * however it helps with the performance to set the thquiz if you already have it.
     *
     * @param \stdClass $thquiz The qui object.
     */
    public function set_thquiz($thquiz) {
        $this->thquiz = $thquiz;
        $this->record->thquizid = $thquiz->id;
    }

    /**
     * Set some tags for this thquiz slot.
     *
     * @param \core_tag_tag[] $tags
     */
    public function set_tags($tags) {
        $this->tags = [];
        foreach ($tags as $tag) {
            // We use $tag->id as the key for the array so not only it handles duplicates of the same tag being given,
            // but also it is consistent with the behaviour of set_tags_by_id() below.
            $this->tags[$tag->id] = $tag;
        }
    }

    /**
     * Set some tags for this thquiz slot. This function uses tag ids to find tags.
     *
     * @param int[] $tagids
     */
    public function set_tags_by_id($tagids) {
        $this->tags = \core_tag_tag::get_bulk($tagids, 'id, name');
    }

    /**
     * Set filter condition.
     *
     * @param \stdClass $filters
     */
    public function set_filter_condition($filters) {
        if (!empty($this->tags)) {
            $filters->tags = $this->tags;
        }

        $this->filtercondition = json_encode($filters);
    }

    /**
     * Inserts the thquiz slot at the $page page.
     * It is required to call this function if you are building a thquiz slot object from scratch.
     *
     * @param int $page The page that this slot will be inserted at.
     */
    public function insert($page) {
        global $DB;

        $slots = $DB->get_records('thquiz_slots', array('thquizid' => $this->record->thquizid),
                'slot', 'id, slot, page');
        $thquiz = $this->get_thquiz();

        $trans = $DB->start_delegated_transaction();

        $maxpage = 1;
        $numonlastpage = 0;
        foreach ($slots as $slot) {
            if ($slot->page > $maxpage) {
                $maxpage = $slot->page;
                $numonlastpage = 1;
            } else {
                $numonlastpage += 1;
            }
        }

        if (is_int($page) && $page >= 1) {
            // Adding on a given page.
            $lastslotbefore = 0;
            foreach (array_reverse($slots) as $otherslot) {
                if ($otherslot->page > $page) {
                    $DB->set_field('thquiz_slots', 'slot', $otherslot->slot + 1, array('id' => $otherslot->id));
                } else {
                    $lastslotbefore = $otherslot->slot;
                    break;
                }
            }
            $this->record->slot = $lastslotbefore + 1;
            $this->record->page = min($page, $maxpage + 1);

            thquiz_update_section_firstslots($this->record->thquizid, 1, max($lastslotbefore, 1));
        } else {
            $lastslot = end($slots);
            if ($lastslot) {
                $this->record->slot = $lastslot->slot + 1;
            } else {
                $this->record->slot = 1;
            }
            if ($thquiz->questionsperpage && $numonlastpage >= $thquiz->questionsperpage) {
                $this->record->page = $maxpage + 1;
            } else {
                $this->record->page = $maxpage;
            }
        }

        $this->record->id = $DB->insert_record('thquiz_slots', $this->record);

        $this->referencerecord->component = 'mod_thquiz';
        $this->referencerecord->questionarea = 'slot';
        $this->referencerecord->itemid = $this->record->id;
        $this->referencerecord->filtercondition = $this->filtercondition;
        $DB->insert_record('question_set_references', $this->referencerecord);

        $trans->allow_commit();

        // Log slot created event.
        $cm = get_coursemodule_from_instance('thquiz', $thquiz->id);
        $event = \mod_thquiz\event\slot_created::create([
            'context' => \context_module::instance($cm->id),
            'objectid' => $this->record->id,
            'other' => [
                'thquizid' => $thquiz->id,
                'slotnumber' => $this->record->slot,
                'page' => $this->record->page
            ]
        ]);
        $event->trigger();
    }
}

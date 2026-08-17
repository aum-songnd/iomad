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

namespace mod_thquiz;

/**
 * PHPUnit data generator testcase
 *
 * @package    mod_thquiz
 * @category   phpunit
 * @copyright  2012 Matt Petro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_thquiz_generator
 */
class generator_test extends \advanced_testcase {
    public function test_generator() {
        global $DB, $SITE;

        $this->resetAfterTest(true);

        $this->assertEquals(0, $DB->count_records('thquiz'));

        /** @var \mod_thquiz_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_thquiz');
        $this->assertInstanceOf('mod_thquiz_generator', $generator);
        $this->assertEquals('thquiz', $generator->get_modulename());

        $generator->create_instance(array('course'=>$SITE->id));
        $generator->create_instance(array('course'=>$SITE->id));
        $createtime = time();
        $thquiz = $generator->create_instance(array('course' => $SITE->id, 'timecreated' => 0));
        $this->assertEquals(3, $DB->count_records('thquiz'));

        $cm = get_coursemodule_from_instance('thquiz', $thquiz->id);
        $this->assertEquals($thquiz->id, $cm->instance);
        $this->assertEquals('thquiz', $cm->modname);
        $this->assertEquals($SITE->id, $cm->course);

        $context = \context_module::instance($cm->id);
        $this->assertEquals($thquiz->cmid, $context->instanceid);

        $this->assertEqualsWithDelta($createtime,
                $DB->get_field('thquiz', 'timecreated', ['id' => $cm->instance]), 2);
    }

    public function test_generating_a_user_override() {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $thquiz = $generator->create_module('thquiz', ['course' => $course->id]);
        $generator->enrol_user($user->id, $course->id, 'student');

        /** @var \mod_thquiz_generator $thquizgenerator */
        $thquizgenerator = $generator->get_plugin_generator('mod_thquiz');
        $thquizgenerator->create_override([
            'thquiz' => $thquiz->id,
            'userid' => $user->id,
            'timeclose' => strtotime('2022-10-20'),
        ]);

        // Check the corresponding calendar event now exists.
        $events = calendar_get_events(strtotime('2022-01-01'),
                strtotime('2022-12-31'), $user->id, false, $course->id);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($user->id, $event->userid);
        $this->assertEquals(0, $event->groupid);
        $this->assertEquals(0, $event->courseid);
        $this->assertEquals('thquiz', $event->modulename);
        $this->assertEquals($thquiz->id, $event->instance);
        $this->assertEquals('close', $event->eventtype);
        $this->assertEquals(strtotime('2022-10-20'), $event->timestart);
    }

    public function test_generating_a_group_override() {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $thquiz = $generator->create_module('thquiz', ['course' => $course->id]);
        $group = $generator->create_group(['courseid' => $course->id]);

        /** @var \mod_thquiz_generator $thquizgenerator */
        $thquizgenerator = $generator->get_plugin_generator('mod_thquiz');
        $thquizgenerator->create_override([
            'thquiz' => $thquiz->id,
            'groupid' => $group->id,
            'timeclose' => strtotime('2022-10-20'),
        ]);

        // Check the corresponding calendar event now exists.
        $events = calendar_get_events(strtotime('2022-01-01'),
                strtotime('2022-12-31'), false, $group->id, $course->id);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals(0, $event->userid);
        $this->assertEquals($group->id, $event->groupid);
        $this->assertEquals($course->id, $event->courseid);
        $this->assertEquals('thquiz', $event->modulename);
        $this->assertEquals($thquiz->id, $event->instance);
        $this->assertEquals('close', $event->eventtype);
        $this->assertEquals(strtotime('2022-10-20'), $event->timestart);
    }
}

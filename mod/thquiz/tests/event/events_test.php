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
 * Thquiz events tests.
 *
 * @package    mod_thquiz
 * @category   phpunit
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_thquiz\event;

use thquiz;
use thquiz_attempt;
use context_module;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/thquiz/attemptlib.php');

/**
 * Unit tests for thquiz events.
 *
 * @package    mod_thquiz
 * @category   phpunit
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class events_test extends \advanced_testcase {

    /**
     * Setup a thquiz.
     *
     * @return thquiz the generated thquiz.
     */
    protected function prepare_thquiz() {

        $this->resetAfterTest(true);

        // Create a course
        $course = $this->getDataGenerator()->create_course();

        // Make a thquiz.
        $thquizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_thquiz');

        $thquiz = $thquizgenerator->create_instance(array('course' => $course->id, 'questionsperpage' => 0,
                'grade' => 100.0, 'sumgrades' => 2));

        $cm = get_coursemodule_from_instance('thquiz', $thquiz->id, $course->id);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));

        // Add them to the thquiz.
        thquiz_add_thquiz_question($saq->id, $thquiz);
        thquiz_add_thquiz_question($numq->id, $thquiz);

        // Make a user to do the thquiz.
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        return thquiz::create($thquiz->id, $user1->id);
    }

    /**
     * Setup a thquiz attempt at the thquiz created by {@link prepare_thquiz()}.
     *
     * @param thquiz $thquizobj the generated thquiz.
     * @param bool $ispreview Make the attempt a preview attempt when true.
     * @return array with three elements, array($thquizobj, $quba, $attempt)
     */
    protected function prepare_thquiz_attempt($thquizobj, $ispreview = false) {
        // Start the attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_thquiz', $thquizobj->get_context());
        $quba->set_preferred_behaviour($thquizobj->get_thquiz()->preferredbehaviour);

        $timenow = time();
        $attempt = thquiz_create_attempt($thquizobj, 1, false, $timenow, $ispreview);
        thquiz_start_new_attempt($thquizobj, $quba, $attempt, 1, $timenow);
        thquiz_attempt_save_started($thquizobj, $quba, $attempt);

        return array($thquizobj, $quba, $attempt);
    }

    /**
     * Setup some convenience test data with a single attempt.
     *
     * @param bool $ispreview Make the attempt a preview attempt when true.
     * @return array with three elements, array($thquizobj, $quba, $attempt)
     */
    protected function prepare_thquiz_data($ispreview = false) {
        $thquizobj = $this->prepare_thquiz();
        return $this->prepare_thquiz_attempt($thquizobj, $ispreview);
    }

    public function test_attempt_submitted() {

        list($thquizobj, $quba, $attempt) = $this->prepare_thquiz_data();
        $attemptobj = thquiz_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();

        $timefinish = time();
        $attemptobj->process_finish($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(3, $events);
        $event = $events[2];
        $this->assertInstanceOf('\mod_thquiz\event\attempt_submitted', $event);
        $this->assertEquals('thquiz_attempts', $event->objecttable);
        $this->assertEquals($thquizobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertEquals(null, $event->other['submitterid']); // Should be the user, but PHP Unit complains...
        $this->assertEquals('thquiz_attempt_submitted', $event->get_legacy_eventname());
        $legacydata = new \stdClass();
        $legacydata->component = 'mod_thquiz';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $thquizobj->get_cmid();
        $legacydata->courseid = $thquizobj->get_courseid();
        $legacydata->thquizid = $thquizobj->get_thquizid();
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $legacydata->submitterid = null;
        $legacydata->timefinish = $timefinish;
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_becameoverdue() {

        list($thquizobj, $quba, $attempt) = $this->prepare_thquiz_data();
        $attemptobj = thquiz_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();
        $timefinish = time();
        $attemptobj->process_going_overdue($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\mod_thquiz\event\attempt_becameoverdue', $event);
        $this->assertEquals('thquiz_attempts', $event->objecttable);
        $this->assertEquals($thquizobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertNotEmpty($event->get_description());
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $this->assertEquals(null, $event->other['submitterid']);
        $this->assertEquals('thquiz_attempt_overdue', $event->get_legacy_eventname());
        $legacydata = new \stdClass();
        $legacydata->component = 'mod_thquiz';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $thquizobj->get_cmid();
        $legacydata->courseid = $thquizobj->get_courseid();
        $legacydata->thquizid = $thquizobj->get_thquizid();
        $legacydata->submitterid = null; // Should be the user, but PHP Unit complains...
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_abandoned() {

        list($thquizobj, $quba, $attempt) = $this->prepare_thquiz_data();
        $attemptobj = thquiz_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();
        $timefinish = time();
        $attemptobj->process_abandon($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\mod_thquiz\event\attempt_abandoned', $event);
        $this->assertEquals('thquiz_attempts', $event->objecttable);
        $this->assertEquals($thquizobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $this->assertEquals(null, $event->other['submitterid']);
        $this->assertEquals('thquiz_attempt_abandoned', $event->get_legacy_eventname());
        $legacydata = new \stdClass();
        $legacydata->component = 'mod_thquiz';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $thquizobj->get_cmid();
        $legacydata->courseid = $thquizobj->get_courseid();
        $legacydata->thquizid = $thquizobj->get_thquizid();
        $legacydata->submitterid = null; // Should be the user, but PHP Unit complains...
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_started() {
        $thquizobj = $this->prepare_thquiz();

        $quba = \question_engine::make_questions_usage_by_activity('mod_thquiz', $thquizobj->get_context());
        $quba->set_preferred_behaviour($thquizobj->get_thquiz()->preferredbehaviour);

        $timenow = time();
        $attempt = thquiz_create_attempt($thquizobj, 1, false, $timenow);
        thquiz_start_new_attempt($thquizobj, $quba, $attempt, 1, $timenow);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        thquiz_attempt_save_started($thquizobj, $quba, $attempt);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\attempt_started', $event);
        $this->assertEquals('thquiz_attempts', $event->objecttable);
        $this->assertEquals($attempt->id, $event->objectid);
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertEquals($thquizobj->get_context(), $event->get_context());
        $this->assertEquals('thquiz_attempt_started', $event->get_legacy_eventname());
        $this->assertEquals(\context_module::instance($thquizobj->get_cmid()), $event->get_context());
        // Check legacy log data.
        $expected = array($thquizobj->get_courseid(), 'thquiz', 'attempt', 'review.php?attempt=' . $attempt->id,
            $thquizobj->get_thquizid(), $thquizobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        // Check legacy event data.
        $legacydata = new \stdClass();
        $legacydata->component = 'mod_thquiz';
        $legacydata->attemptid = $attempt->id;
        $legacydata->timestart = $attempt->timestart;
        $legacydata->timestamp = $attempt->timestart;
        $legacydata->userid = $attempt->userid;
        $legacydata->thquizid = $thquizobj->get_thquizid();
        $legacydata->cmid = $thquizobj->get_cmid();
        $legacydata->courseid = $thquizobj->get_courseid();
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt question restarted event.
     *
     * There is no external API for replacing a question, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_question_restarted() {
        list($thquizobj, $quba, $attempt) = $this->prepare_thquiz_data();

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $thquizobj->get_courseid(),
            'context' => \context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'thquizid' => $thquizobj->get_thquizid(),
                'page' => 2,
                'slot' => 3,
                'newquestionid' => 2
            ]
        ];
        $event = \mod_thquiz\event\attempt_question_restarted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\attempt_question_restarted', $event);
        $this->assertEquals(\context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt updated event.
     *
     * There is no external API for updating an attempt, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_updated() {
        list($thquizobj, $quba, $attempt) = $this->prepare_thquiz_data();

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $thquizobj->get_courseid(),
            'context' => \context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'thquizid' => $thquizobj->get_thquizid(),
                'page' => 0
            ]
        ];
        $event = \mod_thquiz\event\attempt_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\attempt_updated', $event);
        $this->assertEquals(\context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt auto-saved event.
     *
     * There is no external API for auto-saving an attempt, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_autosaved() {
        list($thquizobj, $quba, $attempt) = $this->prepare_thquiz_data();

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $thquizobj->get_courseid(),
            'context' => \context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'thquizid' => $thquizobj->get_thquizid(),
                'page' => 0
            ]
        ];

        $event = \mod_thquiz\event\attempt_autosaved::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\attempt_autosaved', $event);
        $this->assertEquals(\context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the edit page viewed event.
     *
     * There is no external API for updating a thquiz, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_edit_page_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $thquiz = $this->getDataGenerator()->create_module('thquiz', array('course' => $course->id));

        $params = array(
            'courseid' => $course->id,
            'context' => \context_module::instance($thquiz->cmid),
            'other' => array(
                'thquizid' => $thquiz->id
            )
        );
        $event = \mod_thquiz\event\edit_page_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\edit_page_viewed', $event);
        $this->assertEquals(\context_module::instance($thquiz->cmid), $event->get_context());
        $expected = array($course->id, 'thquiz', 'editquestions', 'view.php?id=' . $thquiz->cmid, $thquiz->id, $thquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt deleted event.
     */
    public function test_attempt_deleted() {
        list($thquizobj, $quba, $attempt) = $this->prepare_thquiz_data();

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        thquiz_delete_attempt($attempt, $thquizobj->get_thquiz());
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\attempt_deleted', $event);
        $this->assertEquals(\context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $expected = array($thquizobj->get_courseid(), 'thquiz', 'delete attempt', 'report.php?id=' . $thquizobj->get_cmid(),
            $attempt->id, $thquizobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test that preview attempt deletions are not logged.
     */
    public function test_preview_attempt_deleted() {
        // Create thquiz with preview attempt.
        list($thquizobj, $quba, $previewattempt) = $this->prepare_thquiz_data(true);

        // Delete a preview attempt, capturing events.
        $sink = $this->redirectEvents();
        thquiz_delete_attempt($previewattempt, $thquizobj->get_thquiz());

        // Verify that no events were generated.
        $this->assertEmpty($sink->get_events());
    }

    /**
     * Test the report viewed event.
     *
     * There is no external API for viewing reports, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_report_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $thquiz = $this->getDataGenerator()->create_module('thquiz', array('course' => $course->id));

        $params = array(
            'context' => $context = \context_module::instance($thquiz->cmid),
            'other' => array(
                'thquizid' => $thquiz->id,
                'reportname' => 'overview'
            )
        );
        $event = \mod_thquiz\event\report_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\report_viewed', $event);
        $this->assertEquals(\context_module::instance($thquiz->cmid), $event->get_context());
        $expected = array($course->id, 'thquiz', 'report', 'report.php?id=' . $thquiz->cmid . '&mode=overview',
            $thquiz->id, $thquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt reviewed event.
     *
     * There is no external API for reviewing attempts, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_reviewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $thquiz = $this->getDataGenerator()->create_module('thquiz', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => \context_module::instance($thquiz->cmid),
            'other' => array(
                'thquizid' => $thquiz->id
            )
        );
        $event = \mod_thquiz\event\attempt_reviewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\attempt_reviewed', $event);
        $this->assertEquals(\context_module::instance($thquiz->cmid), $event->get_context());
        $expected = array($course->id, 'thquiz', 'review', 'review.php?attempt=1', $thquiz->id, $thquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt summary viewed event.
     *
     * There is no external API for viewing the attempt summary, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_summary_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $thquiz = $this->getDataGenerator()->create_module('thquiz', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => \context_module::instance($thquiz->cmid),
            'other' => array(
                'thquizid' => $thquiz->id
            )
        );
        $event = \mod_thquiz\event\attempt_summary_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\attempt_summary_viewed', $event);
        $this->assertEquals(\context_module::instance($thquiz->cmid), $event->get_context());
        $expected = array($course->id, 'thquiz', 'view summary', 'summary.php?attempt=1', $thquiz->id, $thquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override created event.
     *
     * There is no external API for creating a user override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_user_override_created() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $thquiz = $this->getDataGenerator()->create_module('thquiz', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'context' => \context_module::instance($thquiz->cmid),
            'other' => array(
                'thquizid' => $thquiz->id
            )
        );
        $event = \mod_thquiz\event\user_override_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\user_override_created', $event);
        $this->assertEquals(\context_module::instance($thquiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override created event.
     *
     * There is no external API for creating a group override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_group_override_created() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $thquiz = $this->getDataGenerator()->create_module('thquiz', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'context' => \context_module::instance($thquiz->cmid),
            'other' => array(
                'thquizid' => $thquiz->id,
                'groupid' => 2
            )
        );
        $event = \mod_thquiz\event\group_override_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\group_override_created', $event);
        $this->assertEquals(\context_module::instance($thquiz->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override updated event.
     *
     * There is no external API for updating a user override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_user_override_updated() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $thquiz = $this->getDataGenerator()->create_module('thquiz', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'context' => \context_module::instance($thquiz->cmid),
            'other' => array(
                'thquizid' => $thquiz->id
            )
        );
        $event = \mod_thquiz\event\user_override_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\user_override_updated', $event);
        $this->assertEquals(\context_module::instance($thquiz->cmid), $event->get_context());
        $expected = array($course->id, 'thquiz', 'edit override', 'overrideedit.php?id=1', $thquiz->id, $thquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override updated event.
     *
     * There is no external API for updating a group override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_group_override_updated() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $thquiz = $this->getDataGenerator()->create_module('thquiz', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'context' => \context_module::instance($thquiz->cmid),
            'other' => array(
                'thquizid' => $thquiz->id,
                'groupid' => 2
            )
        );
        $event = \mod_thquiz\event\group_override_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\group_override_updated', $event);
        $this->assertEquals(\context_module::instance($thquiz->cmid), $event->get_context());
        $expected = array($course->id, 'thquiz', 'edit override', 'overrideedit.php?id=1', $thquiz->id, $thquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override deleted event.
     */
    public function test_user_override_deleted() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $thquiz = $this->getDataGenerator()->create_module('thquiz', array('course' => $course->id));

        // Create an override.
        $override = new \stdClass();
        $override->thquiz = $thquiz->id;
        $override->userid = 2;
        $override->id = $DB->insert_record('thquiz_overrides', $override);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        thquiz_delete_override($thquiz, $override->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\user_override_deleted', $event);
        $this->assertEquals(\context_module::instance($thquiz->cmid), $event->get_context());
        $expected = array($course->id, 'thquiz', 'delete override', 'overrides.php?cmid=' . $thquiz->cmid, $thquiz->id, $thquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override deleted event.
     */
    public function test_group_override_deleted() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $thquiz = $this->getDataGenerator()->create_module('thquiz', array('course' => $course->id));

        // Create an override.
        $override = new \stdClass();
        $override->thquiz = $thquiz->id;
        $override->groupid = 2;
        $override->id = $DB->insert_record('thquiz_overrides', $override);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        thquiz_delete_override($thquiz, $override->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\group_override_deleted', $event);
        $this->assertEquals(\context_module::instance($thquiz->cmid), $event->get_context());
        $expected = array($course->id, 'thquiz', 'delete override', 'overrides.php?cmid=' . $thquiz->cmid, $thquiz->id, $thquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt viewed event.
     *
     * There is no external API for continuing an attempt, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $thquiz = $this->getDataGenerator()->create_module('thquiz', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => \context_module::instance($thquiz->cmid),
            'other' => array(
                'thquizid' => $thquiz->id,
                'page' => 0
            )
        );
        $event = \mod_thquiz\event\attempt_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\attempt_viewed', $event);
        $this->assertEquals(\context_module::instance($thquiz->cmid), $event->get_context());
        $expected = array($course->id, 'thquiz', 'continue attempt', 'review.php?attempt=1', $thquiz->id, $thquiz->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt previewed event.
     */
    public function test_attempt_preview_started() {
        $thquizobj = $this->prepare_thquiz();

        $quba = \question_engine::make_questions_usage_by_activity('mod_thquiz', $thquizobj->get_context());
        $quba->set_preferred_behaviour($thquizobj->get_thquiz()->preferredbehaviour);

        $timenow = time();
        $attempt = thquiz_create_attempt($thquizobj, 1, false, $timenow, true);
        thquiz_start_new_attempt($thquizobj, $quba, $attempt, 1, $timenow);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        thquiz_attempt_save_started($thquizobj, $quba, $attempt);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\attempt_preview_started', $event);
        $this->assertEquals(\context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $expected = array($thquizobj->get_courseid(), 'thquiz', 'preview', 'view.php?id=' . $thquizobj->get_cmid(),
            $thquizobj->get_thquizid(), $thquizobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the question manually graded event.
     *
     * There is no external API for manually grading a question, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_question_manually_graded() {
        list($thquizobj, $quba, $attempt) = $this->prepare_thquiz_data();

        $params = array(
            'objectid' => 1,
            'courseid' => $thquizobj->get_courseid(),
            'context' => \context_module::instance($thquizobj->get_cmid()),
            'other' => array(
                'thquizid' => $thquizobj->get_thquizid(),
                'attemptid' => 2,
                'slot' => 3
            )
        );
        $event = \mod_thquiz\event\question_manually_graded::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\question_manually_graded', $event);
        $this->assertEquals(\context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $expected = array($thquizobj->get_courseid(), 'thquiz', 'manualgrade', 'comment.php?attempt=2&slot=3',
            $thquizobj->get_thquizid(), $thquizobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt regraded event.
     *
     * There is no external API for regrading attempts, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_regraded() {
      $this->resetAfterTest();

      $this->setAdminUser();
      $course = $this->getDataGenerator()->create_course();
      $thquiz = $this->getDataGenerator()->create_module('thquiz', array('course' => $course->id));

      $params = array(
        'objectid' => 1,
        'relateduserid' => 2,
        'courseid' => $course->id,
        'context' => \context_module::instance($thquiz->cmid),
        'other' => array(
          'thquizid' => $thquiz->id
        )
      );
      $event = \mod_thquiz\event\attempt_regraded::create($params);

      // Trigger and capture the event.
      $sink = $this->redirectEvents();
      $event->trigger();
      $events = $sink->get_events();
      $event = reset($events);

      // Check that the event data is valid.
      $this->assertInstanceOf('\mod_thquiz\event\attempt_regraded', $event);
      $this->assertEquals(\context_module::instance($thquiz->cmid), $event->get_context());
      $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt notify manual graded event.
     * There is no external API for notification email when manual grading of user's attempt is completed,
     * so the unit test will simply create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_manual_grading_completed() {
        $this->resetAfterTest();
        list($thquizobj, $quba, $attempt) = $this->prepare_thquiz_data();
        $attemptobj = thquiz_attempt::create($attempt->id);

        $params = [
            'objectid' => $attemptobj->get_attemptid(),
            'relateduserid' => $attemptobj->get_userid(),
            'courseid' => $attemptobj->get_course()->id,
            'context' => \context_module::instance($attemptobj->get_cmid()),
            'other' => [
                'thquizid' => $attemptobj->get_thquizid()
            ]
        ];
        $event = \mod_thquiz\event\attempt_manual_grading_completed::create($params);

        // Catch the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_thquiz\event\attempt_manual_grading_completed', $event);
        $this->assertEquals('thquiz_attempts', $event->objecttable);
        $this->assertEquals($thquizobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertNotEmpty($event->get_description());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the page break created event.
     *
     * There is no external API for creating page break, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_page_break_created() {
        $thquizobj = $this->prepare_thquiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'thquizid' => $thquizobj->get_thquizid(),
                'slotnumber' => 3,
            ]
        ];
        $event = \mod_thquiz\event\page_break_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\page_break_created', $event);
        $this->assertEquals(context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the page break deleted event.
     *
     * There is no external API for deleting page break, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_page_deleted_created() {
        $thquizobj = $this->prepare_thquiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'thquizid' => $thquizobj->get_thquizid(),
                'slotnumber' => 3,
            ]
        ];
        $event = \mod_thquiz\event\page_break_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\page_break_deleted', $event);
        $this->assertEquals(context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the thquiz grade updated event.
     *
     * There is no external API for updating thquiz grade, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_thquiz_grade_updated() {
        $thquizobj = $this->prepare_thquiz();

        $params = [
            'objectid' => $thquizobj->get_thquizid(),
            'context' => context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'oldgrade' => 1,
                'newgrade' => 3,
            ]
        ];
        $event = \mod_thquiz\event\thquiz_grade_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\thquiz_grade_updated', $event);
        $this->assertEquals(context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the thquiz re-paginated event.
     *
     * There is no external API for re-paginating thquiz, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_thquiz_repaginated() {
        $thquizobj = $this->prepare_thquiz();

        $params = [
            'objectid' => $thquizobj->get_thquizid(),
            'context' => context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'slotsperpage' => 3,
            ]
        ];
        $event = \mod_thquiz\event\thquiz_repaginated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\thquiz_repaginated', $event);
        $this->assertEquals(context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the section break created event.
     *
     * There is no external API for creating section break, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_section_break_created() {
        $thquizobj = $this->prepare_thquiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'thquizid' => $thquizobj->get_thquizid(),
                'firstslotid' => 1,
                'firstslotnumber' => 2,
                'title' => 'New title'
            ]
        ];
        $event = \mod_thquiz\event\section_break_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\section_break_created', $event);
        $this->assertEquals(context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertStringContainsString($params['other']['title'], $event->get_description());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the section break deleted event.
     *
     * There is no external API for deleting section break, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_section_break_deleted() {
        $thquizobj = $this->prepare_thquiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'thquizid' => $thquizobj->get_thquizid(),
                'firstslotid' => 1,
                'firstslotnumber' => 2
            ]
        ];
        $event = \mod_thquiz\event\section_break_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\section_break_deleted', $event);
        $this->assertEquals(context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the section shuffle updated event.
     *
     * There is no external API for updating section shuffle, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_section_shuffle_updated() {
        $thquizobj = $this->prepare_thquiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'thquizid' => $thquizobj->get_thquizid(),
                'firstslotnumber' => 2,
                'shuffle' => true
            ]
        ];
        $event = \mod_thquiz\event\section_shuffle_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\section_shuffle_updated', $event);
        $this->assertEquals(context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the section title updated event.
     *
     * There is no external API for updating section title, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_section_title_updated() {
        $thquizobj = $this->prepare_thquiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'thquizid' => $thquizobj->get_thquizid(),
                'firstslotid' => 1,
                'firstslotnumber' => 2,
                'newtitle' => 'New title'
            ]
        ];
        $event = \mod_thquiz\event\section_title_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\section_title_updated', $event);
        $this->assertEquals(context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertStringContainsString($params['other']['newtitle'], $event->get_description());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot created event.
     *
     * There is no external API for creating slot, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_created() {
        $thquizobj = $this->prepare_thquiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'thquizid' => $thquizobj->get_thquizid(),
                'slotnumber' => 1,
                'page' => 1
            ]
        ];
        $event = \mod_thquiz\event\slot_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\slot_created', $event);
        $this->assertEquals(context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot deleted event.
     *
     * There is no external API for deleting slot, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_deleted() {
        $thquizobj = $this->prepare_thquiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'thquizid' => $thquizobj->get_thquizid(),
                'slotnumber' => 1,
            ]
        ];
        $event = \mod_thquiz\event\slot_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\slot_deleted', $event);
        $this->assertEquals(context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot mark updated event.
     *
     * There is no external API for updating slot mark, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_mark_updated() {
        $thquizobj = $this->prepare_thquiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'thquizid' => $thquizobj->get_thquizid(),
                'previousmaxmark' => 1,
                'newmaxmark' => 2,
            ]
        ];
        $event = \mod_thquiz\event\slot_mark_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\slot_mark_updated', $event);
        $this->assertEquals(context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot moved event.
     *
     * There is no external API for moving slot, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_moved() {
        $thquizobj = $this->prepare_thquiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'thquizid' => $thquizobj->get_thquizid(),
                'previousslotnumber' => 1,
                'afterslotnumber' => 2,
                'page' => 1
            ]
        ];
        $event = \mod_thquiz\event\slot_moved::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\slot_moved', $event);
        $this->assertEquals(context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot require previous updated event.
     *
     * There is no external API for updating slot require previous option, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_requireprevious_updated() {
        $thquizobj = $this->prepare_thquiz();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($thquizobj->get_cmid()),
            'other' => [
                'thquizid' => $thquizobj->get_thquizid(),
                'requireprevious' => true
            ]
        ];
        $event = \mod_thquiz\event\slot_requireprevious_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_thquiz\event\slot_requireprevious_updated', $event);
        $this->assertEquals(context_module::instance($thquizobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }
}

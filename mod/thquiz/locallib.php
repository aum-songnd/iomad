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
 * Library of functions used by the thquiz module.
 *
 * This contains functions that are called from within the thquiz module only
 * Functions that are also called by core Moodle are in {@link lib.php}
 * This script also loads the code in {@link questionlib.php} which holds
 * the module-indpendent code for handling questions and which in turn
 * initialises all the questiontype classes.
 *
 * @package    mod_thquiz
 * @copyright  1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/thquiz/lib.php');
require_once($CFG->dirroot . '/mod/thquiz/accessmanager.php');
require_once($CFG->dirroot . '/mod/thquiz/accessmanager_form.php');
require_once($CFG->dirroot . '/mod/thquiz/renderer.php');
require_once($CFG->dirroot . '/mod/thquiz/attemptlib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/questionlib.php');

use mod_thquiz\question\bank\qbank_helper;
use qbank_previewquestion\question_preview_options;

/**
 * @var int We show the countdown timer if there is less than this amount of time left before the
 * the thquiz close date. (1 hour)
 */
define('THQUIZ_SHOW_TIME_BEFORE_DEADLINE', '3600');

/**
 * @var int If there are fewer than this many seconds left when the student submits
 * a page of the thquiz, then do not take them to the next page of the thquiz. Instead
 * close the thquiz immediately.
 */
define('THQUIZ_MIN_TIME_TO_CONTINUE', '2');

/**
 * @var int We show no image when user selects No image from dropdown menu in thquiz settings.
 */
define('THQUIZ_SHOWIMAGE_NONE', 0);

/**
 * @var int We show small image when user selects small image from dropdown menu in thquiz settings.
 */
define('THQUIZ_SHOWIMAGE_SMALL', 1);

/**
 * @var int We show Large image when user selects Large image from dropdown menu in thquiz settings.
 */
define('THQUIZ_SHOWIMAGE_LARGE', 2);


// Functions related to attempts ///////////////////////////////////////////////

/**
 * Creates an object to represent a new attempt at a thquiz
 *
 * Creates an attempt object to represent an attempt at the thquiz by the current
 * user starting at the current time. The ->id field is not set. The object is
 * NOT written to the database.
 *
 * @param object $thquizobj the thquiz object to create an attempt for.
 * @param int $attemptnumber the sequence number for the attempt.
 * @param stdClass|null $lastattempt the previous attempt by this user, if any. Only needed
 *         if $attemptnumber > 1 and $thquiz->attemptonlast is true.
 * @param int $timenow the time the attempt was started at.
 * @param bool $ispreview whether this new attempt is a preview.
 * @param int $userid  the id of the user attempting this thquiz.
 *
 * @return object the newly created attempt object.
 */
function thquiz_create_attempt(thquiz $thquizobj, $attemptnumber, $lastattempt, $timenow, $ispreview = false, $userid = null) {
    global $USER;

    if ($userid === null) {
        $userid = $USER->id;
    }

    $thquiz = $thquizobj->get_thquiz();
    if ($thquiz->sumgrades < 0.000005 && $thquiz->grade > 0.000005) {
        throw new moodle_exception('cannotstartgradesmismatch', 'thquiz',
                new moodle_url('/mod/thquiz/view.php', array('q' => $thquiz->id)),
                    array('grade' => thquiz_format_grade($thquiz, $thquiz->grade)));
    }

    if ($attemptnumber == 1 || !$thquiz->attemptonlast) {
        // We are not building on last attempt so create a new attempt.
        $attempt = new stdClass();
        $attempt->thquiz = $thquiz->id;
        $attempt->userid = $userid;
        $attempt->preview = 0;
        $attempt->layout = '';
    } else {
        // Build on last attempt.
        if (empty($lastattempt)) {
            throw new \moodle_exception('cannotfindprevattempt', 'thquiz');
        }
        $attempt = $lastattempt;
    }

    $attempt->attempt = $attemptnumber;
    $attempt->timestart = $timenow;
    $attempt->timefinish = 0;
    $attempt->timemodified = $timenow;
    $attempt->timemodifiedoffline = 0;
    $attempt->state = thquiz_attempt::IN_PROGRESS;
    $attempt->currentpage = 0;
    $attempt->sumgrades = null;
    $attempt->gradednotificationsenttime = null;

    // If this is a preview, mark it as such.
    if ($ispreview) {
        $attempt->preview = 1;
    }

    $timeclose = $thquizobj->get_access_manager($timenow)->get_end_time($attempt);
    if ($timeclose === false || $ispreview) {
        $attempt->timecheckstate = null;
    } else {
        $attempt->timecheckstate = $timeclose;
    }

    return $attempt;
}
/**
 * Start a normal, new, thquiz attempt.
 *
 * @param thquiz      $thquizobj            the thquiz object to start an attempt for.
 * @param question_usage_by_activity $quba
 * @param object    $attempt
 * @param integer   $attemptnumber      starting from 1
 * @param integer   $timenow            the attempt start time
 * @param array     $questionids        slot number => question id. Used for random questions, to force the choice
 *                                        of a particular actual question. Intended for testing purposes only.
 * @param array     $forcedvariantsbyslot slot number => variant. Used for questions with variants,
 *                                          to force the choice of a particular variant. Intended for testing
 *                                          purposes only.
 * @throws moodle_exception
 * @return object   modified attempt object
 */
function thquiz_start_new_attempt($thquizobj, $quba, $attempt, $attemptnumber, $timenow,
                                $questionids = array(), $forcedvariantsbyslot = array()) {

    // Usages for this user's previous thquiz attempts.
    $qubaids = new \mod_thquiz\question\qubaids_for_users_attempts(
            $thquizobj->get_thquizid(), $attempt->userid);

    // Fully load all the questions in this thquiz.
    $thquizobj->preload_questions();
    $thquizobj->load_questions();

    // First load all the non-random questions.
    $randomfound = false;
    $slot = 0;
    $questions = array();
    $maxmark = array();
    $page = array();
    foreach ($thquizobj->get_questions() as $questiondata) {
        $slot += 1;
        $maxmark[$slot] = $questiondata->maxmark;
        $page[$slot] = $questiondata->page;
        if ($questiondata->status == \core_question\local\bank\question_version_status::QUESTION_STATUS_DRAFT) {
            throw new moodle_exception('questiondraftonly', 'mod_thquiz', '', $questiondata->name);
        }
        if ($questiondata->qtype == 'random') {
            $randomfound = true;
            continue;
        }
        if (!$thquizobj->get_thquiz()->shuffleanswers) {
            $questiondata->options->shuffleanswers = false;
        }
        $questions[$slot] = question_bank::make_question($questiondata);
    }

    // Then find a question to go in place of each random question.
    if ($randomfound) {
        $slot = 0;
        $usedquestionids = array();
        foreach ($questions as $question) {
            if ($question->id && isset($usedquestions[$question->id])) {
                $usedquestionids[$question->id] += 1;
            } else {
                $usedquestionids[$question->id] = 1;
            }
        }
        $randomloader = new \core_question\local\bank\random_question_loader($qubaids, $usedquestionids);

        foreach ($thquizobj->get_questions() as $questiondata) {
            $slot += 1;
            if ($questiondata->qtype != 'random') {
                continue;
            }

            $tagids = qbank_helper::get_tag_ids_for_slot($questiondata);

            // Deal with fixed random choices for testing.
            if (isset($questionids[$quba->next_slot_number()])) {
                if ($randomloader->is_question_available($questiondata->category,
                        (bool) $questiondata->questiontext, $questionids[$quba->next_slot_number()], $tagids)) {
                    $questions[$slot] = question_bank::load_question(
                            $questionids[$quba->next_slot_number()], $thquizobj->get_thquiz()->shuffleanswers);
                    continue;
                } else {
                    throw new coding_exception('Forced question id not available.');
                }
            }

            // Normal case, pick one at random.
            $questionid = $randomloader->get_next_question_id($questiondata->category,
                    $questiondata->randomrecurse, $tagids);
            if ($questionid === null) {
                throw new moodle_exception('notenoughrandomquestions', 'thquiz',
                                           $thquizobj->view_url(), $questiondata);
            }

            $questions[$slot] = question_bank::load_question($questionid,
                    $thquizobj->get_thquiz()->shuffleanswers);
        }
    }

    // Finally add them all to the usage.
    ksort($questions);
    foreach ($questions as $slot => $question) {
        $newslot = $quba->add_question($question, $maxmark[$slot]);
        if ($newslot != $slot) {
            throw new coding_exception('Slot numbers have got confused.');
        }
    }

    // Start all the questions.
    $variantstrategy = new core_question\engine\variants\least_used_strategy($quba, $qubaids);

    if (!empty($forcedvariantsbyslot)) {
        $forcedvariantsbyseed = question_variant_forced_choices_selection_strategy::prepare_forced_choices_array(
            $forcedvariantsbyslot, $quba);
        $variantstrategy = new question_variant_forced_choices_selection_strategy(
            $forcedvariantsbyseed, $variantstrategy);
    }

    $quba->start_all_questions($variantstrategy, $timenow, $attempt->userid);

    // Work out the attempt layout.
    $sections = $thquizobj->get_sections();
    foreach ($sections as $i => $section) {
        if (isset($sections[$i + 1])) {
            $sections[$i]->lastslot = $sections[$i + 1]->firstslot - 1;
        } else {
            $sections[$i]->lastslot = count($questions);
        }
    }

    $layout = array();
    foreach ($sections as $section) {
        if ($section->shufflequestions) {
            $questionsinthissection = array();
            for ($slot = $section->firstslot; $slot <= $section->lastslot; $slot += 1) {
                $questionsinthissection[] = $slot;
            }
            shuffle($questionsinthissection);
            $questionsonthispage = 0;
            foreach ($questionsinthissection as $slot) {
                if ($questionsonthispage && $questionsonthispage == $thquizobj->get_thquiz()->questionsperpage) {
                    $layout[] = 0;
                    $questionsonthispage = 0;
                }
                $layout[] = $slot;
                $questionsonthispage += 1;
            }

        } else {
            $currentpage = $page[$section->firstslot];
            for ($slot = $section->firstslot; $slot <= $section->lastslot; $slot += 1) {
                if ($currentpage !== null && $page[$slot] != $currentpage) {
                    $layout[] = 0;
                }
                $layout[] = $slot;
                $currentpage = $page[$slot];
            }
        }

        // Each section ends with a page break.
        $layout[] = 0;
    }
    $attempt->layout = implode(',', $layout);

    return $attempt;
}

/**
 * Start a subsequent new attempt, in each attempt builds on last mode.
 *
 * @param question_usage_by_activity    $quba         this question usage
 * @param object                        $attempt      this attempt
 * @param object                        $lastattempt  last attempt
 * @return object                       modified attempt object
 *
 */
function thquiz_start_attempt_built_on_last($quba, $attempt, $lastattempt) {
    $oldquba = question_engine::load_questions_usage_by_activity($lastattempt->uniqueid);

    $oldnumberstonew = array();
    foreach ($oldquba->get_attempt_iterator() as $oldslot => $oldqa) {
        $question = $oldqa->get_question(false);
        if ($question->status == \core_question\local\bank\question_version_status::QUESTION_STATUS_DRAFT) {
            throw new moodle_exception('questiondraftonly', 'mod_thquiz', '', $question->name);
        }
        $newslot = $quba->add_question($question, $oldqa->get_max_mark());

        $quba->start_question_based_on($newslot, $oldqa);

        $oldnumberstonew[$oldslot] = $newslot;
    }

    // Update attempt layout.
    $newlayout = array();
    foreach (explode(',', $lastattempt->layout) as $oldslot) {
        if ($oldslot != 0) {
            $newlayout[] = $oldnumberstonew[$oldslot];
        } else {
            $newlayout[] = 0;
        }
    }
    $attempt->layout = implode(',', $newlayout);
    return $attempt;
}

/**
 * The save started question usage and thquiz attempt in db and log the started attempt.
 *
 * @param thquiz                       $thquizobj
 * @param question_usage_by_activity $quba
 * @param object                     $attempt
 * @return object                    attempt object with uniqueid and id set.
 */
function thquiz_attempt_save_started($thquizobj, $quba, $attempt) {
    global $DB;
    // Save the attempt in the database.
    question_engine::save_questions_usage_by_activity($quba);
    $attempt->uniqueid = $quba->get_id();
    $attempt->id = $DB->insert_record('thquiz_attempts', $attempt);

    // Params used by the events below.
    $params = array(
        'objectid' => $attempt->id,
        'relateduserid' => $attempt->userid,
        'courseid' => $thquizobj->get_courseid(),
        'context' => $thquizobj->get_context()
    );
    // Decide which event we are using.
    if ($attempt->preview) {
        $params['other'] = array(
            'thquizid' => $thquizobj->get_thquizid()
        );
        $event = \mod_thquiz\event\attempt_preview_started::create($params);
    } else {
        $event = \mod_thquiz\event\attempt_started::create($params);

    }

    // Trigger the event.
    $event->add_record_snapshot('thquiz', $thquizobj->get_thquiz());
    $event->add_record_snapshot('thquiz_attempts', $attempt);
    $event->trigger();

    return $attempt;
}

/**
 * Returns an unfinished attempt (if there is one) for the given
 * user on the given thquiz. This function does not return preview attempts.
 *
 * @param int $thquizid the id of the thquiz.
 * @param int $userid the id of the user.
 *
 * @return mixed the unfinished attempt if there is one, false if not.
 */
function thquiz_get_user_attempt_unfinished($thquizid, $userid) {
    $attempts = thquiz_get_user_attempts($thquizid, $userid, 'unfinished', true);
    if ($attempts) {
        return array_shift($attempts);
    } else {
        return false;
    }
}

/**
 * Delete a thquiz attempt.
 * @param mixed $attempt an integer attempt id or an attempt object
 *      (row of the thquiz_attempts table).
 * @param object $thquiz the thquiz object.
 */
function thquiz_delete_attempt($attempt, $thquiz) {
    global $DB;
    if (is_numeric($attempt)) {
        if (!$attempt = $DB->get_record('thquiz_attempts', array('id' => $attempt))) {
            return;
        }
    }

    if ($attempt->thquiz != $thquiz->id) {
        debugging("Trying to delete attempt $attempt->id which belongs to thquiz $attempt->thquiz " .
                "but was passed thquiz $thquiz->id.");
        return;
    }

    if (!isset($thquiz->cmid)) {
        $cm = get_coursemodule_from_instance('thquiz', $thquiz->id, $thquiz->course);
        $thquiz->cmid = $cm->id;
    }

    question_engine::delete_questions_usage_by_activity($attempt->uniqueid);
    $DB->delete_records('thquiz_attempts', array('id' => $attempt->id));

    // Log the deletion of the attempt if not a preview.
    if (!$attempt->preview) {
        $params = array(
            'objectid' => $attempt->id,
            'relateduserid' => $attempt->userid,
            'context' => context_module::instance($thquiz->cmid),
            'other' => array(
                'thquizid' => $thquiz->id
            )
        );
        $event = \mod_thquiz\event\attempt_deleted::create($params);
        $event->add_record_snapshot('thquiz_attempts', $attempt);
        $event->trigger();

        $callbackclasses = \core_component::get_plugin_list_with_class('thquiz', 'thquiz_attempt_deleted');
        foreach ($callbackclasses as $callbackclass) {
            component_class_callback($callbackclass, 'callback', [$thquiz->id]);
        }
    }

    // Search thquiz_attempts for other instances by this user.
    // If none, then delete record for this thquiz, this user from thquiz_grades
    // else recalculate best grade.
    $userid = $attempt->userid;
    if (!$DB->record_exists('thquiz_attempts', array('userid' => $userid, 'thquiz' => $thquiz->id))) {
        $DB->delete_records('thquiz_grades', array('userid' => $userid, 'thquiz' => $thquiz->id));
    } else {
        thquiz_save_best_grade($thquiz, $userid);
    }

    thquiz_update_grades($thquiz, $userid);
}

/**
 * Delete all the preview attempts at a thquiz, or possibly all the attempts belonging
 * to one user.
 * @param object $thquiz the thquiz object.
 * @param int $userid (optional) if given, only delete the previews belonging to this user.
 */
function thquiz_delete_previews($thquiz, $userid = null) {
    global $DB;
    $conditions = array('thquiz' => $thquiz->id, 'preview' => 1);
    if (!empty($userid)) {
        $conditions['userid'] = $userid;
    }
    $previewattempts = $DB->get_records('thquiz_attempts', $conditions);
    foreach ($previewattempts as $attempt) {
        thquiz_delete_attempt($attempt, $thquiz);
    }
}

/**
 * @param int $thquizid The thquiz id.
 * @return bool whether this thquiz has any (non-preview) attempts.
 */
function thquiz_has_attempts($thquizid) {
    global $DB;
    return $DB->record_exists('thquiz_attempts', array('thquiz' => $thquizid, 'preview' => 0));
}

// Functions to do with thquiz layout and pages //////////////////////////////////

/**
 * Repaginate the questions in a thquiz
 * @param int $thquizid the id of the thquiz to repaginate.
 * @param int $slotsperpage number of items to put on each page. 0 means unlimited.
 */
function thquiz_repaginate_questions($thquizid, $slotsperpage) {
    global $DB;
    $trans = $DB->start_delegated_transaction();

    $sections = $DB->get_records('thquiz_sections', array('thquizid' => $thquizid), 'firstslot ASC');
    $firstslots = array();
    foreach ($sections as $section) {
        if ((int)$section->firstslot === 1) {
            continue;
        }
        $firstslots[] = $section->firstslot;
    }

    $slots = $DB->get_records('thquiz_slots', array('thquizid' => $thquizid),
            'slot');
    $currentpage = 1;
    $slotsonthispage = 0;
    foreach ($slots as $slot) {
        if (($firstslots && in_array($slot->slot, $firstslots)) ||
            ($slotsonthispage && $slotsonthispage == $slotsperpage)) {
            $currentpage += 1;
            $slotsonthispage = 0;
        }
        if ($slot->page != $currentpage) {
            $DB->set_field('thquiz_slots', 'page', $currentpage, array('id' => $slot->id));
        }
        $slotsonthispage += 1;
    }

    $trans->allow_commit();

    // Log thquiz re-paginated event.
    $cm = get_coursemodule_from_instance('thquiz', $thquizid);
    $event = \mod_thquiz\event\thquiz_repaginated::create([
        'context' => \context_module::instance($cm->id),
        'objectid' => $thquizid,
        'other' => [
            'slotsperpage' => $slotsperpage
        ]
    ]);
    $event->trigger();

}

// Functions to do with thquiz grades ////////////////////////////////////////////

/**
 * Convert the raw grade stored in $attempt into a grade out of the maximum
 * grade for this thquiz.
 *
 * @param float $rawgrade the unadjusted grade, fof example $attempt->sumgrades
 * @param object $thquiz the thquiz object. Only the fields grade, sumgrades and decimalpoints are used.
 * @param bool|string $format whether to format the results for display
 *      or 'question' to format a question grade (different number of decimal places.
 * @return float|string the rescaled grade, or null/the lang string 'notyetgraded'
 *      if the $grade is null.
 */
function thquiz_rescale_grade($rawgrade, $thquiz, $format = true) {
    if (is_null($rawgrade)) {
        $grade = null;
    } else if ($thquiz->sumgrades >= 0.000005) {
        $grade = $rawgrade * $thquiz->grade / $thquiz->sumgrades;
    } else {
        $grade = 0;
    }
    if ($format === 'question') {
        $grade = thquiz_format_question_grade($thquiz, $grade);
    } else if ($format) {
        $grade = thquiz_format_grade($thquiz, $grade);
    }
    return $grade;
}

/**
 * Get the feedback object for this grade on this thquiz.
 *
 * @param float $grade a grade on this thquiz.
 * @param object $thquiz the thquiz settings.
 * @return false|stdClass the record object or false if there is not feedback for the given grade
 * @since  Moodle 3.1
 */
function thquiz_feedback_record_for_grade($grade, $thquiz) {
    global $DB;

    // With CBM etc, it is possible to get -ve grades, which would then not match
    // any feedback. Therefore, we replace -ve grades with 0.
    $grade = max($grade, 0);

    $feedback = $DB->get_record_select('thquiz_feedback',
            'thquizid = ? AND mingrade <= ? AND ? < maxgrade', array($thquiz->id, $grade, $grade));

    return $feedback;
}

/**
 * Get the feedback text that should be show to a student who
 * got this grade on this thquiz. The feedback is processed ready for diplay.
 *
 * @param float $grade a grade on this thquiz.
 * @param object $thquiz the thquiz settings.
 * @param object $context the thquiz context.
 * @return string the comment that corresponds to this grade (empty string if there is not one.
 */
function thquiz_feedback_for_grade($grade, $thquiz, $context) {

    if (is_null($grade)) {
        return '';
    }

    $feedback = thquiz_feedback_record_for_grade($grade, $thquiz);

    if (empty($feedback->feedbacktext)) {
        return '';
    }

    // Clean the text, ready for display.
    $formatoptions = new stdClass();
    $formatoptions->noclean = true;
    $feedbacktext = file_rewrite_pluginfile_urls($feedback->feedbacktext, 'pluginfile.php',
            $context->id, 'mod_thquiz', 'feedback', $feedback->id);
    $feedbacktext = format_text($feedbacktext, $feedback->feedbacktextformat, $formatoptions);

    return $feedbacktext;
}

/**
 * @param object $thquiz the thquiz database row.
 * @return bool Whether this thquiz has any non-blank feedback text.
 */
function thquiz_has_feedback($thquiz) {
    global $DB;
    static $cache = array();
    if (!array_key_exists($thquiz->id, $cache)) {
        $cache[$thquiz->id] = thquiz_has_grades($thquiz) &&
                $DB->record_exists_select('thquiz_feedback', "thquizid = ? AND " .
                    $DB->sql_isnotempty('thquiz_feedback', 'feedbacktext', false, true),
                array($thquiz->id));
    }
    return $cache[$thquiz->id];
}

/**
 * Update the sumgrades field of the thquiz. This needs to be called whenever
 * the grading structure of the thquiz is changed. For example if a question is
 * added or removed, or a question weight is changed.
 *
 * You should call {@link thquiz_delete_previews()} before you call this function.
 *
 * @param object $thquiz a thquiz.
 */
function thquiz_update_sumgrades($thquiz) {
    global $DB;

    $sql = 'UPDATE {thquiz}
            SET sumgrades = COALESCE((
                SELECT SUM(maxmark)
                FROM {thquiz_slots}
                WHERE thquizid = {thquiz}.id
            ), 0)
            WHERE id = ?';
    $DB->execute($sql, array($thquiz->id));
    $thquiz->sumgrades = $DB->get_field('thquiz', 'sumgrades', array('id' => $thquiz->id));

    if ($thquiz->sumgrades < 0.000005 && thquiz_has_attempts($thquiz->id)) {
        // If the thquiz has been attempted, and the sumgrades has been
        // set to 0, then we must also set the maximum possible grade to 0, or
        // we will get a divide by zero error.
        thquiz_set_grade(0, $thquiz);
    }

    $callbackclasses = \core_component::get_plugin_list_with_class('thquiz', 'thquiz_structure_modified');
    foreach ($callbackclasses as $callbackclass) {
        component_class_callback($callbackclass, 'callback', [$thquiz->id]);
    }
}

/**
 * Update the sumgrades field of the attempts at a thquiz.
 *
 * @param object $thquiz a thquiz.
 */
function thquiz_update_all_attempt_sumgrades($thquiz) {
    global $DB;
    $dm = new question_engine_data_mapper();
    $timenow = time();

    $sql = "UPDATE {thquiz_attempts}
            SET
                timemodified = :timenow,
                sumgrades = (
                    {$dm->sum_usage_marks_subquery('uniqueid')}
                )
            WHERE thquiz = :thquizid AND state = :finishedstate";
    $DB->execute($sql, array('timenow' => $timenow, 'thquizid' => $thquiz->id,
            'finishedstate' => thquiz_attempt::FINISHED));
}

/**
 * The thquiz grade is the maximum that student's results are marked out of. When it
 * changes, the corresponding data in thquiz_grades and thquiz_feedback needs to be
 * rescaled. After calling this function, you probably need to call
 * thquiz_update_all_attempt_sumgrades, thquiz_update_all_final_grades and
 * thquiz_update_grades.
 *
 * @param float $newgrade the new maximum grade for the thquiz.
 * @param object $thquiz the thquiz we are updating. Passed by reference so its
 *      grade field can be updated too.
 * @return bool indicating success or failure.
 */
function thquiz_set_grade($newgrade, $thquiz) {
    global $DB;
    // This is potentially expensive, so only do it if necessary.
    if (abs($thquiz->grade - $newgrade) < 1e-7) {
        // Nothing to do.
        return true;
    }

    $oldgrade = $thquiz->grade;
    $thquiz->grade = $newgrade;

    // Use a transaction, so that on those databases that support it, this is safer.
    $transaction = $DB->start_delegated_transaction();

    // Update the thquiz table.
    $DB->set_field('thquiz', 'grade', $newgrade, array('id' => $thquiz->instance));

    if ($oldgrade < 1) {
        // If the old grade was zero, we cannot rescale, we have to recompute.
        // We also recompute if the old grade was too small to avoid underflow problems.
        thquiz_update_all_final_grades($thquiz);

    } else {
        // We can rescale the grades efficiently.
        $timemodified = time();
        $DB->execute("
                UPDATE {thquiz_grades}
                SET grade = ? * grade, timemodified = ?
                WHERE thquiz = ?
        ", array($newgrade/$oldgrade, $timemodified, $thquiz->id));
    }

    if ($oldgrade > 1e-7) {
        // Update the thquiz_feedback table.
        $factor = $newgrade/$oldgrade;
        $DB->execute("
                UPDATE {thquiz_feedback}
                SET mingrade = ? * mingrade, maxgrade = ? * maxgrade
                WHERE thquizid = ?
        ", array($factor, $factor, $thquiz->id));
    }

    // Update grade item and send all grades to gradebook.
    thquiz_grade_item_update($thquiz);
    thquiz_update_grades($thquiz);

    $transaction->allow_commit();

    // Log thquiz grade updated event.
    // We use $num + 0 as a trick to remove the useless 0 digits from decimals.
    $cm = get_coursemodule_from_instance('thquiz', $thquiz->id);
    $event = \mod_thquiz\event\thquiz_grade_updated::create([
        'context' => \context_module::instance($cm->id),
        'objectid' => $thquiz->id,
        'other' => [
            'oldgrade' => $oldgrade + 0,
            'newgrade' => $newgrade + 0
        ]
    ]);
    $event->trigger();
    return true;
}

/**
 * Save the overall grade for a user at a thquiz in the thquiz_grades table
 *
 * @param object $thquiz The thquiz for which the best grade is to be calculated and then saved.
 * @param int $userid The userid to calculate the grade for. Defaults to the current user.
 * @param array $attempts The attempts of this user. Useful if you are
 * looping through many users. Attempts can be fetched in one master query to
 * avoid repeated querying.
 * @return bool Indicates success or failure.
 */
function thquiz_save_best_grade($thquiz, $userid = null, $attempts = array()) {
    global $DB, $OUTPUT, $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    if (!$attempts) {
        // Get all the attempts made by the user.
        $attempts = thquiz_get_user_attempts($thquiz->id, $userid);
    }

    // Calculate the best grade.
    $bestgrade = thquiz_calculate_best_grade($thquiz, $attempts);
    $bestgrade = thquiz_rescale_grade($bestgrade, $thquiz, false);

    // Save the best grade in the database.
    if (is_null($bestgrade)) {
        $DB->delete_records('thquiz_grades', array('thquiz' => $thquiz->id, 'userid' => $userid));

    } else if ($grade = $DB->get_record('thquiz_grades',
            array('thquiz' => $thquiz->id, 'userid' => $userid))) {
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        $DB->update_record('thquiz_grades', $grade);

    } else {
        $grade = new stdClass();
        $grade->thquiz = $thquiz->id;
        $grade->userid = $userid;
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        $DB->insert_record('thquiz_grades', $grade);
    }

    thquiz_update_grades($thquiz, $userid);
}

/**
 * Calculate the overall grade for a thquiz given a number of attempts by a particular user.
 *
 * @param object $thquiz    the thquiz settings object.
 * @param array $attempts an array of all the user's attempts at this thquiz in order.
 * @return float          the overall grade
 */
function thquiz_calculate_best_grade($thquiz, $attempts) {

    switch ($thquiz->grademethod) {

        case THQUIZ_ATTEMPTFIRST:
            $firstattempt = reset($attempts);
            return $firstattempt->sumgrades;

        case THQUIZ_ATTEMPTLAST:
            $lastattempt = end($attempts);
            return $lastattempt->sumgrades;

        case THQUIZ_GRADEAVERAGE:
            $sum = 0;
            $count = 0;
            foreach ($attempts as $attempt) {
                if (!is_null($attempt->sumgrades)) {
                    $sum += $attempt->sumgrades;
                    $count++;
                }
            }
            if ($count == 0) {
                return null;
            }
            return $sum / $count;

        case THQUIZ_GRADEHIGHEST:
        default:
            $max = null;
            foreach ($attempts as $attempt) {
                if ($attempt->sumgrades > $max) {
                    $max = $attempt->sumgrades;
                }
            }
            return $max;
    }
}

/**
 * Update the final grade at this thquiz for all students.
 *
 * This function is equivalent to calling thquiz_save_best_grade for all
 * users, but much more efficient.
 *
 * @param object $thquiz the thquiz settings.
 */
function thquiz_update_all_final_grades($thquiz) {
    global $DB;

    if (!$thquiz->sumgrades) {
        return;
    }

    $param = array('ithquizid' => $thquiz->id, 'istatefinished' => thquiz_attempt::FINISHED);
    $firstlastattemptjoin = "JOIN (
            SELECT
                ithquiza.userid,
                MIN(attempt) AS firstattempt,
                MAX(attempt) AS lastattempt

            FROM {thquiz_attempts} ithquiza

            WHERE
                ithquiza.state = :istatefinished AND
                ithquiza.preview = 0 AND
                ithquiza.thquiz = :ithquizid

            GROUP BY ithquiza.userid
        ) first_last_attempts ON first_last_attempts.userid = thquiza.userid";

    switch ($thquiz->grademethod) {
        case THQUIZ_ATTEMPTFIRST:
            // Because of the where clause, there will only be one row, but we
            // must still use an aggregate function.
            $select = 'MAX(thquiza.sumgrades)';
            $join = $firstlastattemptjoin;
            $where = 'thquiza.attempt = first_last_attempts.firstattempt AND';
            break;

        case THQUIZ_ATTEMPTLAST:
            // Because of the where clause, there will only be one row, but we
            // must still use an aggregate function.
            $select = 'MAX(thquiza.sumgrades)';
            $join = $firstlastattemptjoin;
            $where = 'thquiza.attempt = first_last_attempts.lastattempt AND';
            break;

        case THQUIZ_GRADEAVERAGE:
            $select = 'AVG(thquiza.sumgrades)';
            $join = '';
            $where = '';
            break;

        default:
        case THQUIZ_GRADEHIGHEST:
            $select = 'MAX(thquiza.sumgrades)';
            $join = '';
            $where = '';
            break;
    }

    if ($thquiz->sumgrades >= 0.000005) {
        $finalgrade = $select . ' * ' . ($thquiz->grade / $thquiz->sumgrades);
    } else {
        $finalgrade = '0';
    }
    $param['thquizid'] = $thquiz->id;
    $param['thquizid2'] = $thquiz->id;
    $param['thquizid3'] = $thquiz->id;
    $param['thquizid4'] = $thquiz->id;
    $param['statefinished'] = thquiz_attempt::FINISHED;
    $param['statefinished2'] = thquiz_attempt::FINISHED;
    $finalgradesubquery = "
            SELECT thquiza.userid, $finalgrade AS newgrade
            FROM {thquiz_attempts} thquiza
            $join
            WHERE
                $where
                thquiza.state = :statefinished AND
                thquiza.preview = 0 AND
                thquiza.thquiz = :thquizid3
            GROUP BY thquiza.userid";

    $changedgrades = $DB->get_records_sql("
            SELECT users.userid, qg.id, qg.grade, newgrades.newgrade

            FROM (
                SELECT userid
                FROM {thquiz_grades} qg
                WHERE thquiz = :thquizid
            UNION
                SELECT DISTINCT userid
                FROM {thquiz_attempts} thquiza2
                WHERE
                    thquiza2.state = :statefinished2 AND
                    thquiza2.preview = 0 AND
                    thquiza2.thquiz = :thquizid2
            ) users

            LEFT JOIN {thquiz_grades} qg ON qg.userid = users.userid AND qg.thquiz = :thquizid4

            LEFT JOIN (
                $finalgradesubquery
            ) newgrades ON newgrades.userid = users.userid

            WHERE
                ABS(newgrades.newgrade - qg.grade) > 0.000005 OR
                ((newgrades.newgrade IS NULL OR qg.grade IS NULL) AND NOT
                          (newgrades.newgrade IS NULL AND qg.grade IS NULL))",
                // The mess on the previous line is detecting where the value is
                // NULL in one column, and NOT NULL in the other, but SQL does
                // not have an XOR operator, and MS SQL server can't cope with
                // (newgrades.newgrade IS NULL) <> (qg.grade IS NULL).
            $param);

    $timenow = time();
    $todelete = array();
    foreach ($changedgrades as $changedgrade) {

        if (is_null($changedgrade->newgrade)) {
            $todelete[] = $changedgrade->userid;

        } else if (is_null($changedgrade->grade)) {
            $toinsert = new stdClass();
            $toinsert->thquiz = $thquiz->id;
            $toinsert->userid = $changedgrade->userid;
            $toinsert->timemodified = $timenow;
            $toinsert->grade = $changedgrade->newgrade;
            $DB->insert_record('thquiz_grades', $toinsert);

        } else {
            $toupdate = new stdClass();
            $toupdate->id = $changedgrade->id;
            $toupdate->grade = $changedgrade->newgrade;
            $toupdate->timemodified = $timenow;
            $DB->update_record('thquiz_grades', $toupdate);
        }
    }

    if (!empty($todelete)) {
        list($test, $params) = $DB->get_in_or_equal($todelete);
        $DB->delete_records_select('thquiz_grades', 'thquiz = ? AND userid ' . $test,
                array_merge(array($thquiz->id), $params));
    }
}

/**
 * Return summary of the number of settings override that exist.
 *
 * To get a nice display of this, see the thquiz_override_summary_links()
 * thquiz renderer method.
 *
 * @param stdClass $thquiz the thquiz settings. Only $thquiz->id is used at the moment.
 * @param stdClass|cm_info $cm the cm object. Only $cm->course, $cm->groupmode and
 *      $cm->groupingid fields are used at the moment.
 * @param int $currentgroup if there is a concept of current group where this method is being called
 *      (e.g. a report) pass it in here. Default 0 which means no current group.
 * @return array like 'group' => 3, 'user' => 12] where 3 is the number of group overrides,
 *      and 12 is the number of user ones.
 */
function thquiz_override_summary(stdClass $thquiz, stdClass $cm, int $currentgroup = 0): array {
    global $DB;

    if ($currentgroup) {
        // Currently only interested in one group.
        $groupcount = $DB->count_records('thquiz_overrides', ['thquiz' => $thquiz->id, 'groupid' => $currentgroup]);
        $usercount = $DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {thquiz_overrides} o
                  JOIN {groups_members} gm ON o.userid = gm.userid
                 WHERE o.thquiz = ?
                   AND gm.groupid = ?
                    ", [$thquiz->id, $currentgroup]);
        return ['group' => $groupcount, 'user' => $usercount, 'mode' => 'onegroup'];
    }

    $thquizgroupmode = groups_get_activity_groupmode($cm);
    $accessallgroups = ($thquizgroupmode == NOGROUPS) ||
            has_capability('moodle/site:accessallgroups', context_module::instance($cm->id));

    if ($accessallgroups) {
        // User can see all groups.
        $groupcount = $DB->count_records_select('thquiz_overrides',
                'thquiz = ? AND groupid IS NOT NULL', [$thquiz->id]);
        $usercount = $DB->count_records_select('thquiz_overrides',
                'thquiz = ? AND userid IS NOT NULL', [$thquiz->id]);
        return ['group' => $groupcount, 'user' => $usercount, 'mode' => 'allgroups'];

    } else {
        // User can only see groups they are in.
        $groups = groups_get_activity_allowed_groups($cm);
        if (!$groups) {
            return ['group' => 0, 'user' => 0, 'mode' => 'somegroups'];
        }

        list($groupidtest, $params) = $DB->get_in_or_equal(array_keys($groups));
        $params[] = $thquiz->id;

        $groupcount = $DB->count_records_select('thquiz_overrides',
                "groupid $groupidtest AND thquiz = ?", $params);
        $usercount = $DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {thquiz_overrides} o
                  JOIN {groups_members} gm ON o.userid = gm.userid
                 WHERE gm.groupid $groupidtest
                   AND o.thquiz = ?
               ", $params);

        return ['group' => $groupcount, 'user' => $usercount, 'mode' => 'somegroups'];
    }
}

/**
 * Efficiently update check state time on all open attempts
 *
 * @param array $conditions optional restrictions on which attempts to update
 *                    Allowed conditions:
 *                      courseid => (array|int) attempts in given course(s)
 *                      userid   => (array|int) attempts for given user(s)
 *                      thquizid   => (array|int) attempts in given thquiz(s)
 *                      groupid  => (array|int) thquizzes with some override for given group(s)
 *
 */
function thquiz_update_open_attempts(array $conditions) {
    global $DB;

    foreach ($conditions as &$value) {
        if (!is_array($value)) {
            $value = array($value);
        }
    }

    $params = array();
    $wheres = array("thquiza.state IN ('inprogress', 'overdue')");
    $iwheres = array("ithquiza.state IN ('inprogress', 'overdue')");

    if (isset($conditions['courseid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['courseid'], SQL_PARAMS_NAMED, 'cid');
        $params = array_merge($params, $inparams);
        $wheres[] = "thquiza.thquiz IN (SELECT q.id FROM {thquiz} q WHERE q.course $incond)";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['courseid'], SQL_PARAMS_NAMED, 'icid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "ithquiza.thquiz IN (SELECT q.id FROM {thquiz} q WHERE q.course $incond)";
    }

    if (isset($conditions['userid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['userid'], SQL_PARAMS_NAMED, 'uid');
        $params = array_merge($params, $inparams);
        $wheres[] = "thquiza.userid $incond";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['userid'], SQL_PARAMS_NAMED, 'iuid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "ithquiza.userid $incond";
    }

    if (isset($conditions['thquizid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['thquizid'], SQL_PARAMS_NAMED, 'qid');
        $params = array_merge($params, $inparams);
        $wheres[] = "thquiza.thquiz $incond";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['thquizid'], SQL_PARAMS_NAMED, 'iqid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "ithquiza.thquiz $incond";
    }

    if (isset($conditions['groupid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['groupid'], SQL_PARAMS_NAMED, 'gid');
        $params = array_merge($params, $inparams);
        $wheres[] = "thquiza.thquiz IN (SELECT qo.thquiz FROM {thquiz_overrides} qo WHERE qo.groupid $incond)";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['groupid'], SQL_PARAMS_NAMED, 'igid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "ithquiza.thquiz IN (SELECT qo.thquiz FROM {thquiz_overrides} qo WHERE qo.groupid $incond)";
    }

    // SQL to compute timeclose and timelimit for each attempt:
    $thquizausersql = thquiz_get_attempt_usertime_sql(
            implode("\n                AND ", $iwheres));

    // SQL to compute the new timecheckstate
    $timecheckstatesql = "
          CASE WHEN thquizauser.usertimelimit = 0 AND thquizauser.usertimeclose = 0 THEN NULL
               WHEN thquizauser.usertimelimit = 0 THEN thquizauser.usertimeclose
               WHEN thquizauser.usertimeclose = 0 THEN thquiza.timestart + thquizauser.usertimelimit
               WHEN thquiza.timestart + thquizauser.usertimelimit < thquizauser.usertimeclose THEN thquiza.timestart + thquizauser.usertimelimit
               ELSE thquizauser.usertimeclose END +
          CASE WHEN thquiza.state = 'overdue' THEN thquiz.graceperiod ELSE 0 END";

    // SQL to select which attempts to process
    $attemptselect = implode("\n                         AND ", $wheres);

   /*
    * Each database handles updates with inner joins differently:
    *  - mysql does not allow a FROM clause
    *  - postgres and mssql allow FROM but handle table aliases differently
    *  - oracle requires a subquery
    *
    * Different code for each database.
    */

    $dbfamily = $DB->get_dbfamily();
    if ($dbfamily == 'mysql') {
        $updatesql = "UPDATE {thquiz_attempts} thquiza
                        JOIN {thquiz} thquiz ON thquiz.id = thquiza.thquiz
                        JOIN ( $thquizausersql ) thquizauser ON thquizauser.id = thquiza.id
                         SET thquiza.timecheckstate = $timecheckstatesql
                       WHERE $attemptselect";
    } else if ($dbfamily == 'postgres') {
        $updatesql = "UPDATE {thquiz_attempts} thquiza
                         SET timecheckstate = $timecheckstatesql
                        FROM {thquiz} thquiz, ( $thquizausersql ) thquizauser
                       WHERE thquiz.id = thquiza.thquiz
                         AND thquizauser.id = thquiza.id
                         AND $attemptselect";
    } else if ($dbfamily == 'mssql') {
        $updatesql = "UPDATE thquiza
                         SET timecheckstate = $timecheckstatesql
                        FROM {thquiz_attempts} thquiza
                        JOIN {thquiz} thquiz ON thquiz.id = thquiza.thquiz
                        JOIN ( $thquizausersql ) thquizauser ON thquizauser.id = thquiza.id
                       WHERE $attemptselect";
    } else {
        // oracle, sqlite and others
        $updatesql = "UPDATE {thquiz_attempts} thquiza
                         SET timecheckstate = (
                           SELECT $timecheckstatesql
                             FROM {thquiz} thquiz, ( $thquizausersql ) thquizauser
                            WHERE thquiz.id = thquiza.thquiz
                              AND thquizauser.id = thquiza.id
                         )
                         WHERE $attemptselect";
    }

    $DB->execute($updatesql, $params);
}

/**
 * Returns SQL to compute timeclose and timelimit for every attempt, taking into account user and group overrides.
 * The query used herein is very similar to the one in function thquiz_get_user_timeclose, so, in case you
 * would change either one of them, make sure to apply your changes to both.
 *
 * @param string $redundantwhereclauses extra where clauses to add to the subquery
 *      for performance. These can use the table alias ithquiza for the thquiz attempts table.
 * @return string SQL select with columns attempt.id, usertimeclose, usertimelimit.
 */
function thquiz_get_attempt_usertime_sql($redundantwhereclauses = '') {
    if ($redundantwhereclauses) {
        $redundantwhereclauses = 'WHERE ' . $redundantwhereclauses;
    }
    // The multiple qgo JOINS are necessary because we want timeclose/timelimit = 0 (unlimited) to supercede
    // any other group override
    $thquizausersql = "
          SELECT ithquiza.id,
           COALESCE(MAX(quo.timeclose), MAX(qgo1.timeclose), MAX(qgo2.timeclose), ithquiz.timeclose) AS usertimeclose,
           COALESCE(MAX(quo.timelimit), MAX(qgo3.timelimit), MAX(qgo4.timelimit), ithquiz.timelimit) AS usertimelimit

           FROM {thquiz_attempts} ithquiza
           JOIN {thquiz} ithquiz ON ithquiz.id = ithquiza.thquiz
      LEFT JOIN {thquiz_overrides} quo ON quo.thquiz = ithquiza.thquiz AND quo.userid = ithquiza.userid
      LEFT JOIN {groups_members} gm ON gm.userid = ithquiza.userid
      LEFT JOIN {thquiz_overrides} qgo1 ON qgo1.thquiz = ithquiza.thquiz AND qgo1.groupid = gm.groupid AND qgo1.timeclose = 0
      LEFT JOIN {thquiz_overrides} qgo2 ON qgo2.thquiz = ithquiza.thquiz AND qgo2.groupid = gm.groupid AND qgo2.timeclose > 0
      LEFT JOIN {thquiz_overrides} qgo3 ON qgo3.thquiz = ithquiza.thquiz AND qgo3.groupid = gm.groupid AND qgo3.timelimit = 0
      LEFT JOIN {thquiz_overrides} qgo4 ON qgo4.thquiz = ithquiza.thquiz AND qgo4.groupid = gm.groupid AND qgo4.timelimit > 0
          $redundantwhereclauses
       GROUP BY ithquiza.id, ithquiz.id, ithquiz.timeclose, ithquiz.timelimit";
    return $thquizausersql;
}

/**
 * Return the attempt with the best grade for a thquiz
 *
 * Which attempt is the best depends on $thquiz->grademethod. If the grade
 * method is GRADEAVERAGE then this function simply returns the last attempt.
 * @return object         The attempt with the best grade
 * @param object $thquiz    The thquiz for which the best grade is to be calculated
 * @param array $attempts An array of all the attempts of the user at the thquiz
 */
function thquiz_calculate_best_attempt($thquiz, $attempts) {

    switch ($thquiz->grademethod) {

        case THQUIZ_ATTEMPTFIRST:
            foreach ($attempts as $attempt) {
                return $attempt;
            }
            break;

        case THQUIZ_GRADEAVERAGE: // We need to do something with it.
        case THQUIZ_ATTEMPTLAST:
            foreach ($attempts as $attempt) {
                $final = $attempt;
            }
            return $final;

        default:
        case THQUIZ_GRADEHIGHEST:
            $max = -1;
            foreach ($attempts as $attempt) {
                if ($attempt->sumgrades > $max) {
                    $max = $attempt->sumgrades;
                    $maxattempt = $attempt;
                }
            }
            return $maxattempt;
    }
}

/**
 * @return array int => lang string the options for calculating the thquiz grade
 *      from the individual attempt grades.
 */
function thquiz_get_grading_options() {
    return array(
        THQUIZ_GRADEHIGHEST => get_string('gradehighest', 'thquiz'),
        THQUIZ_GRADEAVERAGE => get_string('gradeaverage', 'thquiz'),
        THQUIZ_ATTEMPTFIRST => get_string('attemptfirst', 'thquiz'),
        THQUIZ_ATTEMPTLAST  => get_string('attemptlast', 'thquiz')
    );
}

/**
 * @param int $option one of the values THQUIZ_GRADEHIGHEST, THQUIZ_GRADEAVERAGE,
 *      THQUIZ_ATTEMPTFIRST or THQUIZ_ATTEMPTLAST.
 * @return the lang string for that option.
 */
function thquiz_get_grading_option_name($option) {
    $strings = thquiz_get_grading_options();
    return $strings[$option];
}

/**
 * @return array string => lang string the options for handling overdue thquiz
 *      attempts.
 */
function thquiz_get_overdue_handling_options() {
    return array(
        'autosubmit'  => get_string('overduehandlingautosubmit', 'thquiz'),
        'graceperiod' => get_string('overduehandlinggraceperiod', 'thquiz'),
        'autoabandon' => get_string('overduehandlingautoabandon', 'thquiz'),
    );
}

/**
 * Get the choices for what size user picture to show.
 * @return array string => lang string the options for whether to display the user's picture.
 */
function thquiz_get_user_image_options() {
    return array(
        THQUIZ_SHOWIMAGE_NONE  => get_string('shownoimage', 'thquiz'),
        THQUIZ_SHOWIMAGE_SMALL => get_string('showsmallimage', 'thquiz'),
        THQUIZ_SHOWIMAGE_LARGE => get_string('showlargeimage', 'thquiz'),
    );
}

/**
 * Return an user's timeclose for all thquizzes in a course, hereby taking into account group and user overrides.
 *
 * @param int $courseid the course id.
 * @return object An object with of all thquizids and close unixdates in this course, taking into account the most lenient
 * overrides, if existing and 0 if no close date is set.
 */
function thquiz_get_user_timeclose($courseid) {
    global $DB, $USER;

    // For teacher and manager/admins return timeclose.
    if (has_capability('moodle/course:update', context_course::instance($courseid))) {
        $sql = "SELECT thquiz.id, thquiz.timeclose AS usertimeclose
                  FROM {thquiz} thquiz
                 WHERE thquiz.course = :courseid";

        $results = $DB->get_records_sql($sql, array('courseid' => $courseid));
        return $results;
    }

    $sql = "SELECT q.id,
  COALESCE(v.userclose, v.groupclose, q.timeclose, 0) AS usertimeclose
  FROM (
      SELECT thquiz.id as thquizid,
             MAX(quo.timeclose) AS userclose, MAX(qgo.timeclose) AS groupclose
       FROM {thquiz} thquiz
  LEFT JOIN {thquiz_overrides} quo on thquiz.id = quo.thquiz AND quo.userid = :userid
  LEFT JOIN {groups_members} gm ON gm.userid = :useringroupid
  LEFT JOIN {thquiz_overrides} qgo on thquiz.id = qgo.thquiz AND qgo.groupid = gm.groupid
      WHERE thquiz.course = :courseid
   GROUP BY thquiz.id) v
       JOIN {thquiz} q ON q.id = v.thquizid";

    $results = $DB->get_records_sql($sql, array('userid' => $USER->id, 'useringroupid' => $USER->id, 'courseid' => $courseid));
    return $results;

}

/**
 * Get the choices to offer for the 'Questions per page' option.
 * @return array int => string.
 */
function thquiz_questions_per_page_options() {
    $pageoptions = array();
    $pageoptions[0] = get_string('neverallononepage', 'thquiz');
    $pageoptions[1] = get_string('everyquestion', 'thquiz');
    for ($i = 2; $i <= THQUIZ_MAX_QPP_OPTION; ++$i) {
        $pageoptions[$i] = get_string('everynquestions', 'thquiz', $i);
    }
    return $pageoptions;
}

/**
 * Get the human-readable name for a thquiz attempt state.
 * @param string $state one of the state constants like {@link thquiz_attempt::IN_PROGRESS}.
 * @return string The lang string to describe that state.
 */
function thquiz_attempt_state_name($state) {
    switch ($state) {
        case thquiz_attempt::IN_PROGRESS:
            return get_string('stateinprogress', 'thquiz');
        case thquiz_attempt::OVERDUE:
            return get_string('stateoverdue', 'thquiz');
        case thquiz_attempt::FINISHED:
            return get_string('statefinished', 'thquiz');
        case thquiz_attempt::ABANDONED:
            return get_string('stateabandoned', 'thquiz');
        default:
            throw new coding_exception('Unknown thquiz attempt state.');
    }
}

// Other thquiz functions ////////////////////////////////////////////////////////

/**
 * @param object $thquiz the thquiz.
 * @param int $cmid the course_module object for this thquiz.
 * @param object $question the question.
 * @param string $returnurl url to return to after action is done.
 * @param int $variant which question variant to preview (optional).
 * @param bool $random if question is random, true.
 * @return string html for a number of icons linked to action pages for a
 * question - preview and edit / view icons depending on user capabilities.
 */
function thquiz_question_action_icons($thquiz, $cmid, $question, $returnurl, $variant = null) {
    $html = '';
    if ($question->qtype !== 'random') {
        $html = thquiz_question_preview_button($thquiz, $question, false, $variant);
    }
    $html .= thquiz_question_edit_button($cmid, $question, $returnurl);
    return $html;
}

/**
 * @param int $cmid the course_module.id for this thquiz.
 * @param object $question the question.
 * @param string $returnurl url to return to after action is done.
 * @param string $contentbeforeicon some HTML content to be added inside the link, before the icon.
 * @return the HTML for an edit icon, view icon, or nothing for a question
 *      (depending on permissions).
 */
function thquiz_question_edit_button($cmid, $question, $returnurl, $contentaftericon = '') {
    global $CFG, $OUTPUT;

    // Minor efficiency saving. Only get strings once, even if there are a lot of icons on one page.
    static $stredit = null;
    static $strview = null;
    if ($stredit === null) {
        $stredit = get_string('edit');
        $strview = get_string('view');
    }

    // What sort of icon should we show?
    $action = '';
    if (!empty($question->id) &&
            (question_has_capability_on($question, 'edit') ||
                    question_has_capability_on($question, 'move'))) {
        $action = $stredit;
        $icon = 't/edit';
    } else if (!empty($question->id) &&
            question_has_capability_on($question, 'view')) {
        $action = $strview;
        $icon = 'i/info';
    }

    // Build the icon.
    if ($action) {
        if ($returnurl instanceof moodle_url) {
            $returnurl = $returnurl->out_as_local_url(false);
        }
        $questionparams = array('returnurl' => $returnurl, 'cmid' => $cmid, 'id' => $question->id);
        $questionurl = new moodle_url("$CFG->wwwroot/question/bank/editquestion/question.php", $questionparams);
        return '<a title="' . $action . '" href="' . $questionurl->out() . '" class="questioneditbutton">' .
                $OUTPUT->pix_icon($icon, $action) . $contentaftericon .
                '</a>';
    } else if ($contentaftericon) {
        return '<span class="questioneditbutton">' . $contentaftericon . '</span>';
    } else {
        return '';
    }
}

/**
 * @param object $thquiz the thquiz settings
 * @param object $question the question
 * @param int $variant which question variant to preview (optional).
 * @param int $restartversion version of the question to use when restarting the preview.
 * @return moodle_url to preview this question with the options from this thquiz.
 */
function thquiz_question_preview_url($thquiz, $question, $variant = null, $restartversion = null) {
    // Get the appropriate display options.
    $displayoptions = mod_thquiz_display_options::make_from_thquiz($thquiz,
            mod_thquiz_display_options::DURING);

    $maxmark = null;
    if (isset($question->maxmark)) {
        $maxmark = $question->maxmark;
    }

    // Work out the correcte preview URL.
    return \qbank_previewquestion\helper::question_preview_url($question->id, $thquiz->preferredbehaviour,
            $maxmark, $displayoptions, $variant, null, null, $restartversion);
}

/**
 * @param object $thquiz the thquiz settings
 * @param object $question the question
 * @param bool $label if true, show the preview question label after the icon
 * @param int $variant which question variant to preview (optional).
 * @param bool $random if question is random, true.
 * @return string the HTML for a preview question icon.
 */
function thquiz_question_preview_button($thquiz, $question, $label = false, $variant = null, $random = null) {
    global $PAGE;
    if (!question_has_capability_on($question, 'use')) {
        return '';
    }
    $structure = thquiz::create($thquiz->id)->get_structure();
    if (!empty($question->slot)) {
        $requestedversion = $structure->get_slot_by_number($question->slot)->requestedversion
                ?? question_preview_options::ALWAYS_LATEST;
    } else {
        $requestedversion = question_preview_options::ALWAYS_LATEST;
    }
    return $PAGE->get_renderer('mod_thquiz', 'edit')->question_preview_icon(
            $thquiz, $question, $label, $variant, $requestedversion);
}

/**
 * @param object $attempt the attempt.
 * @param object $context the thquiz context.
 * @return int whether flags should be shown/editable to the current user for this attempt.
 */
function thquiz_get_flag_option($attempt, $context) {
    global $USER;
    if (!has_capability('moodle/question:flag', $context)) {
        return question_display_options::HIDDEN;
    } else if ($attempt->userid == $USER->id) {
        return question_display_options::EDITABLE;
    } else {
        return question_display_options::VISIBLE;
    }
}

/**
 * Work out what state this thquiz attempt is in - in the sense used by
 * thquiz_get_review_options, not in the sense of $attempt->state.
 * @param object $thquiz the thquiz settings
 * @param object $attempt the thquiz_attempt database row.
 * @return int one of the mod_thquiz_display_options::DURING,
 *      IMMEDIATELY_AFTER, LATER_WHILE_OPEN or AFTER_CLOSE constants.
 */
function thquiz_attempt_state($thquiz, $attempt) {
    if ($attempt->state == thquiz_attempt::IN_PROGRESS) {
        return mod_thquiz_display_options::DURING;
    } else if ($thquiz->timeclose && time() >= $thquiz->timeclose) {
        return mod_thquiz_display_options::AFTER_CLOSE;
    } else if (time() < $attempt->timefinish + 120) {
        return mod_thquiz_display_options::IMMEDIATELY_AFTER;
    } else {
        return mod_thquiz_display_options::LATER_WHILE_OPEN;
    }
}

/**
 * The the appropraite mod_thquiz_display_options object for this attempt at this
 * thquiz right now.
 *
 * @param stdClass $thquiz the thquiz instance.
 * @param stdClass $attempt the attempt in question.
 * @param context $context the thquiz context.
 *
 * @return mod_thquiz_display_options
 */
function thquiz_get_review_options($thquiz, $attempt, $context) {
    $options = mod_thquiz_display_options::make_from_thquiz($thquiz, thquiz_attempt_state($thquiz, $attempt));

    $options->readonly = true;
    $options->flags = thquiz_get_flag_option($attempt, $context);
    if (!empty($attempt->id)) {
        $options->questionreviewlink = new moodle_url('/mod/thquiz/reviewquestion.php',
                array('attempt' => $attempt->id));
    }

    // Show a link to the comment box only for closed attempts.
    if (!empty($attempt->id) && $attempt->state == thquiz_attempt::FINISHED && !$attempt->preview &&
            !is_null($context) && has_capability('mod/thquiz:grade', $context)) {
        $options->manualcomment = question_display_options::VISIBLE;
        $options->manualcommentlink = new moodle_url('/mod/thquiz/comment.php',
                array('attempt' => $attempt->id));
    }

    if (!is_null($context) && !$attempt->preview &&
            has_capability('mod/thquiz:viewreports', $context) &&
            has_capability('moodle/grade:viewhidden', $context)) {
        // People who can see reports and hidden grades should be shown everything,
        // except during preview when teachers want to see what students see.
        $options->attempt = question_display_options::VISIBLE;
        $options->correctness = question_display_options::VISIBLE;
        $options->marks = question_display_options::MARK_AND_MAX;
        $options->feedback = question_display_options::VISIBLE;
        $options->numpartscorrect = question_display_options::VISIBLE;
        $options->manualcomment = question_display_options::VISIBLE;
        $options->generalfeedback = question_display_options::VISIBLE;
        $options->rightanswer = question_display_options::VISIBLE;
        $options->overallfeedback = question_display_options::VISIBLE;
        $options->history = question_display_options::VISIBLE;
        $options->userinfoinhistory = $attempt->userid;

    }

    return $options;
}

/**
 * Combines the review options from a number of different thquiz attempts.
 * Returns an array of two ojects, so the suggested way of calling this
 * funciton is:
 * list($someoptions, $alloptions) = thquiz_get_combined_reviewoptions(...)
 *
 * @param object $thquiz the thquiz instance.
 * @param array $attempts an array of attempt objects.
 *
 * @return array of two options objects, one showing which options are true for
 *          at least one of the attempts, the other showing which options are true
 *          for all attempts.
 */
function thquiz_get_combined_reviewoptions($thquiz, $attempts) {
    $fields = array('feedback', 'generalfeedback', 'rightanswer', 'overallfeedback');
    $someoptions = new stdClass();
    $alloptions = new stdClass();
    foreach ($fields as $field) {
        $someoptions->$field = false;
        $alloptions->$field = true;
    }
    $someoptions->marks = question_display_options::HIDDEN;
    $alloptions->marks = question_display_options::MARK_AND_MAX;

    // This shouldn't happen, but we need to prevent reveal information.
    if (empty($attempts)) {
        return array($someoptions, $someoptions);
    }

    foreach ($attempts as $attempt) {
        $attemptoptions = mod_thquiz_display_options::make_from_thquiz($thquiz,
                thquiz_attempt_state($thquiz, $attempt));
        foreach ($fields as $field) {
            $someoptions->$field = $someoptions->$field || $attemptoptions->$field;
            $alloptions->$field = $alloptions->$field && $attemptoptions->$field;
        }
        $someoptions->marks = max($someoptions->marks, $attemptoptions->marks);
        $alloptions->marks = min($alloptions->marks, $attemptoptions->marks);
    }
    return array($someoptions, $alloptions);
}

// Functions for sending notification messages /////////////////////////////////

/**
 * Sends a confirmation message to the student confirming that the attempt was processed.
 *
 * @param object $a lots of useful information that can be used in the message
 *      subject and body.
 * @param bool $studentisonline is the student currently interacting with Moodle?
 *
 * @return int|false as for {@link message_send()}.
 */
function thquiz_send_confirmation($recipient, $a, $studentisonline) {

    // Add information about the recipient to $a.
    // Don't do idnumber. we want idnumber to be the submitter's idnumber.
    $a->username     = fullname($recipient);
    $a->userusername = $recipient->username;

    // Prepare the message.
    $eventdata = new \core\message\message();
    $eventdata->courseid          = $a->courseid;
    $eventdata->component         = 'mod_thquiz';
    $eventdata->name              = 'confirmation';
    $eventdata->notification      = 1;

    $eventdata->userfrom          = core_user::get_noreply_user();
    $eventdata->userto            = $recipient;
    $eventdata->subject           = get_string('emailconfirmsubject', 'thquiz', $a);

    if ($studentisonline) {
        $eventdata->fullmessage = get_string('emailconfirmbody', 'thquiz', $a);
    } else {
        $eventdata->fullmessage = get_string('emailconfirmbodyautosubmit', 'thquiz', $a);
    }

    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';

    $eventdata->smallmessage      = get_string('emailconfirmsmall', 'thquiz', $a);
    $eventdata->contexturl        = $a->thquizurl;
    $eventdata->contexturlname    = $a->thquizname;
    $eventdata->customdata        = [
        'cmid' => $a->thquizcmid,
        'instance' => $a->thquizid,
        'attemptid' => $a->attemptid,
    ];

    // ... and send it.
    return message_send($eventdata);
}

/**
 * Sends notification messages to the interested parties that assign the role capability
 *
 * @param object $recipient user object of the intended recipient
 * @param object $a associative array of replaceable fields for the templates
 *
 * @return int|false as for {@link message_send()}.
 */
function thquiz_send_notification($recipient, $submitter, $a) {
    global $PAGE;

    // Recipient info for template.
    $a->useridnumber = $recipient->idnumber;
    $a->username     = fullname($recipient);
    $a->userusername = $recipient->username;

    // Prepare the message.
    $eventdata = new \core\message\message();
    $eventdata->courseid          = $a->courseid;
    $eventdata->component         = 'mod_thquiz';
    $eventdata->name              = 'submission';
    $eventdata->notification      = 1;

    $eventdata->userfrom          = $submitter;
    $eventdata->userto            = $recipient;
    $eventdata->subject           = get_string('emailnotifysubject', 'thquiz', $a);
    $eventdata->fullmessage       = get_string('emailnotifybody', 'thquiz', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';

    $eventdata->smallmessage      = get_string('emailnotifysmall', 'thquiz', $a);
    $eventdata->contexturl        = $a->thquizreviewurl;
    $eventdata->contexturlname    = $a->thquizname;
    $userpicture = new user_picture($submitter);
    $userpicture->size = 1; // Use f1 size.
    $userpicture->includetoken = $recipient->id; // Generate an out-of-session token for the user receiving the message.
    $eventdata->customdata        = [
        'cmid' => $a->thquizcmid,
        'instance' => $a->thquizid,
        'attemptid' => $a->attemptid,
        'notificationiconurl' => $userpicture->get_url($PAGE)->out(false),
    ];

    // ... and send it.
    return message_send($eventdata);
}

/**
 * Send all the requried messages when a thquiz attempt is submitted.
 *
 * @param object $course the course
 * @param object $thquiz the thquiz
 * @param object $attempt this attempt just finished
 * @param object $context the thquiz context
 * @param object $cm the coursemodule for this thquiz
 * @param bool $studentisonline is the student currently interacting with Moodle?
 *
 * @return bool true if all necessary messages were sent successfully, else false.
 */
function thquiz_send_notification_messages($course, $thquiz, $attempt, $context, $cm, $studentisonline) {
    global $CFG, $DB;

    // Do nothing if required objects not present.
    if (empty($course) or empty($thquiz) or empty($attempt) or empty($context)) {
        throw new coding_exception('$course, $thquiz, $attempt, $context and $cm must all be set.');
    }

    $submitter = $DB->get_record('user', array('id' => $attempt->userid), '*', MUST_EXIST);

    // Check for confirmation required.
    $sendconfirm = false;
    $notifyexcludeusers = '';
    if (has_capability('mod/thquiz:emailconfirmsubmission', $context, $submitter, false)) {
        $notifyexcludeusers = $submitter->id;
        $sendconfirm = true;
    }

    // Check for notifications required.
    $notifyfields = 'u.id, u.username, u.idnumber, u.email, u.emailstop, u.lang,
            u.timezone, u.mailformat, u.maildisplay, u.auth, u.suspended, u.deleted, ';
    $userfieldsapi = \core_user\fields::for_name();
    $notifyfields .= $userfieldsapi->get_sql('u', false, '', '', false)->selects;
    $groups = groups_get_all_groups($course->id, $submitter->id, $cm->groupingid);
    if (is_array($groups) && count($groups) > 0) {
        $groups = array_keys($groups);
    } else if (groups_get_activity_groupmode($cm, $course) != NOGROUPS) {
        // If the user is not in a group, and the thquiz is set to group mode,
        // then set $groups to a non-existant id so that only users with
        // 'moodle/site:accessallgroups' get notified.
        $groups = -1;
    } else {
        $groups = '';
    }
    $userstonotify = get_users_by_capability($context, 'mod/thquiz:emailnotifysubmission',
            $notifyfields, '', '', '', $groups, $notifyexcludeusers, false, false, true);

    if (empty($userstonotify) && !$sendconfirm) {
        return true; // Nothing to do.
    }

    $a = new stdClass();
    // Course info.
    $a->courseid        = $course->id;
    $a->coursename      = $course->fullname;
    $a->courseshortname = $course->shortname;
    // Thquiz info.
    $a->thquizname        = $thquiz->name;
    $a->thquizreporturl   = $CFG->wwwroot . '/mod/thquiz/report.php?id=' . $cm->id;
    $a->thquizreportlink  = '<a href="' . $a->thquizreporturl . '">' .
            format_string($thquiz->name) . ' report</a>';
    $a->thquizurl         = $CFG->wwwroot . '/mod/thquiz/view.php?id=' . $cm->id;
    $a->thquizlink        = '<a href="' . $a->thquizurl . '">' . format_string($thquiz->name) . '</a>';
    $a->thquizid          = $thquiz->id;
    $a->thquizcmid        = $cm->id;
    // Attempt info.
    $a->submissiontime  = userdate($attempt->timefinish);
    $a->timetaken       = format_time($attempt->timefinish - $attempt->timestart);
    $a->thquizreviewurl   = $CFG->wwwroot . '/mod/thquiz/review.php?attempt=' . $attempt->id;
    $a->thquizreviewlink  = '<a href="' . $a->thquizreviewurl . '">' .
            format_string($thquiz->name) . ' review</a>';
    $a->attemptid       = $attempt->id;
    // Student who sat the thquiz info.
    $a->studentidnumber = $submitter->idnumber;
    $a->studentname     = fullname($submitter);
    $a->studentusername = $submitter->username;

    $allok = true;

    // Send notifications if required.
    if (!empty($userstonotify)) {
        foreach ($userstonotify as $recipient) {
            $allok = $allok && thquiz_send_notification($recipient, $submitter, $a);
        }
    }

    // Send confirmation if required. We send the student confirmation last, so
    // that if message sending is being intermittently buggy, which means we send
    // some but not all messages, and then try again later, then teachers may get
    // duplicate messages, but the student will always get exactly one.
    if ($sendconfirm) {
        $allok = $allok && thquiz_send_confirmation($submitter, $a, $studentisonline);
    }

    return $allok;
}

/**
 * Send the notification message when a thquiz attempt becomes overdue.
 *
 * @param thquiz_attempt $attemptobj all the data about the thquiz attempt.
 */
function thquiz_send_overdue_message($attemptobj) {
    global $CFG, $DB;

    $submitter = $DB->get_record('user', array('id' => $attemptobj->get_userid()), '*', MUST_EXIST);

    if (!$attemptobj->has_capability('mod/thquiz:emailwarnoverdue', $submitter->id, false)) {
        return; // Message not required.
    }

    if (!$attemptobj->has_response_to_at_least_one_graded_question()) {
        return; // Message not required.
    }

    // Prepare lots of useful information that admins might want to include in
    // the email message.
    $thquizname = format_string($attemptobj->get_thquiz_name());

    $deadlines = array();
    if ($attemptobj->get_thquiz()->timelimit) {
        $deadlines[] = $attemptobj->get_attempt()->timestart + $attemptobj->get_thquiz()->timelimit;
    }
    if ($attemptobj->get_thquiz()->timeclose) {
        $deadlines[] = $attemptobj->get_thquiz()->timeclose;
    }
    $duedate = min($deadlines);
    $graceend = $duedate + $attemptobj->get_thquiz()->graceperiod;

    $a = new stdClass();
    // Course info.
    $a->courseid           = $attemptobj->get_course()->id;
    $a->coursename         = format_string($attemptobj->get_course()->fullname);
    $a->courseshortname    = format_string($attemptobj->get_course()->shortname);
    // Thquiz info.
    $a->thquizname           = $thquizname;
    $a->thquizurl            = $attemptobj->view_url();
    $a->thquizlink           = '<a href="' . $a->thquizurl . '">' . $thquizname . '</a>';
    // Attempt info.
    $a->attemptduedate     = userdate($duedate);
    $a->attemptgraceend    = userdate($graceend);
    $a->attemptsummaryurl  = $attemptobj->summary_url()->out(false);
    $a->attemptsummarylink = '<a href="' . $a->attemptsummaryurl . '">' . $thquizname . ' review</a>';
    // Student's info.
    $a->studentidnumber    = $submitter->idnumber;
    $a->studentname        = fullname($submitter);
    $a->studentusername    = $submitter->username;

    // Prepare the message.
    $eventdata = new \core\message\message();
    $eventdata->courseid          = $a->courseid;
    $eventdata->component         = 'mod_thquiz';
    $eventdata->name              = 'attempt_overdue';
    $eventdata->notification      = 1;

    $eventdata->userfrom          = core_user::get_noreply_user();
    $eventdata->userto            = $submitter;
    $eventdata->subject           = get_string('emailoverduesubject', 'thquiz', $a);
    $eventdata->fullmessage       = get_string('emailoverduebody', 'thquiz', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';

    $eventdata->smallmessage      = get_string('emailoverduesmall', 'thquiz', $a);
    $eventdata->contexturl        = $a->thquizurl;
    $eventdata->contexturlname    = $a->thquizname;
    $eventdata->customdata        = [
        'cmid' => $attemptobj->get_cmid(),
        'instance' => $attemptobj->get_thquizid(),
        'attemptid' => $attemptobj->get_attemptid(),
    ];

    // Send the message.
    return message_send($eventdata);
}

/**
 * Handle the thquiz_attempt_submitted event.
 *
 * This sends the confirmation and notification messages, if required.
 *
 * @param object $event the event object.
 */
function thquiz_attempt_submitted_handler($event) {
    global $DB;

    $course  = $DB->get_record('course', array('id' => $event->courseid));
    $attempt = $event->get_record_snapshot('thquiz_attempts', $event->objectid);
    $thquiz    = $event->get_record_snapshot('thquiz', $attempt->thquiz);
    $cm      = get_coursemodule_from_id('thquiz', $event->get_context()->instanceid, $event->courseid);
    $eventdata = $event->get_data();

    if (!($course && $thquiz && $cm && $attempt)) {
        // Something has been deleted since the event was raised. Therefore, the
        // event is no longer relevant.
        return true;
    }

    // Update completion state.
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm) &&
        ($thquiz->completionattemptsexhausted || $thquiz->completionminattempts)) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $event->userid);
    }
    return thquiz_send_notification_messages($course, $thquiz, $attempt,
            context_module::instance($cm->id), $cm, $eventdata['other']['studentisonline']);
}

/**
 * Send the notification message when a thquiz attempt has been manual graded.
 *
 * @param thquiz_attempt $attemptobj Some data about the thquiz attempt.
 * @param object $userto
 * @return int|false As for message_send.
 */
function thquiz_send_notify_manual_graded_message(thquiz_attempt $attemptobj, object $userto): ?int {
    global $CFG;

    $thquizname = format_string($attemptobj->get_thquiz_name());

    $a = new stdClass();
    // Course info.
    $a->courseid           = $attemptobj->get_courseid();
    $a->coursename         = format_string($attemptobj->get_course()->fullname);
    // Thquiz info.
    $a->thquizname           = $thquizname;
    $a->thquizurl            = $CFG->wwwroot . '/mod/thquiz/view.php?id=' . $attemptobj->get_cmid();

    // Attempt info.
    $a->attempttimefinish  = userdate($attemptobj->get_attempt()->timefinish);
    // Student's info.
    $a->studentidnumber    = $userto->idnumber;
    $a->studentname        = fullname($userto);

    $eventdata = new \core\message\message();
    $eventdata->component = 'mod_thquiz';
    $eventdata->name = 'attempt_grading_complete';
    $eventdata->userfrom = core_user::get_noreply_user();
    $eventdata->userto = $userto;

    $eventdata->subject = get_string('emailmanualgradedsubject', 'thquiz', $a);
    $eventdata->fullmessage = get_string('emailmanualgradedbody', 'thquiz', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml = '';

    $eventdata->notification = 1;
    $eventdata->contexturl = $a->thquizurl;
    $eventdata->contexturlname = $a->thquizname;

    // Send the message.
    return message_send($eventdata);
}

/**
 * Handle groups_member_added event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_thquiz\group_observers::group_member_added()}.
 */
function thquiz_groups_member_added_handler($event) {
    debugging('thquiz_groups_member_added_handler() is deprecated, please use ' .
        '\mod_thquiz\group_observers::group_member_added() instead.', DEBUG_DEVELOPER);
    thquiz_update_open_attempts(array('userid'=>$event->userid, 'groupid'=>$event->groupid));
}

/**
 * Handle groups_member_removed event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_thquiz\group_observers::group_member_removed()}.
 */
function thquiz_groups_member_removed_handler($event) {
    debugging('thquiz_groups_member_removed_handler() is deprecated, please use ' .
        '\mod_thquiz\group_observers::group_member_removed() instead.', DEBUG_DEVELOPER);
    thquiz_update_open_attempts(array('userid'=>$event->userid, 'groupid'=>$event->groupid));
}

/**
 * Handle groups_group_deleted event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_thquiz\group_observers::group_deleted()}.
 */
function thquiz_groups_group_deleted_handler($event) {
    global $DB;
    debugging('thquiz_groups_group_deleted_handler() is deprecated, please use ' .
        '\mod_thquiz\group_observers::group_deleted() instead.', DEBUG_DEVELOPER);
    thquiz_process_group_deleted_in_course($event->courseid);
}

/**
 * Logic to happen when a/some group(s) has/have been deleted in a course.
 *
 * @param int $courseid The course ID.
 * @return void
 */
function thquiz_process_group_deleted_in_course($courseid) {
    global $DB;

    // It would be nice if we got the groupid that was deleted.
    // Instead, we just update all thquizzes with orphaned group overrides.
    $sql = "SELECT o.id, o.thquiz, o.groupid
              FROM {thquiz_overrides} o
              JOIN {thquiz} thquiz ON thquiz.id = o.thquiz
         LEFT JOIN {groups} grp ON grp.id = o.groupid
             WHERE thquiz.course = :courseid
               AND o.groupid IS NOT NULL
               AND grp.id IS NULL";
    $params = array('courseid' => $courseid);
    $records = $DB->get_records_sql($sql, $params);
    if (!$records) {
        return; // Nothing to do.
    }
    $DB->delete_records_list('thquiz_overrides', 'id', array_keys($records));
    $cache = cache::make('mod_thquiz', 'overrides');
    foreach ($records as $record) {
        $cache->delete("{$record->thquiz}_g_{$record->groupid}");
    }
    thquiz_update_open_attempts(['thquizid' => array_unique(array_column($records, 'thquiz'))]);
}

/**
 * Handle groups_members_removed event
 *
 * @param object $event the event object.
 * @deprecated since 2.6, see {@link \mod_thquiz\group_observers::group_member_removed()}.
 */
function thquiz_groups_members_removed_handler($event) {
    debugging('thquiz_groups_members_removed_handler() is deprecated, please use ' .
        '\mod_thquiz\group_observers::group_member_removed() instead.', DEBUG_DEVELOPER);
    if ($event->userid == 0) {
        thquiz_update_open_attempts(array('courseid'=>$event->courseid));
    } else {
        thquiz_update_open_attempts(array('courseid'=>$event->courseid, 'userid'=>$event->userid));
    }
}

/**
 * Get the information about the standard thquiz JavaScript module.
 * @return array a standard jsmodule structure.
 */
function thquiz_get_js_module() {
    global $PAGE;

    return array(
        'name' => 'mod_thquiz',
        'fullpath' => '/mod/thquiz/module.js',
        'requires' => array('base', 'dom', 'event-delegate', 'event-key',
                'core_question_engine'),
        'strings' => array(
            array('cancel', 'moodle'),
            array('flagged', 'question'),
            array('functiondisabledbysecuremode', 'thquiz'),
            array('startattempt', 'thquiz'),
            array('timesup', 'thquiz'),
        ),
    );
}


/**
 * An extension of question_display_options that includes the extra options used
 * by the thquiz.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_thquiz_display_options extends question_display_options {
    /**#@+
     * @var integer bits used to indicate various times in relation to a
     * thquiz attempt.
     */
    const DURING =            0x10000;
    const IMMEDIATELY_AFTER = 0x01000;
    const LATER_WHILE_OPEN =  0x00100;
    const AFTER_CLOSE =       0x00010;
    /**#@-*/

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $attempt = true;

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $overallfeedback = self::VISIBLE;

    /**
     * Set up the various options from the thquiz settings, and a time constant.
     * @param object $thquiz the thquiz settings.
     * @param int $one of the {@link DURING}, {@link IMMEDIATELY_AFTER},
     * {@link LATER_WHILE_OPEN} or {@link AFTER_CLOSE} constants.
     * @return mod_thquiz_display_options set up appropriately.
     */
    public static function make_from_thquiz($thquiz, $when) {
        $options = new self();

        $options->attempt = self::extract($thquiz->reviewattempt, $when, true, false);
        $options->correctness = self::extract($thquiz->reviewcorrectness, $when);
        $options->marks = self::extract($thquiz->reviewmarks, $when,
                self::MARK_AND_MAX, self::MAX_ONLY);
        $options->feedback = self::extract($thquiz->reviewspecificfeedback, $when);
        $options->generalfeedback = self::extract($thquiz->reviewgeneralfeedback, $when);
        $options->rightanswer = self::extract($thquiz->reviewrightanswer, $when);
        $options->overallfeedback = self::extract($thquiz->reviewoverallfeedback, $when);

        $options->numpartscorrect = $options->feedback;
        $options->manualcomment = $options->feedback;

        if ($thquiz->questiondecimalpoints != -1) {
            $options->markdp = $thquiz->questiondecimalpoints;
        } else {
            $options->markdp = $thquiz->decimalpoints;
        }

        return $options;
    }

    protected static function extract($bitmask, $bit,
            $whenset = self::VISIBLE, $whennotset = self::HIDDEN) {
        if ($bitmask & $bit) {
            return $whenset;
        } else {
            return $whennotset;
        }
    }
}

/**
 * A {@link qubaid_condition} for finding all the question usages belonging to
 * a particular thquiz.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qubaids_for_thquiz extends qubaid_join {
    public function __construct($thquizid, $includepreviews = true, $onlyfinished = false) {
        $where = 'thquiza.thquiz = :thquizathquiz';
        $params = array('thquizathquiz' => $thquizid);

        if (!$includepreviews) {
            $where .= ' AND preview = 0';
        }

        if ($onlyfinished) {
            $where .= ' AND state = :statefinished';
            $params['statefinished'] = thquiz_attempt::FINISHED;
        }

        parent::__construct('{thquiz_attempts} thquiza', 'thquiza.uniqueid', $where, $params);
    }
}

/**
 * A {@link qubaid_condition} for finding all the question usages belonging to a particular user and thquiz combination.
 *
 * @copyright  2018 Andrew Nicols <andrwe@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qubaids_for_thquiz_user extends qubaid_join {
    /**
     * Constructor for this qubaid.
     *
     * @param   int     $thquizid The thquiz to search.
     * @param   int     $userid The user to filter on
     * @param   bool    $includepreviews Whether to include preview attempts
     * @param   bool    $onlyfinished Whether to only include finished attempts or not
     */
    public function __construct($thquizid, $userid, $includepreviews = true, $onlyfinished = false) {
        $where = 'thquiza.thquiz = :thquizathquiz AND thquiza.userid = :thquizauserid';
        $params = [
            'thquizathquiz' => $thquizid,
            'thquizauserid' => $userid,
        ];

        if (!$includepreviews) {
            $where .= ' AND preview = 0';
        }

        if ($onlyfinished) {
            $where .= ' AND state = :statefinished';
            $params['statefinished'] = thquiz_attempt::FINISHED;
        }

        parent::__construct('{thquiz_attempts} thquiza', 'thquiza.uniqueid', $where, $params);
    }
}

/**
 * Creates a textual representation of a question for display.
 *
 * @param object $question A question object from the database questions table
 * @param bool $showicon If true, show the question's icon with the question. False by default.
 * @param bool $showquestiontext If true (default), show question text after question name.
 *       If false, show only question name.
 * @param bool $showidnumber If true, show the question's idnumber, if any. False by default.
 * @param core_tag_tag[]|bool $showtags if array passed, show those tags. Else, if true, get and show tags,
 *       else, don't show tags (which is the default).
 * @return string HTML fragment.
 */
function thquiz_question_tostring($question, $showicon = false, $showquestiontext = true,
        $showidnumber = false, $showtags = false) {
    global $OUTPUT;
    $result = '';

    // Question name.
    $name = shorten_text(format_string($question->name), 200);
    if ($showicon) {
        $name .= print_question_icon($question) . ' ' . $name;
    }
    $result .= html_writer::span($name, 'questionname');

    // Question idnumber.
    if ($showidnumber && $question->idnumber !== null && $question->idnumber !== '') {
        $result .= ' ' . html_writer::span(
                html_writer::span(get_string('idnumber', 'question'), 'accesshide') .
                ' ' . s($question->idnumber), 'badge badge-primary');
    }

    // Question tags.
    if (is_array($showtags)) {
        $tags = $showtags;
    } else if ($showtags) {
        $tags = core_tag_tag::get_item_tags('core_question', 'question', $question->id);
    } else {
        $tags = [];
    }
    if ($tags) {
        $result .= $OUTPUT->tag_list($tags, null, 'd-inline', 0, null, true);
    }

    // Question text.
    if ($showquestiontext) {
        $questiontext = question_utils::to_plain_text($question->questiontext,
                $question->questiontextformat, ['noclean' => true, 'para' => false, 'filter' => false]);
        $questiontext = shorten_text($questiontext, 50);
        if ($questiontext) {
            $result .= ' ' . html_writer::span(s($questiontext), 'questiontext');
        }
    }

    return $result;
}

/**
 * Verify that the question exists, and the user has permission to use it.
 * Does not return. Throws an exception if the question cannot be used.
 * @param int $questionid The id of the question.
 */
function thquiz_require_question_use($questionid) {
    global $DB;
    $question = $DB->get_record('question', array('id' => $questionid), '*', MUST_EXIST);
    question_require_capability_on($question, 'use');
}

/**
 * Verify that the question exists, and the user has permission to use it.
 *
 * @deprecated in 4.1 use mod_thquiz\structure::has_use_capability(...) instead.
 *
 * @param object $thquiz the thquiz settings.
 * @param int $slot which question in the thquiz to test.
 * @return bool whether the user can use this question.
 */
function thquiz_has_question_use($thquiz, $slot) {
    global $DB;

    debugging('Deprecated. Please use mod_thquiz\structure::has_use_capability instead.');

    $sql = 'SELECT q.*
              FROM {thquiz_slots} slot
              JOIN {question_references} qre ON qre.itemid = slot.id
              JOIN {question_bank_entries} qbe ON qbe.id = qre.questionbankentryid
              JOIN {question_versions} qve ON qve.questionbankentryid = qbe.id
              JOIN {question} q ON q.id = qve.questionid
             WHERE slot.thquizid = ?
               AND slot.slot = ?
               AND qre.component = ?
               AND qre.questionarea = ?';

    $question = $DB->get_record_sql($sql, [$thquiz->id, $slot, 'mod_thquiz', 'slot']);

    if (!$question) {
        return false;
    }
    return question_has_capability_on($question, 'use');
}

/**
 * Add a question to a thquiz
 *
 * Adds a question to a thquiz by updating $thquiz as well as the
 * thquiz and thquiz_slots tables. It also adds a page break if required.
 * @param int $questionid The id of the question to be added
 * @param object $thquiz The extended thquiz object as used by edit.php
 *      This is updated by this function
 * @param int $page Which page in thquiz to add the question on. If 0 (default),
 *      add at the end
 * @param float $maxmark The maximum mark to set for this question. (Optional,
 *      defaults to question.defaultmark.
 * @return bool false if the question was already in the thquiz
 */
function thquiz_add_thquiz_question($questionid, $thquiz, $page = 0, $maxmark = null) {
    global $DB;

    if (!isset($thquiz->cmid)) {
        $cm = get_coursemodule_from_instance('thquiz', $thquiz->id, $thquiz->course);
        $thquiz->cmid = $cm->id;
    }

    // Make sue the question is not of the "random" type.
    $questiontype = $DB->get_field('question', 'qtype', array('id' => $questionid));
    if ($questiontype == 'random') {
        throw new coding_exception(
                'Adding "random" questions via thquiz_add_thquiz_question() is deprecated. Please use thquiz_add_random_questions().'
        );
    }

    $trans = $DB->start_delegated_transaction();

    $sql = "SELECT qbe.id
              FROM {thquiz_slots} slot
              JOIN {question_references} qr ON qr.itemid = slot.id
              JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
             WHERE slot.thquizid = ?
               AND qr.component = ?
               AND qr.questionarea = ?
               AND qr.usingcontextid = ?";

    $questionslots = $DB->get_records_sql($sql, [$thquiz->id, 'mod_thquiz', 'slot',
            context_module::instance($thquiz->cmid)->id]);

    $currententry = get_question_bank_entry($questionid);

    if (array_key_exists($currententry->id, $questionslots)) {
        $trans->allow_commit();
        return false;
    }

    $sql = "SELECT slot.slot, slot.page, slot.id
              FROM {thquiz_slots} slot
             WHERE slot.thquizid = ?
          ORDER BY slot.slot";

    $slots = $DB->get_records_sql($sql, [$thquiz->id]);

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

    // Add the new instance.
    $slot = new stdClass();
    $slot->thquizid = $thquiz->id;

    if ($maxmark !== null) {
        $slot->maxmark = $maxmark;
    } else {
        $slot->maxmark = $DB->get_field('question', 'defaultmark', array('id' => $questionid));
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
        $slot->slot = $lastslotbefore + 1;
        $slot->page = min($page, $maxpage + 1);

        thquiz_update_section_firstslots($thquiz->id, 1, max($lastslotbefore, 1));

    } else {
        $lastslot = end($slots);
        if ($lastslot) {
            $slot->slot = $lastslot->slot + 1;
        } else {
            $slot->slot = 1;
        }
        if ($thquiz->questionsperpage && $numonlastpage >= $thquiz->questionsperpage) {
            $slot->page = $maxpage + 1;
        } else {
            $slot->page = $maxpage;
        }
    }

    $slotid = $DB->insert_record('thquiz_slots', $slot);

    // Update or insert record in question_reference table.
    $sql = "SELECT DISTINCT qr.id, qr.itemid
              FROM {question} q
              JOIN {question_versions} qv ON q.id = qv.questionid
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              JOIN {question_references} qr ON qbe.id = qr.questionbankentryid AND qr.version = qv.version
              JOIN {thquiz_slots} qs ON qs.id = qr.itemid
             WHERE q.id = ?
               AND qs.id = ?
               AND qr.component = ?
               AND qr.questionarea = ?";
    $qreferenceitem = $DB->get_record_sql($sql, [$questionid, $slotid, 'mod_thquiz', 'slot']);

    if (!$qreferenceitem) {
        // Create a new reference record for questions created already.
        $questionreferences = new \StdClass();
        $questionreferences->usingcontextid = context_module::instance($thquiz->cmid)->id;
        $questionreferences->component = 'mod_thquiz';
        $questionreferences->questionarea = 'slot';
        $questionreferences->itemid = $slotid;
        $questionreferences->questionbankentryid = get_question_bank_entry($questionid)->id;
        $questionreferences->version = null; // Always latest.
        $DB->insert_record('question_references', $questionreferences);

    } else if ($qreferenceitem->itemid === 0 || $qreferenceitem->itemid === null) {
        $questionreferences = new \StdClass();
        $questionreferences->id = $qreferenceitem->id;
        $questionreferences->itemid = $slotid;
        $DB->update_record('question_references', $questionreferences);
    } else {
        // If the reference record exits for another thquiz.
        $questionreferences = new \StdClass();
        $questionreferences->usingcontextid = context_module::instance($thquiz->cmid)->id;
        $questionreferences->component = 'mod_thquiz';
        $questionreferences->questionarea = 'slot';
        $questionreferences->itemid = $slotid;
        $questionreferences->questionbankentryid = get_question_bank_entry($questionid)->id;
        $questionreferences->version = null; // Always latest.
        $DB->insert_record('question_references', $questionreferences);
    }

    $trans->allow_commit();

    // Log slot created event.
    $cm = get_coursemodule_from_instance('thquiz', $thquiz->id);
    $event = \mod_thquiz\event\slot_created::create([
        'context' => context_module::instance($cm->id),
        'objectid' => $slotid,
        'other' => [
            'thquizid' => $thquiz->id,
            'slotnumber' => $slot->slot,
            'page' => $slot->page
        ]
    ]);
    $event->trigger();
}

/**
 * Move all the section headings in a certain slot range by a certain offset.
 *
 * @param int $thquizid the id of a thquiz
 * @param int $direction amount to adjust section heading positions. Normally +1 or -1.
 * @param int $afterslot adjust headings that start after this slot.
 * @param int|null $beforeslot optionally, only adjust headings before this slot.
 */
function thquiz_update_section_firstslots($thquizid, $direction, $afterslot, $beforeslot = null) {
    global $DB;
    $where = 'thquizid = ? AND firstslot > ?';
    $params = [$direction, $thquizid, $afterslot];
    if ($beforeslot) {
        $where .= ' AND firstslot < ?';
        $params[] = $beforeslot;
    }
    $firstslotschanges = $DB->get_records_select_menu('thquiz_sections',
            $where, $params, '', 'firstslot, firstslot + ?');
    update_field_with_unique_index('thquiz_sections', 'firstslot', $firstslotschanges, ['thquizid' => $thquizid]);
}

/**
 * Add a random question to the thquiz at a given point.
 * @param stdClass $thquiz the thquiz settings.
 * @param int $addonpage the page on which to add the question.
 * @param int $categoryid the question category to add the question from.
 * @param int $number the number of random questions to add.
 * @param bool $includesubcategories whether to include questoins from subcategories.
 * @param int[] $tagids Array of tagids. The question that will be picked randomly should be tagged with all these tags.
 */
function thquiz_add_random_questions($thquiz, $addonpage, $categoryid, $number,
        $includesubcategories, $tagids = []) {
    global $DB;

    $category = $DB->get_record('question_categories', ['id' => $categoryid]);
    if (!$category) {
        new moodle_exception('invalidcategoryid');
    }

    $catcontext = context::instance_by_id($category->contextid);
    require_capability('moodle/question:useall', $catcontext);

    // Tags for filter condition.
    $tags = \core_tag_tag::get_bulk($tagids, 'id, name');
    $tagstrings = [];
    foreach ($tags as $tag) {
        $tagstrings[] = "{$tag->id},{$tag->name}";
    }
    // Create the selected number of random questions.
    for ($i = 0; $i < $number; $i++) {
        // Set the filter conditions.
        $filtercondition = new stdClass();
        $filtercondition->questioncategoryid = $categoryid;
        $filtercondition->includingsubcategories = $includesubcategories ? 1 : 0;
        if (!empty($tagstrings)) {
            $filtercondition->tags = $tagstrings;
        }

        if (!isset($thquiz->cmid)) {
            $cm = get_coursemodule_from_instance('thquiz', $thquiz->id, $thquiz->course);
            $thquiz->cmid = $cm->id;
        }

        // Slot data.
        $randomslotdata = new stdClass();
        $randomslotdata->thquizid = $thquiz->id;
        $randomslotdata->usingcontextid = context_module::instance($thquiz->cmid)->id;
        $randomslotdata->questionscontextid = $category->contextid;
        $randomslotdata->maxmark = 1;

        $randomslot = new \mod_thquiz\local\structure\slot_random($randomslotdata);
        $randomslot->set_thquiz($thquiz);
        $randomslot->set_filter_condition($filtercondition);
        $randomslot->insert($addonpage);
    }
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $thquiz       thquiz object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.1
 */
function thquiz_view($thquiz, $course, $cm, $context) {

    $params = array(
        'objectid' => $thquiz->id,
        'context' => $context
    );

    $event = \mod_thquiz\event\course_module_viewed::create($params);
    $event->add_record_snapshot('thquiz', $thquiz);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Validate permissions for creating a new attempt and start a new preview attempt if required.
 *
 * @param  thquiz $thquizobj thquiz object
 * @param  thquiz_access_manager $accessmanager thquiz access manager
 * @param  bool $forcenew whether was required to start a new preview attempt
 * @param  int $page page to jump to in the attempt
 * @param  bool $redirect whether to redirect or throw exceptions (for web or ws usage)
 * @return array an array containing the attempt information, access error messages and the page to jump to in the attempt
 * @throws moodle_thquiz_exception
 * @since Moodle 3.1
 */
function thquiz_validate_new_attempt(thquiz $thquizobj, thquiz_access_manager $accessmanager, $forcenew, $page, $redirect) {
    global $DB, $USER;
    $timenow = time();

    if ($thquizobj->is_preview_user() && $forcenew) {
        $accessmanager->current_attempt_finished();
    }

    // Check capabilities.
    if (!$thquizobj->is_preview_user()) {
        $thquizobj->require_capability('mod/thquiz:attempt');
    }

    // Check to see if a new preview was requested.
    if ($thquizobj->is_preview_user() && $forcenew) {
        // To force the creation of a new preview, we mark the current attempt (if any)
        // as abandoned. It will then automatically be deleted below.
        $DB->set_field('thquiz_attempts', 'state', thquiz_attempt::ABANDONED,
                array('thquiz' => $thquizobj->get_thquizid(), 'userid' => $USER->id));
    }

    // Look for an existing attempt.
    $attempts = thquiz_get_user_attempts($thquizobj->get_thquizid(), $USER->id, 'all', true);
    $lastattempt = end($attempts);

    $attemptnumber = null;
    // If an in-progress attempt exists, check password then redirect to it.
    if ($lastattempt && ($lastattempt->state == thquiz_attempt::IN_PROGRESS ||
            $lastattempt->state == thquiz_attempt::OVERDUE)) {
        $currentattemptid = $lastattempt->id;
        $messages = $accessmanager->prevent_access();

        // If the attempt is now overdue, deal with that.
        $thquizobj->create_attempt_object($lastattempt)->handle_if_time_expired($timenow, true);

        // And, if the attempt is now no longer in progress, redirect to the appropriate place.
        if ($lastattempt->state == thquiz_attempt::ABANDONED || $lastattempt->state == thquiz_attempt::FINISHED) {
            if ($redirect) {
                redirect($thquizobj->review_url($lastattempt->id));
            } else {
                throw new moodle_thquiz_exception($thquizobj, 'attemptalreadyclosed');
            }
        }

        // If the page number was not explicitly in the URL, go to the current page.
        if ($page == -1) {
            $page = $lastattempt->currentpage;
        }

    } else {
        while ($lastattempt && $lastattempt->preview) {
            $lastattempt = array_pop($attempts);
        }

        // Get number for the next or unfinished attempt.
        if ($lastattempt) {
            $attemptnumber = $lastattempt->attempt + 1;
        } else {
            $lastattempt = false;
            $attemptnumber = 1;
        }
        $currentattemptid = null;

        $messages = $accessmanager->prevent_access() +
            $accessmanager->prevent_new_attempt(count($attempts), $lastattempt);

        if ($page == -1) {
            $page = 0;
        }
    }
    return array($currentattemptid, $attemptnumber, $lastattempt, $messages, $page);
}

/**
 * Prepare and start a new attempt deleting the previous preview attempts.
 *
 * @param thquiz $thquizobj thquiz object
 * @param int $attemptnumber the attempt number
 * @param object $lastattempt last attempt object
 * @param bool $offlineattempt whether is an offline attempt or not
 * @param array $forcedrandomquestions slot number => question id. Used for random questions,
 *      to force the choice of a particular actual question. Intended for testing purposes only.
 * @param array $forcedvariants slot number => variant. Used for questions with variants,
 *      to force the choice of a particular variant. Intended for testing purposes only.
 * @param int $userid Specific user id to create an attempt for that user, null for current logged in user
 * @return object the new attempt
 * @since  Moodle 3.1
 */
function thquiz_prepare_and_start_new_attempt(thquiz $thquizobj, $attemptnumber, $lastattempt,
        $offlineattempt = false, $forcedrandomquestions = [], $forcedvariants = [], $userid = null) {
    global $DB, $USER;

    if ($userid === null) {
        $userid = $USER->id;
        $ispreviewuser = $thquizobj->is_preview_user();
    } else {
        $ispreviewuser = has_capability('mod/thquiz:preview', $thquizobj->get_context(), $userid);
    }
    // Delete any previous preview attempts belonging to this user.
    thquiz_delete_previews($thquizobj->get_thquiz(), $userid);

    $quba = question_engine::make_questions_usage_by_activity('mod_thquiz', $thquizobj->get_context());
    $quba->set_preferred_behaviour($thquizobj->get_thquiz()->preferredbehaviour);

    // Create the new attempt and initialize the question sessions
    $timenow = time(); // Update time now, in case the server is running really slowly.
    $attempt = thquiz_create_attempt($thquizobj, $attemptnumber, $lastattempt, $timenow, $ispreviewuser, $userid);

    if (!($thquizobj->get_thquiz()->attemptonlast && $lastattempt)) {
        $attempt = thquiz_start_new_attempt($thquizobj, $quba, $attempt, $attemptnumber, $timenow,
                $forcedrandomquestions, $forcedvariants);
    } else {
        $attempt = thquiz_start_attempt_built_on_last($quba, $attempt, $lastattempt);
    }

    $transaction = $DB->start_delegated_transaction();

    // Init the timemodifiedoffline for offline attempts.
    if ($offlineattempt) {
        $attempt->timemodifiedoffline = $attempt->timemodified;
    }
    $attempt = thquiz_attempt_save_started($thquizobj, $quba, $attempt);

    $transaction->allow_commit();

    return $attempt;
}

/**
 * Check if the given calendar_event is either a user or group override
 * event for thquiz.
 *
 * @param calendar_event $event The calendar event to check
 * @return bool
 */
function thquiz_is_overriden_calendar_event(\calendar_event $event) {
    global $DB;

    if (!isset($event->modulename)) {
        return false;
    }

    if ($event->modulename != 'thquiz') {
        return false;
    }

    if (!isset($event->instance)) {
        return false;
    }

    if (!isset($event->userid) && !isset($event->groupid)) {
        return false;
    }

    $overrideparams = [
        'thquiz' => $event->instance
    ];

    if (isset($event->groupid)) {
        $overrideparams['groupid'] = $event->groupid;
    } else if (isset($event->userid)) {
        $overrideparams['userid'] = $event->userid;
    }

    return $DB->record_exists('thquiz_overrides', $overrideparams);
}

/**
 * Retrieves tag information for the given list of thquiz slot ids.
 * Currently the only slots that have tags are random question slots.
 *
 * Example:
 * If we have 3 slots with id 1, 2, and 3. The first slot has two tags, the second
 * has one tag, and the third has zero tags. The return structure will look like:
 * [
 *      1 => [
 *          thquiz_slot_tags.id => { ...tag data... },
 *          thquiz_slot_tags.id => { ...tag data... },
 *      ],
 *      2 => [
 *          thquiz_slot_tags.id => { ...tag data... },
 *      ],
 *      3 => [],
 * ]
 *
 * @param int[] $slotids The list of id for the thquiz slots.
 * @return array[] List of thquiz_slot_tags records indexed by slot id.
 * @deprecated since Moodle 4.0
 * @todo Final deprecation on Moodle 4.4 MDL-72438
 */
function thquiz_retrieve_tags_for_slot_ids($slotids) {
    debugging('Method thquiz_retrieve_tags_for_slot_ids() is deprecated, ' .
        'see filtercondition->tags from the question_set_reference table.', DEBUG_DEVELOPER);
    global $DB;
    if (empty($slotids)) {
        return [];
    }

    $slottags = $DB->get_records_list('thquiz_slot_tags', 'slotid', $slotids);
    $tagsbyid = core_tag_tag::get_bulk(array_filter(array_column($slottags, 'tagid')), 'id, name');
    $tagsbyname = false; // It will be loaded later if required.
    $emptytagids = array_reduce($slotids, function($carry, $slotid) {
        $carry[$slotid] = [];
        return $carry;
    }, []);

    return array_reduce(
        $slottags,
        function($carry, $slottag) use ($slottags, $tagsbyid, $tagsbyname) {
            if (isset($tagsbyid[$slottag->tagid])) {
                // Make sure that we're returning the most updated tag name.
                $slottag->tagname = $tagsbyid[$slottag->tagid]->name;
            } else {
                if ($tagsbyname === false) {
                    // We were hoping that this query could be avoided, but life
                    // showed its other side to us!
                    $tagcollid = core_tag_area::get_collection('core', 'question');
                    $tagsbyname = core_tag_tag::get_by_name_bulk(
                        $tagcollid,
                        array_column($slottags, 'tagname'),
                        'id, name'
                    );
                }
                if (isset($tagsbyname[$slottag->tagname])) {
                    // Make sure that we're returning the current tag id that matches
                    // the given tag name.
                    $slottag->tagid = $tagsbyname[$slottag->tagname]->id;
                } else {
                    // The tag does not exist anymore (neither the tag id nor the tag name
                    // matches an existing tag).
                    // We still need to include this row in the result as some callers might
                    // be interested in these rows. An example is the editing forms that still
                    // need to display tag names even if they don't exist anymore.
                    $slottag->tagid = null;
                }
            }

            $carry[$slottag->slotid][$slottag->id] = $slottag;
            return $carry;
        },
        $emptytagids
    );
}

/**
 * Get thquiz attempt and handling error.
 *
 * @param int $attemptid the id of the current attempt.
 * @param int|null $cmid the course_module id for this thquiz.
 * @return thquiz_attempt $attemptobj all the data about the thquiz attempt.
 * @throws moodle_exception
 */
function thquiz_create_attempt_handling_errors($attemptid, $cmid = null) {
    try {
        $attempobj = thquiz_attempt::create($attemptid);
    } catch (moodle_exception $e) {
        if (!empty($cmid)) {
            list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'thquiz');
            $continuelink = new moodle_url('/mod/thquiz/view.php', array('id' => $cmid));
            $context = context_module::instance($cm->id);
            if (has_capability('mod/thquiz:preview', $context)) {
                throw new moodle_exception('attempterrorcontentchange', 'thquiz', $continuelink);
            } else {
                throw new moodle_exception('attempterrorcontentchangeforuser', 'thquiz', $continuelink);
            }
        } else {
            throw new moodle_exception('attempterrorinvalid', 'thquiz');
        }
    }
    if (!empty($cmid) && $attempobj->get_cmid() != $cmid) {
        throw new moodle_exception('invalidcoursemodule');
    } else {
        return $attempobj;
    }
}

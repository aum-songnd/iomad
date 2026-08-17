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
 * Page to edit thquizzes
 *
 * This page generally has two columns:
 * The right column lists all available questions in a chosen category and
 * allows them to be edited or more to be added. This column is only there if
 * the thquiz does not already have student attempts
 * The left column lists all questions that have been added to the current thquiz.
 * The lecturer can add questions from the right hand list to the thquiz or remove them
 *
 * The script also processes a number of actions:
 * Actions affecting a thquiz:
 * up and down  Changes the order of questions and page breaks
 * addquestion  Adds a single question to the thquiz
 * add          Adds several selected questions to the thquiz
 * addrandom    Adds a certain number of random questions to the thquiz
 * repaginate   Re-paginates the thquiz
 * delete       Removes a question from the thquiz
 * savechanges  Saves the order and grades for questions in the thquiz
 *
 * @package    mod_thquiz
 * @copyright  1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/thquiz/locallib.php');
require_once($CFG->dirroot . '/mod/thquiz/addrandomform.php');
require_once($CFG->dirroot . '/question/editlib.php');

// These params are only passed from page request to request while we stay on
// this page otherwise they would go in question_edit_setup.
$scrollpos = optional_param('scrollpos', '', PARAM_INT);

list($thispageurl, $contexts, $cmid, $cm, $thquiz, $pagevars) =
        question_edit_setup('editq', '/mod/thquiz/edit.php', true);

$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

$thquizhasattempts = thquiz_has_attempts($thquiz->id);

$PAGE->set_url($thispageurl);
$PAGE->set_secondary_active_tab("mod_thquiz_edit");

// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $thquiz->course), '*', MUST_EXIST);
$thquizobj = new thquiz($thquiz, $cm, $course);
$structure = $thquizobj->get_structure();

// You need mod/thquiz:manage in addition to question capabilities to access this page.
require_capability('mod/thquiz:manage', $contexts->lowest());

// Process commands ============================================================.

// Get the list of question ids had their check-boxes ticked.
$selectedslots = array();
$params = (array) data_submitted();
foreach ($params as $key => $value) {
    if (preg_match('!^s([0-9]+)$!', $key, $matches)) {
        $selectedslots[] = $matches[1];
    }
}

$afteractionurl = new moodle_url($thispageurl);
if ($scrollpos) {
    $afteractionurl->param('scrollpos', $scrollpos);
}

if (optional_param('repaginate', false, PARAM_BOOL) && confirm_sesskey()) {
    // Re-paginate the thquiz.
    $structure->check_can_be_edited();
    $questionsperpage = optional_param('questionsperpage', $thquiz->questionsperpage, PARAM_INT);
    thquiz_repaginate_questions($thquiz->id, $questionsperpage );
    thquiz_delete_previews($thquiz);
    redirect($afteractionurl);
}

if (($addquestion = optional_param('addquestion', 0, PARAM_INT)) && confirm_sesskey()) {
    // Add a single question to the current thquiz.
    $structure->check_can_be_edited();
    thquiz_require_question_use($addquestion);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    thquiz_add_thquiz_question($addquestion, $thquiz, $addonpage);
    thquiz_delete_previews($thquiz);
    thquiz_update_sumgrades($thquiz);
    $thispageurl->param('lastchanged', $addquestion);
    redirect($afteractionurl);
}

if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    $structure->check_can_be_edited();
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    // Add selected questions to the current thquiz.
    $rawdata = (array) data_submitted();
    foreach ($rawdata as $key => $value) { // Parse input for question ids.
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $key = $matches[1];
            thquiz_require_question_use($key);
            thquiz_add_thquiz_question($key, $thquiz, $addonpage);
        }
    }
    thquiz_delete_previews($thquiz);
    thquiz_update_sumgrades($thquiz);
    redirect($afteractionurl);
}

if ($addsectionatpage = optional_param('addsectionatpage', false, PARAM_INT)) {
    // Add a section to the thquiz.
    $structure->check_can_be_edited();
    $structure->add_section_heading($addsectionatpage);
    thquiz_delete_previews($thquiz);
    redirect($afteractionurl);
}

if ((optional_param('addrandom', false, PARAM_BOOL)) && confirm_sesskey()) {
    // Add random questions to the thquiz.
    $structure->check_can_be_edited();
    $recurse = optional_param('recurse', 0, PARAM_BOOL);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    $categoryid = required_param('categoryid', PARAM_INT);
    $randomcount = required_param('randomcount', PARAM_INT);
    thquiz_add_random_questions($thquiz, $addonpage, $categoryid, $randomcount, $recurse);

    thquiz_delete_previews($thquiz);
    thquiz_update_sumgrades($thquiz);
    redirect($afteractionurl);
}

if (optional_param('savechanges', false, PARAM_BOOL) && confirm_sesskey()) {

    // If rescaling is required save the new maximum.
    $maxgrade = unformat_float(optional_param('maxgrade', '', PARAM_RAW_TRIMMED), true);
    if (is_float($maxgrade) && $maxgrade >= 0) {
        thquiz_set_grade($maxgrade, $thquiz);
        thquiz_update_all_final_grades($thquiz);
        thquiz_update_grades($thquiz, 0, true);
    }

    redirect($afteractionurl);
}

// Log this visit.
$event = \mod_thquiz\event\edit_page_viewed::create([
    'courseid' => $course->id,
    'context' => $contexts->lowest(),
    'other' => [
        'thquizid' => $thquiz->id
    ]
]);
$event->trigger();

// Get the question bank view.
$questionbank = new mod_thquiz\question\bank\custom_view($contexts, $thispageurl, $course, $cm, $thquiz);
$questionbank->set_thquiz_has_attempts($thquizhasattempts);

// End of process commands =====================================================.

$PAGE->set_pagelayout('incourse');
$PAGE->set_pagetype('mod-thquiz-edit');

$output = $PAGE->get_renderer('mod_thquiz', 'edit');

$PAGE->set_title(get_string('editingthquizx', 'thquiz', format_string($thquiz->name)));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();
$node = $PAGE->settingsnav->find('mod_thquiz_edit', navigation_node::TYPE_SETTING);
if ($node) {
    $node->make_active();
}
echo $OUTPUT->header();

// Initialise the JavaScript.
$thquizeditconfig = new stdClass();
$thquizeditconfig->url = $thispageurl->out(true, array('qbanktool' => '0'));
$thquizeditconfig->dialoglisteners = array();
$numberoflisteners = $DB->get_field_sql("
    SELECT COALESCE(MAX(page), 1)
      FROM {thquiz_slots}
     WHERE thquizid = ?", array($thquiz->id));

for ($pageiter = 1; $pageiter <= $numberoflisteners; $pageiter++) {
    $thquizeditconfig->dialoglisteners[] = 'addrandomdialoglaunch_' . $pageiter;
}

$PAGE->requires->data_for_js('thquiz_edit_config', $thquizeditconfig);
$PAGE->requires->js('/question/qengine.js');

// Questions wrapper start.
echo html_writer::start_tag('div', array('class' => 'mod-thquiz-edit-content'));

echo $output->edit_page($thquizobj, $structure, $contexts, $thispageurl, $pagevars);

// Questions wrapper end.
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

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
 * Steps definitions related to mod_thquiz.
 *
 * @package   mod_thquiz
 * @category  test
 * @copyright 2014 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');
require_once(__DIR__ . '/../../../../question/tests/behat/behat_question_base.php');

use Behat\Gherkin\Node\TableNode as TableNode;

use Behat\Mink\Exception\ExpectationException as ExpectationException;

/**
 * Steps definitions related to mod_thquiz.
 *
 * @copyright 2014 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_thquiz extends behat_question_base {

    /**
     * Convert page names to URLs for steps like 'When I am on the "[page name]" page'.
     *
     * Recognised page names are:
     * | None so far!      |                                                              |
     *
     * @param string $page name of the page, with the component name removed e.g. 'Admin notification'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            default:
                throw new Exception('Unrecognised thquiz page type "' . $page . '."');
        }
    }

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page names are:
     * | pagetype          | name meaning                                | description                                  |
     * | View              | Thquiz name                                   | The thquiz info page (view.php)                |
     * | Edit              | Thquiz name                                   | The edit thquiz page (edit.php)                |
     * | Group overrides   | Thquiz name                                   | The manage group overrides page              |
     * | User overrides    | Thquiz name                                   | The manage user overrides page               |
     * | Grades report     | Thquiz name                                   | The overview report for a thquiz               |
     * | Responses report  | Thquiz name                                   | The responses report for a thquiz              |
     * | Manual grading report | Thquiz name                               | The manual grading report for a thquiz         |
     * | Statistics report | Thquiz name                                   | The statistics report for a thquiz             |
     * | Attempt review    | Thquiz name > username > [Attempt] attempt no | Review page for a given attempt (review.php) |
     * | Question bank     | Thquiz name                                   | The question bank page for a thquiz            |
     *
     * @param string $type identifies which type of page this is, e.g. 'Attempt review'.
     * @param string $identifier identifies the particular page, e.g. 'Test thquiz > student > Attempt 1'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        switch (strtolower($type)) {
            case 'view':
                return new moodle_url('/mod/thquiz/view.php',
                        ['id' => $this->get_cm_by_thquiz_name($identifier)->id]);

            case 'edit':
                return new moodle_url('/mod/thquiz/edit.php',
                        ['cmid' => $this->get_cm_by_thquiz_name($identifier)->id]);

            case 'group overrides':
                return new moodle_url('/mod/thquiz/overrides.php',
                    ['cmid' => $this->get_cm_by_thquiz_name($identifier)->id, 'mode' => 'group']);

            case 'user overrides':
                return new moodle_url('/mod/thquiz/overrides.php',
                    ['cmid' => $this->get_cm_by_thquiz_name($identifier)->id, 'mode' => 'user']);

            case 'grades report':
                return new moodle_url('/mod/thquiz/report.php',
                    ['id' => $this->get_cm_by_thquiz_name($identifier)->id, 'mode' => 'overview']);

            case 'responses report':
                return new moodle_url('/mod/thquiz/report.php',
                    ['id' => $this->get_cm_by_thquiz_name($identifier)->id, 'mode' => 'responses']);

            case 'statistics report':
                return new moodle_url('/mod/thquiz/report.php',
                    ['id' => $this->get_cm_by_thquiz_name($identifier)->id, 'mode' => 'statistics']);

            case 'manual grading report':
                return new moodle_url('/mod/thquiz/report.php',
                        ['id' => $this->get_cm_by_thquiz_name($identifier)->id, 'mode' => 'grading']);
            case 'attempt view':
                list($thquizname, $username, $attemptno, $pageno) = explode(' > ', $identifier);
                $pageno = intval($pageno);
                $pageno = $pageno > 0 ? $pageno - 1 : 0;
                $attemptno = (int) trim(str_replace ('Attempt', '', $attemptno));
                $thquiz = $this->get_thquiz_by_name($thquizname);
                $thquizcm = get_coursemodule_from_instance('thquiz', $thquiz->id, $thquiz->course);
                $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
                $attempt = $DB->get_record('thquiz_attempts',
                    ['thquiz' => $thquiz->id, 'userid' => $user->id, 'attempt' => $attemptno], '*', MUST_EXIST);
                return new moodle_url('/mod/thquiz/attempt.php', [
                    'attempt' => $attempt->id,
                    'cmid' => $thquizcm->id,
                    'page' => $pageno
                ]);
            case 'attempt review':
                if (substr_count($identifier, ' > ') !== 2) {
                    throw new coding_exception('For "attempt review", name must be ' .
                            '"{Thquiz name} > {username} > Attempt {attemptnumber}", ' .
                            'for example "Thquiz 1 > student > Attempt 1".');
                }
                list($thquizname, $username, $attemptno) = explode(' > ', $identifier);
                $attemptno = (int) trim(str_replace ('Attempt', '', $attemptno));
                $thquiz = $this->get_thquiz_by_name($thquizname);
                $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
                $attempt = $DB->get_record('thquiz_attempts',
                        ['thquiz' => $thquiz->id, 'userid' => $user->id, 'attempt' => $attemptno], '*', MUST_EXIST);
                return new moodle_url('/mod/thquiz/review.php', ['attempt' => $attempt->id]);

            case 'question bank':
                return new moodle_url('/question/edit.php', [
                    'cmid' => $this->get_cm_by_thquiz_name($identifier)->id,
                ]);


            default:
                throw new Exception('Unrecognised thquiz page type "' . $type . '."');
        }
    }

    /**
     * Get a thquiz by name.
     *
     * @param string $name thquiz name.
     * @return stdClass the corresponding DB row.
     */
    protected function get_thquiz_by_name(string $name): stdClass {
        global $DB;
        return $DB->get_record('thquiz', array('name' => $name), '*', MUST_EXIST);
    }

    /**
     * Get a thquiz cmid from the thquiz name.
     *
     * @param string $name thquiz name.
     * @return stdClass cm from get_coursemodule_from_instance.
     */
    protected function get_cm_by_thquiz_name(string $name): stdClass {
        $thquiz = $this->get_thquiz_by_name($name);
        return get_coursemodule_from_instance('thquiz', $thquiz->id, $thquiz->course);
    }

    /**
     * Put the specified questions on the specified pages of a given thquiz.
     *
     * The first row should be column names:
     * | question | page | maxmark | requireprevious |
     * The first two of those are required. The others are optional.
     *
     * question        needs to uniquely match a question name.
     * page            is a page number. Must start at 1, and on each following
     *                 row should be the same as the previous, or one more.
     * maxmark         What the question is marked out of. Defaults to question.defaultmark.
     * requireprevious The question can only be attempted after the previous one was completed.
     *
     * Then there should be a number of rows of data, one for each question you want to add.
     *
     * For backwards-compatibility reasons, specifying the column names is optional
     * (but strongly encouraged). If not specified, the columns are asseumed to be
     * | question | page | maxmark |.
     *
     * @param string $thquizname the name of the thquiz to add questions to.
     * @param TableNode $data information about the questions to add.
     *
     * @Given /^thquiz "([^"]*)" contains the following questions:$/
     */
    public function thquiz_contains_the_following_questions($thquizname, TableNode $data) {
        global $DB;

        $thquiz = $this->get_thquiz_by_name($thquizname);

        // Deal with backwards-compatibility, optional first row.
        $firstrow = $data->getRow(0);
        if (!in_array('question', $firstrow) && !in_array('page', $firstrow)) {
            if (count($firstrow) == 2) {
                $headings = array('question', 'page');
            } else if (count($firstrow) == 3) {
                $headings = array('question', 'page', 'maxmark');
            } else {
                throw new ExpectationException('When adding questions to a thquiz, you should give 2 or three 3 things: ' .
                        ' the question name, the page number, and optionally the maximum mark. ' .
                        count($firstrow) . ' values passed.', $this->getSession());
            }
            $rows = $data->getRows();
            array_unshift($rows, $headings);
            $data = new TableNode($rows);
        }

        // Add the questions.
        $lastpage = 0;
        foreach ($data->getHash() as $questiondata) {
            if (!array_key_exists('question', $questiondata)) {
                throw new ExpectationException('When adding questions to a thquiz, ' .
                        'the question name column is required.', $this->getSession());
            }
            if (!array_key_exists('page', $questiondata)) {
                throw new ExpectationException('When adding questions to a thquiz, ' .
                        'the page number column is required.', $this->getSession());
            }

            // Question id, category and type.
            $sql = 'SELECT q.id AS id, qbe.questioncategoryid AS category, q.qtype AS qtype
                      FROM {question} q
                      JOIN {question_versions} qv ON qv.questionid = q.id
                      JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                     WHERE q.name = :name';
            $question = $DB->get_record_sql($sql, ['name' => $questiondata['question']], MUST_EXIST);

            // Page number.
            $page = clean_param($questiondata['page'], PARAM_INT);
            if ($page <= 0 || (string) $page !== $questiondata['page']) {
                throw new ExpectationException('The page number for question "' .
                         $questiondata['question'] . '" must be a positive integer.',
                        $this->getSession());
            }
            if ($page < $lastpage || $page > $lastpage + 1) {
                throw new ExpectationException('When adding questions to a thquiz, ' .
                        'the page number for each question must either be the same, ' .
                        'or one more, then the page number for the previous question.',
                        $this->getSession());
            }
            $lastpage = $page;

            // Max mark.
            if (!array_key_exists('maxmark', $questiondata) || $questiondata['maxmark'] === '') {
                $maxmark = null;
            } else {
                $maxmark = clean_param($questiondata['maxmark'], PARAM_LOCALISEDFLOAT);
                if (!is_numeric($maxmark) || $maxmark < 0) {
                    throw new ExpectationException('The max mark for question "' .
                            $questiondata['question'] . '" must be a positive number.',
                            $this->getSession());
                }
            }

            if ($question->qtype == 'random') {
                if (!array_key_exists('includingsubcategories', $questiondata) || $questiondata['includingsubcategories'] === '') {
                    $includingsubcategories = false;
                } else {
                    $includingsubcategories = clean_param($questiondata['includingsubcategories'], PARAM_BOOL);
                }
                thquiz_add_random_questions($thquiz, $page, $question->category, 1, $includingsubcategories);
            } else {
                // Add the question.
                thquiz_add_thquiz_question($question->id, $thquiz, $page, $maxmark);
            }

            // Require previous.
            if (array_key_exists('requireprevious', $questiondata)) {
                if ($questiondata['requireprevious'] === '1') {
                    $slot = $DB->get_field('thquiz_slots', 'MAX(slot)', array('thquizid' => $thquiz->id));
                    $DB->set_field('thquiz_slots', 'requireprevious', 1,
                            array('thquizid' => $thquiz->id, 'slot' => $slot));
                } else if ($questiondata['requireprevious'] !== '' && $questiondata['requireprevious'] !== '0') {
                    throw new ExpectationException('Require previous for question "' .
                            $questiondata['question'] . '" should be 0, 1 or blank.',
                            $this->getSession());
                }
            }
        }

        thquiz_update_sumgrades($thquiz);
    }

    /**
     * Put the specified section headings to start at specified pages of a given thquiz.
     *
     * The first row should be column names:
     * | heading | firstslot | shufflequestions |
     *
     * heading   is the section heading text
     * firstslot is the slot number where the section starts
     * shuffle   whether this section is shuffled (0 or 1)
     *
     * Then there should be a number of rows of data, one for each section you want to add.
     *
     * @param string $thquizname the name of the thquiz to add sections to.
     * @param TableNode $data information about the sections to add.
     *
     * @Given /^thquiz "([^"]*)" contains the following sections:$/
     */
    public function thquiz_contains_the_following_sections($thquizname, TableNode $data) {
        global $DB;

        $thquiz = $DB->get_record('thquiz', array('name' => $thquizname), '*', MUST_EXIST);

        // Add the sections.
        $previousfirstslot = 0;
        foreach ($data->getHash() as $rownumber => $sectiondata) {
            if (!array_key_exists('heading', $sectiondata)) {
                throw new ExpectationException('When adding sections to a thquiz, ' .
                        'the heading name column is required.', $this->getSession());
            }
            if (!array_key_exists('firstslot', $sectiondata)) {
                throw new ExpectationException('When adding sections to a thquiz, ' .
                        'the firstslot name column is required.', $this->getSession());
            }
            if (!array_key_exists('shuffle', $sectiondata)) {
                throw new ExpectationException('When adding sections to a thquiz, ' .
                        'the shuffle name column is required.', $this->getSession());
            }

            if ($rownumber == 0) {
                $section = $DB->get_record('thquiz_sections', array('thquizid' => $thquiz->id), '*', MUST_EXIST);
            } else {
                $section = new stdClass();
                $section->thquizid = $thquiz->id;
            }

            // Heading.
            $section->heading = $sectiondata['heading'];

            // First slot.
            $section->firstslot = clean_param($sectiondata['firstslot'], PARAM_INT);
            if ($section->firstslot <= $previousfirstslot ||
                    (string) $section->firstslot !== $sectiondata['firstslot']) {
                throw new ExpectationException('The firstslot number for section "' .
                        $sectiondata['heading'] . '" must an integer greater than the previous section firstslot.',
                        $this->getSession());
            }
            if ($rownumber == 0 && $section->firstslot != 1) {
                throw new ExpectationException('The first section must have firstslot set to 1.',
                        $this->getSession());
            }

            // Shuffle.
            $section->shufflequestions = clean_param($sectiondata['shuffle'], PARAM_INT);
            if ((string) $section->shufflequestions !== $sectiondata['shuffle']) {
                throw new ExpectationException('The shuffle value for section "' .
                        $sectiondata['heading'] . '" must be 0 or 1.',
                        $this->getSession());
            }

            if ($rownumber == 0) {
                $DB->update_record('thquiz_sections', $section);
            } else {
                $DB->insert_record('thquiz_sections', $section);
            }
        }

        if ($section->firstslot > $DB->count_records('thquiz_slots', array('thquizid' => $thquiz->id))) {
            throw new ExpectationException('The section firstslot must be less than the total number of slots in the thquiz.',
                    $this->getSession());
        }
    }

    /**
     * Adds a question to the existing thquiz with filling the form.
     *
     * The form for creating a question should be on one page.
     *
     * @When /^I add a "(?P<question_type_string>(?:[^"]|\\")*)" question to the "(?P<thquiz_name_string>(?:[^"]|\\")*)" thquiz with:$/
     * @param string $questiontype
     * @param string $thquizname
     * @param TableNode $questiondata with data for filling the add question form
     */
    public function i_add_question_to_the_thquiz_with($questiontype, $thquizname, TableNode $questiondata) {
        $thquizname = $this->escape($thquizname);
        $addaquestion = $this->escape(get_string('addaquestion', 'thquiz'));

        $this->execute('behat_navigation::i_am_on_page_instance', [
            $thquizname,
            'mod_thquiz > Edit',
        ]);

        if ($this->running_javascript()) {
            $this->execute("behat_action_menu::i_open_the_action_menu_in", array('.slots', "css_element"));
            $this->execute("behat_action_menu::i_choose_in_the_open_action_menu", array($addaquestion));
        } else {
            $this->execute('behat_general::click_link', $addaquestion);
        }

        $this->finish_adding_question($questiontype, $questiondata);
    }

    /**
     * Set the max mark for a question on the Edit thquiz page.
     *
     * @When /^I set the max mark for question "(?P<question_name_string>(?:[^"]|\\")*)" to "(?P<new_mark_string>(?:[^"]|\\")*)"$/
     * @param string $questionname the name of the question to set the max mark for.
     * @param string $newmark the mark to set
     */
    public function i_set_the_max_mark_for_thquiz_question($questionname, $newmark) {
        $this->execute('behat_general::click_link', $this->escape(get_string('editmaxmark', 'thquiz')));

        $this->execute('behat_general::wait_until_exists', array("li input[name=maxmark]", "css_element"));

        $this->execute('behat_general::assert_page_contains_text', $this->escape(get_string('edittitleinstructions')));

        $this->execute('behat_general::i_type', [$newmark]);
        $this->execute('behat_general::i_press_named_key', ['', 'enter']);
    }

    /**
     * Open the add menu on a given page, or at the end of the Edit thquiz page.
     * @Given /^I open the "(?P<page_n_or_last_string>(?:[^"]|\\")*)" add to thquiz menu$/
     * @param string $pageorlast either "Page n" or "last".
     */
    public function i_open_the_add_to_thquiz_menu_for($pageorlast) {

        if (!$this->running_javascript()) {
            throw new DriverException('Activities actions menu not available when Javascript is disabled');
        }

        if ($pageorlast == 'last') {
            $xpath = "//div[@class = 'last-add-menu']//a[contains(@data-toggle, 'dropdown') and contains(., 'Add')]";
        } else if (preg_match('~Page (\d+)~', $pageorlast, $matches)) {
            $xpath = "//li[@id = 'page-{$matches[1]}']//a[contains(@data-toggle, 'dropdown') and contains(., 'Add')]";
        } else {
            throw new ExpectationException("The I open the add to thquiz menu step must specify either 'Page N' or 'last'.",
                $this->getSession());
        }
        $this->find('xpath', $xpath)->click();
    }

    /**
     * Check whether a particular question is on a particular page of the thquiz on the Edit thquiz page.
     * @Given /^I should see "(?P<question_name>(?:[^"]|\\")*)" on thquiz page "(?P<page_number>\d+)"$/
     * @param string $questionname the name of the question we are looking for.
     * @param number $pagenumber the page it should be found on.
     */
    public function i_should_see_on_thquiz_page($questionname, $pagenumber) {
        $xpath = "//li[contains(., '" . $this->escape($questionname) .
            "')][./preceding-sibling::li[contains(@class, 'pagenumber')][1][contains(., 'Page " .
            $pagenumber . "')]]";

        $this->execute('behat_general::should_exist', array($xpath, 'xpath_element'));
    }

    /**
     * Check whether a particular question is not on a particular page of the thquiz on the Edit thquiz page.
     * @Given /^I should not see "(?P<question_name>(?:[^"]|\\")*)" on thquiz page "(?P<page_number>\d+)"$/
     * @param string $questionname the name of the question we are looking for.
     * @param number $pagenumber the page it should be found on.
     */
    public function i_should_not_see_on_thquiz_page($questionname, $pagenumber) {
        $xpath = "//li[contains(., '" . $this->escape($questionname) .
                "')][./preceding-sibling::li[contains(@class, 'pagenumber')][1][contains(., 'Page " .
                $pagenumber . "')]]";

        $this->execute('behat_general::should_not_exist', array($xpath, 'xpath_element'));
    }

    /**
     * Check whether one question comes before another on the Edit thquiz page.
     * The two questions must be on the same page.
     * @Given /^I should see "(?P<first_q_name>(?:[^"]|\\")*)" before "(?P<second_q_name>(?:[^"]|\\")*)" on the edit thquiz page$/
     * @param string $firstquestionname the name of the question that should come first in order.
     * @param string $secondquestionname the name of the question that should come immediately after it in order.
     */
    public function i_should_see_before_on_the_edit_thquiz_page($firstquestionname, $secondquestionname) {
        $xpath = "//li[contains(., '" . $this->escape($firstquestionname) .
                "')]/following-sibling::li" .
                "[contains(., '" . $this->escape($secondquestionname) . "')]";

        $this->execute('behat_general::should_exist', array($xpath, 'xpath_element'));
    }

    /**
     * Check the number displayed alongside a question on the Edit thquiz page.
     * @Given /^"(?P<question_name>(?:[^"]|\\")*)" should have number "(?P<number>(?:[^"]|\\")*)" on the edit thquiz page$/
     * @param string $questionname the name of the question we are looking for.
     * @param number $number the number (or 'i') that should be displayed beside that question.
     */
    public function should_have_number_on_the_edit_thquiz_page($questionname, $number) {
        $xpath = "//li[contains(@class, 'slot') and contains(., '" . $this->escape($questionname) .
                "')]//span[contains(@class, 'slotnumber') and normalize-space(text()) = '" . $this->escape($number) . "']";

        $this->execute('behat_general::should_exist', array($xpath, 'xpath_element'));
    }

    /**
     * Get the xpath for a partcular add/remove page-break icon.
     * @param string $addorremoves 'Add' or 'Remove'.
     * @param string $questionname the name of the question before the icon.
     * @return string the requried xpath.
     */
    protected function get_xpath_page_break_icon_after_question($addorremoves, $questionname) {
        return "//li[contains(@class, 'slot') and contains(., '" . $this->escape($questionname) .
                "')]//a[contains(@class, 'page_split_join') and @title = '" . $addorremoves . " page break']";
    }

    /**
     * Click the add or remove page-break icon after a particular question.
     * @When /^I click on the "(Add|Remove)" page break icon after question "(?P<question_name>(?:[^"]|\\")*)"$/
     * @param string $addorremoves 'Add' or 'Remove'.
     * @param string $questionname the name of the question before the icon to click.
     */
    public function i_click_on_the_page_break_icon_after_question($addorremoves, $questionname) {
        $xpath = $this->get_xpath_page_break_icon_after_question($addorremoves, $questionname);

        $this->execute("behat_general::i_click_on", array($xpath, "xpath_element"));
    }

    /**
     * Assert the add or remove page-break icon after a particular question exists.
     * @When /^the "(Add|Remove)" page break icon after question "(?P<question_name>(?:[^"]|\\")*)" should exist$/
     * @param string $addorremoves 'Add' or 'Remove'.
     * @param string $questionname the name of the question before the icon to click.
     * @return array of steps.
     */
    public function the_page_break_icon_after_question_should_exist($addorremoves, $questionname) {
        $xpath = $this->get_xpath_page_break_icon_after_question($addorremoves, $questionname);

        $this->execute('behat_general::should_exist', array($xpath, 'xpath_element'));
    }

    /**
     * Assert the add or remove page-break icon after a particular question does not exist.
     * @When /^the "(Add|Remove)" page break icon after question "(?P<question_name>(?:[^"]|\\")*)" should not exist$/
     * @param string $addorremoves 'Add' or 'Remove'.
     * @param string $questionname the name of the question before the icon to click.
     * @return array of steps.
     */
    public function the_page_break_icon_after_question_should_not_exist($addorremoves, $questionname) {
        $xpath = $this->get_xpath_page_break_icon_after_question($addorremoves, $questionname);

        $this->execute('behat_general::should_not_exist', array($xpath, 'xpath_element'));
    }

    /**
     * Check the add or remove page-break link after a particular question contains the given parameters in its url.
     *
     * @When /^the "(Add|Remove)" page break link after question "(?P<question_name>(?:[^"]|\\")*) should contain:$/
     * @When /^the "(Add|Remove)" page break link after question "(?P<question_name>(?:[^"]|\\")*) should contain:"$/
     * @param string $addorremoves 'Add' or 'Remove'.
     * @param string $questionname the name of the question before the icon to click.
     * @param TableNode $paramdata with data for checking the page break url
     * @return array of steps.
     */
    public function the_page_break_link_after_question_should_contain($addorremoves, $questionname, $paramdata) {
        $xpath = $this->get_xpath_page_break_icon_after_question($addorremoves, $questionname);

        $this->execute("behat_general::i_click_on", array($xpath, "xpath_element"));
    }

    /**
     * Set Shuffle for shuffling questions within sections
     *
     * @param string $heading the heading of the section to change shuffle for.
     *
     * @Given /^I click on shuffle for section "([^"]*)" on the thquiz edit page$/
     */
    public function i_click_on_shuffle_for_section($heading) {
        $xpath = $this->get_xpath_for_shuffle_checkbox($heading);
        $checkbox = $this->find('xpath', $xpath);
        $this->ensure_node_is_visible($checkbox);
        $checkbox->click();
    }

    /**
     * Check the shuffle checkbox for a particular section.
     *
     * @param string $heading the heading of the section to check shuffle for
     * @param int $value whether the shuffle checkbox should be on or off.
     *
     * @Given /^shuffle for section "([^"]*)" should be "(On|Off)" on the thquiz edit page$/
     */
    public function shuffle_for_section_should_be($heading, $value) {
        $xpath = $this->get_xpath_for_shuffle_checkbox($heading);
        $checkbox = $this->find('xpath', $xpath);
        $this->ensure_node_is_visible($checkbox);
        if ($value == 'On' && !$checkbox->isChecked()) {
            $msg = "Shuffle for section '$heading' is not checked, but you are expecting it to be checked ($value). " .
                    "Check the line with: \nshuffle for section \"$heading\" should be \"$value\" on the thquiz edit page" .
                    "\nin your behat script";
            throw new ExpectationException($msg, $this->getSession());
        } else if ($value == 'Off' && $checkbox->isChecked()) {
            $msg = "Shuffle for section '$heading' is checked, but you are expecting it not to be ($value). " .
                    "Check the line with: \nshuffle for section \"$heading\" should be \"$value\" on the thquiz edit page" .
                    "\nin your behat script";
            throw new ExpectationException($msg, $this->getSession());
        }
    }

    /**
     * Return the xpath for shuffle checkbox in section heading
     * @param string $heading
     * @return string
     */
    protected function get_xpath_for_shuffle_checkbox($heading) {
         return "//div[contains(@class, 'section-heading') and contains(., '" . $this->escape($heading) .
                "')]//input[@type = 'checkbox']";
    }

    /**
     * Move a question on the Edit thquiz page by first clicking on the Move icon,
     * then clicking one of the "After ..." links.
     * @When /^I move "(?P<question_name>(?:[^"]|\\")*)" to "(?P<target>(?:[^"]|\\")*)" in the thquiz by clicking the move icon$/
     * @param string $questionname the name of the question we are looking for.
     * @param string $target the target place to move to. One of the links in the pop-up like
     *      "After Page 1" or "After Question N".
     */
    public function i_move_question_after_item_by_clicking_the_move_icon($questionname, $target) {
        $iconxpath = "//li[contains(@class, ' slot ') and contains(., '" . $this->escape($questionname) .
                "')]//span[contains(@class, 'editing_move')]";

        $this->execute("behat_general::i_click_on", array($iconxpath, "xpath_element"));
        $this->execute("behat_general::i_click_on", array($this->escape($target), "text"));
    }

    /**
     * Move a question on the Edit thquiz page by dragging a given question on top of another item.
     * @When /^I move "(?P<question_name>(?:[^"]|\\")*)" to "(?P<target>(?:[^"]|\\")*)" in the thquiz by dragging$/
     * @param string $questionname the name of the question we are looking for.
     * @param string $target the target place to move to. Ether a question name, or "Page N"
     */
    public function i_move_question_after_item_by_dragging($questionname, $target) {
        $iconxpath = "//li[contains(@class, ' slot ') and contains(., '" . $this->escape($questionname) .
                "')]//span[contains(@class, 'editing_move')]//img";
        $destinationxpath = "//li[contains(@class, ' slot ') or contains(@class, 'pagenumber ')]" .
                "[contains(., '" . $this->escape($target) . "')]";

        $this->execute('behat_general::i_drag_and_i_drop_it_in',
            array($iconxpath, 'xpath_element', $destinationxpath, 'xpath_element')
        );
    }

    /**
     * Delete a question on the Edit thquiz page by first clicking on the Delete icon,
     * then clicking one of the "After ..." links.
     * @When /^I delete "(?P<question_name>(?:[^"]|\\")*)" in the thquiz by clicking the delete icon$/
     * @param string $questionname the name of the question we are looking for.
     * @return array of steps.
     */
    public function i_delete_question_by_clicking_the_delete_icon($questionname) {
        $slotxpath = "//li[contains(@class, ' slot ') and contains(., '" . $this->escape($questionname) .
                "')]";
        $deletexpath = "//a[contains(@class, 'editing_delete')]";

        $this->execute("behat_general::i_click_on", array($slotxpath . $deletexpath, "xpath_element"));

        $this->execute('behat_general::i_click_on_in_the',
            array('Yes', "button", "Confirm", "dialogue")
        );
    }

    /**
     * Set the section heading for a given section on the Edit thquiz page
     *
     * @When /^I change thquiz section heading "(?P<section_name_string>(?:[^"]|\\")*)" to "(?P<new_section_heading_string>(?:[^"]|\\")*)"$/
     * @param string $sectionname the heading to change.
     * @param string $sectionheading the new heading to set.
     */
    public function i_set_the_section_heading_for($sectionname, $sectionheading) {
        // Empty section headings will have a default names of "Untitled heading".
        if (empty($sectionname)) {
            $sectionname = get_string('sectionnoname', 'thquiz');
        }
        $this->execute('behat_general::click_link', $this->escape("Edit heading '{$sectionname}'"));

        $this->execute('behat_general::assert_page_contains_text', $this->escape(get_string('edittitleinstructions')));

        $this->execute('behat_general::i_press_named_key', ['', 'backspace']);
        $this->execute('behat_general::i_type', [$sectionheading]);
        $this->execute('behat_general::i_press_named_key', ['', 'enter']);
    }

    /**
     * Check that a given question comes after a given section heading in the
     * thquiz navigation block.
     *
     * @Then /^I should see question "(?P<questionnumber>\d+)" in section "(?P<section_heading_string>(?:[^"]|\\")*)" in the thquiz navigation$/
     * @param int $questionnumber the number of the question to check.
     * @param string $sectionheading which section heading it should appear after.
     */
    public function i_should_see_question_in_section_in_the_thquiz_navigation($questionnumber, $sectionheading) {

        // Using xpath literal to avoid quotes problems.
        $questionnumberliteral = behat_context_helper::escape('Question ' . $questionnumber);
        $headingliteral = behat_context_helper::escape($sectionheading);

        // Split in two checkings to give more feedback in case of exception.
        $exception = new ExpectationException('Question "' . $questionnumber . '" is not in section "' .
                $sectionheading . '" in the thquiz navigation.', $this->getSession());
        $xpath = "//*[@id = 'mod_thquiz_navblock']//*[contains(concat(' ', normalize-space(@class), ' '), ' qnbutton ') and " .
                "contains(., {$questionnumberliteral}) and contains(preceding-sibling::h3[1], {$headingliteral})]";
        $this->find('xpath', $xpath);
    }

    /**
     * Helper used by user_has_attempted_with_responses,
     * user_has_started_an_attempt_at_thquiz_with_details, etc.
     *
     * @param TableNode $attemptinfo data table from the Behat step
     * @return array with two elements, $forcedrandomquestions, $forcedvariants,
     *      that can be passed to $thquizgenerator->create_attempt.
     */
    protected function extract_forced_randomisation_from_attempt_info(TableNode $attemptinfo) {
        global $DB;

        $forcedrandomquestions = [];
        $forcedvariants = [];
        foreach ($attemptinfo->getHash() as $slotinfo) {
            if (empty($slotinfo['slot'])) {
                throw new ExpectationException('When simulating a thquiz attempt, ' .
                        'the slot column is required.', $this->getSession());
            }

            if (!empty($slotinfo['actualquestion'])) {
                $forcedrandomquestions[$slotinfo['slot']] = $DB->get_field('question', 'id',
                        ['name' => $slotinfo['actualquestion']], MUST_EXIST);
            }

            if (!empty($slotinfo['variant'])) {
                $forcedvariants[$slotinfo['slot']] = (int) $slotinfo['variant'];
            }
        }
        return [$forcedrandomquestions, $forcedvariants];
    }

    /**
     * Helper used by user_has_attempted_with_responses, user_has_checked_answers_in_their_attempt_at_thquiz,
     * user_has_input_answers_in_their_attempt_at_thquiz, etc.
     *
     * @param TableNode $attemptinfo data table from the Behat step
     * @return array of responses that can be passed to $thquizgenerator->submit_responses.
     */
    protected function extract_responses_from_attempt_info(TableNode $attemptinfo) {
        $responses = [];
        foreach ($attemptinfo->getHash() as $slotinfo) {
            if (empty($slotinfo['slot'])) {
                throw new ExpectationException('When simulating a thquiz attempt, ' .
                        'the slot column is required.', $this->getSession());
            }
            if (!array_key_exists('response', $slotinfo)) {
                throw new ExpectationException('When simulating a thquiz attempt, ' .
                        'the response column is required.', $this->getSession());
            }
            $responses[$slotinfo['slot']] = $slotinfo['response'];
        }
        return $responses;
    }

    /**
     * Attempt a thquiz.
     *
     * The first row should be column names:
     * | slot | actualquestion | variant | response |
     * The first two of those are required. The others are optional.
     *
     * slot           The slot
     * actualquestion This column is optional, and is only needed if the thquiz contains
     *                random questions. If so, this will let you control which actual
     *                question gets picked when this slot is 'randomised' at the
     *                start of the attempt. If you don't specify, then one will be picked
     *                at random (which might make the response meaningless).
     *                Give the question name.
     * variant        This column is similar, and also options. It is only needed if
     *                the question that ends up in this slot returns something greater
     *                than 1 for $question->get_num_variants(). Like with actualquestion,
     *                if you specify a value here it is used the fix the 'random' choice
     *                made when the thquiz is started.
     * response       The response that was submitted. How this is interpreted depends on
     *                the question type. It gets passed to
     *                {@link core_question_generator::get_simulated_post_data_for_question_attempt()}
     *                and therefore to the un_summarise_response method of the question to decode.
     *
     * Then there should be a number of rows of data, one for each question you want to add.
     * There is no need to supply answers to all questions. If so, other qusetions will be
     * left unanswered.
     *
     * @param string $username the username of the user that will attempt.
     * @param string $thquizname the name of the thquiz the user will attempt.
     * @param TableNode $attemptinfo information about the questions to add, as above.
     * @Given /^user "([^"]*)" has attempted "([^"]*)" with responses:$/
     */
    public function user_has_attempted_with_responses($username, $thquizname, TableNode $attemptinfo) {
        global $DB;

        /** @var mod_thquiz_generator $thquizgenerator */
        $thquizgenerator = behat_util::get_data_generator()->get_plugin_generator('mod_thquiz');

        $thquizid = $DB->get_field('thquiz', 'id', ['name' => $thquizname], MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        list($forcedrandomquestions, $forcedvariants) =
                $this->extract_forced_randomisation_from_attempt_info($attemptinfo);
        $responses = $this->extract_responses_from_attempt_info($attemptinfo);

        $this->set_user($user);

        $attempt = $thquizgenerator->create_attempt($thquizid, $user->id,
                $forcedrandomquestions, $forcedvariants);

        $thquizgenerator->submit_responses($attempt->id, $responses, false, true);

        $this->set_user();
    }

    /**
     * Start a thquiz attempt without answers.
     *
     * Then there should be a number of rows of data, one for each question you want to add.
     * There is no need to supply answers to all questions. If so, other qusetions will be
     * left unanswered.
     *
     * @param string $username the username of the user that will attempt.
     * @param string $thquizname the name of the thquiz the user will attempt.
     * @Given /^user "([^"]*)" has started an attempt at thquiz "([^"]*)"$/
     */
    public function user_has_started_an_attempt_at_thquiz($username, $thquizname) {
        global $DB;

        /** @var mod_thquiz_generator $thquizgenerator */
        $thquizgenerator = behat_util::get_data_generator()->get_plugin_generator('mod_thquiz');

        $thquizid = $DB->get_field('thquiz', 'id', ['name' => $thquizname], MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $this->set_user($user);
        $thquizgenerator->create_attempt($thquizid, $user->id);
        $this->set_user();
    }

    /**
     * Start a thquiz attempt without answers.
     *
     * The supplied data table for have a row for each slot where you want
     * to force either which random question was chose, or which random variant
     * was used, as for {@link user_has_attempted_with_responses()} above.
     *
     * @param string $username the username of the user that will attempt.
     * @param string $thquizname the name of the thquiz the user will attempt.
     * @param TableNode $attemptinfo information about the questions to add, as above.
     * @Given /^user "([^"]*)" has started an attempt at thquiz "([^"]*)" randomised as follows:$/
     */
    public function user_has_started_an_attempt_at_thquiz_with_details($username, $thquizname, TableNode $attemptinfo) {
        global $DB;

        /** @var mod_thquiz_generator $thquizgenerator */
        $thquizgenerator = behat_util::get_data_generator()->get_plugin_generator('mod_thquiz');

        $thquizid = $DB->get_field('thquiz', 'id', ['name' => $thquizname], MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        list($forcedrandomquestions, $forcedvariants) =
                $this->extract_forced_randomisation_from_attempt_info($attemptinfo);

        $this->set_user($user);

        $thquizgenerator->create_attempt($thquizid, $user->id,
                $forcedrandomquestions, $forcedvariants);

        $this->set_user();
    }

    /**
     * Input answers to particular questions an existing thquiz attempt, without
     * simulating a click of the 'Check' button, if any.
     *
     * Then there should be a number of rows of data, with two columns slot and response,
     * as for {@link user_has_attempted_with_responses()} above.
     * There is no need to supply answers to all questions. If so, other questions will be
     * left unanswered.
     *
     * @param string $username the username of the user that will attempt.
     * @param string $thquizname the name of the thquiz the user will attempt.
     * @param TableNode $attemptinfo information about the questions to add, as above.
     * @throws \Behat\Mink\Exception\ExpectationException
     * @Given /^user "([^"]*)" has input answers in their attempt at thquiz "([^"]*)":$/
     */
    public function user_has_input_answers_in_their_attempt_at_thquiz($username, $thquizname, TableNode $attemptinfo) {
        global $DB;

        /** @var mod_thquiz_generator $thquizgenerator */
        $thquizgenerator = behat_util::get_data_generator()->get_plugin_generator('mod_thquiz');

        $thquizid = $DB->get_field('thquiz', 'id', ['name' => $thquizname], MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        $responses = $this->extract_responses_from_attempt_info($attemptinfo);

        $this->set_user($user);

        $attempts = thquiz_get_user_attempts($thquizid, $user->id, 'unfinished', true);
        $thquizgenerator->submit_responses(key($attempts), $responses, false, false);

        $this->set_user();
    }

    /**
     * Submit answers to questions an existing thquiz attempt, with a simulated click on the 'Check' button.
     *
     * This step should only be used with question behaviours that have have
     * a 'Check' button. Those include Interactive with multiple tires, Immediate feedback
     * and Immediate feedback with CBM.
     *
     * Then there should be a number of rows of data, with two columns slot and response,
     * as for {@link user_has_attempted_with_responses()} above.
     * There is no need to supply answers to all questions. If so, other questions will be
     * left unanswered.
     *
     * @param string $username the username of the user that will attempt.
     * @param string $thquizname the name of the thquiz the user will attempt.
     * @param TableNode $attemptinfo information about the questions to add, as above.
     * @throws \Behat\Mink\Exception\ExpectationException
     * @Given /^user "([^"]*)" has checked answers in their attempt at thquiz "([^"]*)":$/
     */
    public function user_has_checked_answers_in_their_attempt_at_thquiz($username, $thquizname, TableNode $attemptinfo) {
        global $DB;

        /** @var mod_thquiz_generator $thquizgenerator */
        $thquizgenerator = behat_util::get_data_generator()->get_plugin_generator('mod_thquiz');

        $thquizid = $DB->get_field('thquiz', 'id', ['name' => $thquizname], MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        $responses = $this->extract_responses_from_attempt_info($attemptinfo);

        $this->set_user($user);

        $attempts = thquiz_get_user_attempts($thquizid, $user->id, 'unfinished', true);
        $thquizgenerator->submit_responses(key($attempts), $responses, true, false);

        $this->set_user();
    }

    /**
     * Finish an existing thquiz attempt.
     *
     * @param string $username the username of the user that will attempt.
     * @param string $thquizname the name of the thquiz the user will attempt.
     * @Given /^user "([^"]*)" has finished an attempt at thquiz "([^"]*)"$/
     */
    public function user_has_finished_an_attempt_at_thquiz($username, $thquizname) {
        global $DB;

        $thquizid = $DB->get_field('thquiz', 'id', ['name' => $thquizname], MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        $this->set_user($user);

        $attempts = thquiz_get_user_attempts($thquizid, $user->id, 'unfinished', true);
        $attemptobj = thquiz_attempt::create(key($attempts));
        $attemptobj->process_finish(time(), true);

        $this->set_user();
    }

    /**
     * Return a list of the exact named selectors for the component.
     *
     * @return behat_component_named_selector[]
     */
    public static function get_exact_named_selectors(): array {
        return [
            new behat_component_named_selector('Edit slot',
            ["//li[contains(@class,'qtype')]//span[@class='slotnumber' and contains(., %locator%)]/.."])
        ];
    }
}

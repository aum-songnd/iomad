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
 * Administration settings definitions for the thquiz module.
 *
 * @package   mod_thquiz
 * @copyright 2010 Petr Skoda
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/thquiz/lib.php');

// First get a list of thquiz reports with there own settings pages. If there none,
// we use a simpler overall menu structure.
$reports = core_component::get_plugin_list_with_file('thquiz', 'settings.php', false);
$reportsbyname = array();
foreach ($reports as $report => $reportdir) {
    $strreportname = get_string($report . 'report', 'thquiz_'.$report);
    $reportsbyname[$strreportname] = $report;
}
core_collator::ksort($reportsbyname);

// First get a list of thquiz reports with there own settings pages. If there none,
// we use a simpler overall menu structure.
$rules = core_component::get_plugin_list_with_file('thquizaccess', 'settings.php', false);
$rulesbyname = array();
foreach ($rules as $rule => $ruledir) {
    $strrulename = get_string('pluginname', 'thquizaccess_' . $rule);
    $rulesbyname[$strrulename] = $rule;
}
core_collator::ksort($rulesbyname);

// Create the thquiz settings page.
if (empty($reportsbyname) && empty($rulesbyname)) {
    $pagetitle = get_string('modulename', 'thquiz');
} else {
    $pagetitle = get_string('generalsettings', 'admin');
}
$thquizsettings = new admin_settingpage('modsettingthquiz', $pagetitle, 'moodle/site:config');

if ($ADMIN->fulltree) {
    // Introductory explanation that all the settings are defaults for the add thquiz form.
    $thquizsettings->add(new admin_setting_heading('thquizintro', '', get_string('configintro', 'thquiz')));

    // Time limit.
    $setting = new admin_setting_configduration('thquiz/timelimit',
            get_string('timelimit', 'thquiz'), get_string('configtimelimitsec', 'thquiz'),
            '0', 60);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Delay to notify graded attempts.
    $thquizsettings->add(new admin_setting_configduration('thquiz/notifyattemptgradeddelay',
        get_string('attemptgradeddelay', 'thquiz'), get_string('attemptgradeddelay_desc', 'thquiz'), 5 * HOURSECS, HOURSECS));

    // What to do with overdue attempts.
    $setting = new mod_thquiz_admin_setting_overduehandling('thquiz/overduehandling',
            get_string('overduehandling', 'thquiz'), get_string('overduehandling_desc', 'thquiz'),
            array('value' => 'autosubmit', 'adv' => false), null);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Grace period time.
    $setting = new admin_setting_configduration('thquiz/graceperiod',
            get_string('graceperiod', 'thquiz'), get_string('graceperiod_desc', 'thquiz'),
            '86400');
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Minimum grace period used behind the scenes.
    $thquizsettings->add(new admin_setting_configduration('thquiz/graceperiodmin',
            get_string('graceperiodmin', 'thquiz'), get_string('graceperiodmin_desc', 'thquiz'),
            60, 1));

    // Number of attempts.
    $options = array(get_string('unlimited'));
    for ($i = 1; $i <= THQUIZ_MAX_ATTEMPT_OPTION; $i++) {
        $options[$i] = $i;
    }
    $setting = new admin_setting_configselect('thquiz/attempts',
            get_string('attemptsallowed', 'thquiz'), get_string('configattemptsallowed', 'thquiz'),
            0, $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Grading method.
    $setting = new mod_thquiz_admin_setting_grademethod('thquiz/grademethod',
            get_string('grademethod', 'thquiz'), get_string('configgrademethod', 'thquiz'),
            array('value' => THQUIZ_GRADEHIGHEST, 'adv' => false), null);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Maximum grade.
    $setting = new admin_setting_configtext('thquiz/maximumgrade',
            get_string('maximumgrade'), get_string('configmaximumgrade', 'thquiz'), 10, PARAM_INT);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Questions per page.
    $perpage = array();
    $perpage[0] = get_string('never');
    $perpage[1] = get_string('aftereachquestion', 'thquiz');
    for ($i = 2; $i <= THQUIZ_MAX_QPP_OPTION; ++$i) {
        $perpage[$i] = get_string('afternquestions', 'thquiz', $i);
    }
    $setting = new admin_setting_configselect('thquiz/questionsperpage',
            get_string('newpageevery', 'thquiz'), get_string('confignewpageevery', 'thquiz'),
            1, $perpage);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Navigation method.
    $setting = new admin_setting_configselect('thquiz/navmethod',
            get_string('navmethod', 'thquiz'), get_string('confignavmethod', 'thquiz'),
            THQUIZ_NAVMETHOD_FREE, thquiz_get_navigation_options());
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Shuffle within questions.
    $setting = new admin_setting_configcheckbox('thquiz/shuffleanswers',
            get_string('shufflewithin', 'thquiz'), get_string('configshufflewithin', 'thquiz'),
            1);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Preferred behaviour.
    $setting = new admin_setting_question_behaviour('thquiz/preferredbehaviour',
            get_string('howquestionsbehave', 'question'), get_string('howquestionsbehave_desc', 'thquiz'),
            'deferredfeedback');
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Can redo completed questions.
    $setting = new admin_setting_configselect('thquiz/canredoquestions',
            get_string('canredoquestions', 'thquiz'), get_string('canredoquestions_desc', 'thquiz'),
            0,
            array(0 => get_string('no'), 1 => get_string('canredoquestionsyes', 'thquiz')));
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Each attempt builds on last.
    $setting = new admin_setting_configcheckbox('thquiz/attemptonlast',
            get_string('eachattemptbuildsonthelast', 'thquiz'),
            get_string('configeachattemptbuildsonthelast', 'thquiz'),
            0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Review options.
    $thquizsettings->add(new admin_setting_heading('reviewheading',
            get_string('reviewoptionsheading', 'thquiz'), ''));
    foreach (mod_thquiz_admin_review_setting::fields() as $field => $name) {
        $default = mod_thquiz_admin_review_setting::all_on();
        $forceduring = null;
        if ($field == 'attempt') {
            $forceduring = true;
        } else if ($field == 'overallfeedback') {
            $default = $default ^ mod_thquiz_admin_review_setting::DURING;
            $forceduring = false;
        }
        $thquizsettings->add(new mod_thquiz_admin_review_setting('thquiz/review' . $field,
                $name, '', $default, $forceduring));
    }

    // Show the user's picture.
    $setting = new mod_thquiz_admin_setting_user_image('thquiz/showuserpicture',
            get_string('showuserpicture', 'thquiz'), get_string('configshowuserpicture', 'thquiz'),
            array('value' => 0, 'adv' => false), null);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Decimal places for overall grades.
    $options = array();
    for ($i = 0; $i <= THQUIZ_MAX_DECIMAL_OPTION; $i++) {
        $options[$i] = $i;
    }
    $setting = new admin_setting_configselect('thquiz/decimalpoints',
            get_string('decimalplaces', 'thquiz'), get_string('configdecimalplaces', 'thquiz'),
            2, $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Decimal places for question grades.
    $options = array(-1 => get_string('sameasoverall', 'thquiz'));
    for ($i = 0; $i <= THQUIZ_MAX_Q_DECIMAL_OPTION; $i++) {
        $options[$i] = $i;
    }
    $setting = new admin_setting_configselect('thquiz/questiondecimalpoints',
            get_string('decimalplacesquestion', 'thquiz'),
            get_string('configdecimalplacesquestion', 'thquiz'),
            -1, $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Show blocks during thquiz attempts.
    $setting = new admin_setting_configcheckbox('thquiz/showblocks',
            get_string('showblocks', 'thquiz'), get_string('configshowblocks', 'thquiz'),
            0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Password.
    $setting = new admin_setting_configpasswordunmask('thquiz/thquizpassword',
            get_string('requirepassword', 'thquiz'), get_string('configrequirepassword', 'thquiz'),
            '');
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_required_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // IP restrictions.
    $setting = new admin_setting_configtext('thquiz/subnet',
            get_string('requiresubnet', 'thquiz'), get_string('configrequiresubnet', 'thquiz'),
            '', PARAM_TEXT);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Enforced delay between attempts.
    $setting = new admin_setting_configduration('thquiz/delay1',
            get_string('delay1st2nd', 'thquiz'), get_string('configdelay1st2nd', 'thquiz'),
            0, 60);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);
    $setting = new admin_setting_configduration('thquiz/delay2',
            get_string('delaylater', 'thquiz'), get_string('configdelaylater', 'thquiz'),
            0, 60);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    // Browser security.
    $setting = new mod_thquiz_admin_setting_browsersecurity('thquiz/browsersecurity',
            get_string('showinsecurepopup', 'thquiz'), get_string('configpopup', 'thquiz'),
            array('value' => '-', 'adv' => true), null);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $thquizsettings->add($setting);

    $thquizsettings->add(new admin_setting_configtext('thquiz/initialnumfeedbacks',
            get_string('initialnumfeedbacks', 'thquiz'), get_string('initialnumfeedbacks_desc', 'thquiz'),
            2, PARAM_INT, 5));

    // Allow user to specify if setting outcomes is an advanced setting.
    if (!empty($CFG->enableoutcomes)) {
        $thquizsettings->add(new admin_setting_configcheckbox('thquiz/outcomes_adv',
            get_string('outcomesadvanced', 'thquiz'), get_string('configoutcomesadvanced', 'thquiz'),
            '0'));
    }

    // Autosave frequency.
    $thquizsettings->add(new admin_setting_configduration('thquiz/autosaveperiod',
            get_string('autosaveperiod', 'thquiz'), get_string('autosaveperiod_desc', 'thquiz'), 60, 1));
}

// Now, depending on whether any reports have their own settings page, add
// the thquiz setting page to the appropriate place in the tree.
if (empty($reportsbyname) && empty($rulesbyname)) {
    $ADMIN->add('modsettings', $thquizsettings);
} else {
    $ADMIN->add('modsettings', new admin_category('modsettingsthquizcat',
            get_string('modulename', 'thquiz'), $module->is_enabled() === false));
    $ADMIN->add('modsettingsthquizcat', $thquizsettings);

    // Add settings pages for the thquiz report subplugins.
    foreach ($reportsbyname as $strreportname => $report) {
        $reportname = $report;

        $settings = new admin_settingpage('modsettingsthquizcat'.$reportname,
                $strreportname, 'moodle/site:config', $module->is_enabled() === false);
        include($CFG->dirroot . "/mod/thquiz/report/$reportname/settings.php");
        if (!empty($settings)) {
            $ADMIN->add('modsettingsthquizcat', $settings);
        }
    }

    // Add settings pages for the thquiz access rule subplugins.
    foreach ($rulesbyname as $strrulename => $rule) {
        $settings = new admin_settingpage('modsettingsthquizcat' . $rule,
                $strrulename, 'moodle/site:config', $module->is_enabled() === false);
        include($CFG->dirroot . "/mod/thquiz/accessrule/$rule/settings.php");
        if (!empty($settings)) {
            $ADMIN->add('modsettingsthquizcat', $settings);
        }
    }
}

$settings = null; // We do not want standard settings link.

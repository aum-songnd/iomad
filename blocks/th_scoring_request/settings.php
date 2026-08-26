<?php
// This file is part of Moodle - http://moodle.org/
// (C) 2024 Your Company/Name. All rights reserved.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings->add(new admin_setting_configtext(
        'block_th_scoring_request/rec',
        get_string('setting_rec', 'block_th_scoring_request'),
        get_string('setting_rec_desc', 'block_th_scoring_request'),
        'rec'
    ));

    $settings->add(new admin_setting_configtext(
        'block_th_scoring_request/ess',
        get_string('setting_ess', 'block_th_scoring_request'),
        get_string('setting_ess_desc', 'block_th_scoring_request'),
        'ess'
    ));

}

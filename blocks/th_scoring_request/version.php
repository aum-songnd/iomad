<?php
defined('MOODLE_INTERNAL') || die;
$plugin->component = 'block_th_scoring_request';
$plugin->version = 2026012900; // YYYYMMDDXX (year, month, day, 24-hr time)
$plugin->release = 'v4.2';
$plugin->dependencies = array(
	'block_th_assign_grading' => '2025120101',
);

<?php
defined('MOODLE_INTERNAL') || die;
$plugin->component = 'block_th_scoring_collaborators';
$plugin->version = 2026041000; // YYYYMMDDXX (year, month, day, 24-hr time)
$plugin->release = 'v4.1';
$plugin->dependencies = array(
	'block_th_assign_grading' => 2025120101
);

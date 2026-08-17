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
 * Capability definitions for the thquiz module.
 *
 * @package    mod_thquiz
 * @copyright  2006 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Ability to see that the thquiz exists, and the basic information
    // about it, for example the start date and time limit.
    'mod/thquiz:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'guest' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],

    // Ability to add a new thquiz to the course.
    'mod/thquiz:addinstance' => [
        'riskbitmask' => RISK_XSS,

        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ],
        'clonepermissionsfrom' => 'moodle/course:manageactivities'
    ],

    // Ability to do the thquiz as a 'student'.
    'mod/thquiz:attempt' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'student' => CAP_ALLOW
        ]
    ],

    // Ability for a 'Student' to review their previous attempts. Review by
    // 'Teachers' is controlled by mod/thquiz:viewreports.
    'mod/thquiz:reviewmyattempts' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'student' => CAP_ALLOW
        ],
        'clonepermissionsfrom' => 'moodle/thquiz:attempt'
    ],

    // Edit the thquiz settings, add and remove questions.
    'mod/thquiz:manage' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],

    // Edit the thquiz overrides.
    'mod/thquiz:manageoverrides' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],

    // View the thquiz overrides (only checked for users who don't have mod/thquiz:manageoverrides.
    'mod/thquiz:viewoverrides' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],

    // Preview the thquiz.
    'mod/thquiz:preview' => [
        'captype' => 'write', // Only just a write.
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],

    // Manually grade and comment on student attempts at a question.
    'mod/thquiz:grade' => [
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],

    // Regrade thquizzes.
    'mod/thquiz:regrade' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ],
        'clonepermissionsfrom' =>  'mod/thquiz:grade'
    ],

    // View the thquiz reports.
    'mod/thquiz:viewreports' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],

    // Delete attempts using the overview report.
    'mod/thquiz:deleteattempts' => [
        'riskbitmask' => RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],

    // Do not have the time limit imposed. Used for accessibility legislation compliance.
    'mod/thquiz:ignoretimelimits' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => []
    ],

    // Receive a confirmation message of own thquiz submission.
    'mod/thquiz:emailconfirmsubmission' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => []
    ],

    // Receive a notification message of other peoples' thquiz submissions.
    'mod/thquiz:emailnotifysubmission' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => []
    ],

    // Receive a notification message when a thquiz attempt becomes overdue.
    'mod/thquiz:emailwarnoverdue' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => []
    ],

    // Receive a notification message when a thquiz attempt manual graded.
    'mod/thquiz:emailnotifyattemptgraded' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => []
    ],

    'mod/thquiz:viewtracking' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
];


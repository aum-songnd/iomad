<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/mobilecssedit:manage' => [
        'riskbitmask'  => RISK_CONFIG | RISK_XSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
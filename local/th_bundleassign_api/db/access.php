<?php
$capabilities = array(
	'local/th_bundleassign_api:view' => array(
		'riskbitmask' => RISK_SPAM | RISK_XSS,
		'captype' => 'read',
		'contextlevel' => CONTEXT_SYSTEM,
		'archetypes' => array(
			
		),
	),
	'local/th_bundleassign_api:seeallthings' => array(
		'riskbitmask' => RISK_SPAM | RISK_XSS,
		'captype' => 'write',
		'contextlevel' => CONTEXT_BLOCK,
		'archetypes' => array(
			'manager' => CAP_ALLOW,
		),
	),
);

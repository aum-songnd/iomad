<?php
$capabilities = array(
	'local/thlib:seeallthings' => array(
		'riskbitmask' => RISK_SPAM | RISK_XSS,
		'captype' => 'write',
		'contextlevel' => CONTEXT_BLOCK,
		'archetypes' => array(
			'manager' => CAP_ALLOW,
		),
	),
);
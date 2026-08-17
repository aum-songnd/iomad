<?php

$services = array(
    'th_bundleassign_api' => array( 
        'functions' => array('local_th_enrolcourse' ,'th_unenrol', 'test_connection','th_updateuser','th_get_users_by_combo','th_update_all_remote_course', 'th_company', 'th_brand'), 
        'requiredcapability' => 0, 
        'restrictedusers' => 0, 
        'enabled' => 1, 
        'downloadfiles' => 1,
        'uploadfiles' => 1,
        'shortname' => 'th_bundleassign_api', 
    ),
);

$functions = array(
    'local_th_enrolcourse' => array(
        'classname' => 'local_th_bundleassign_api_external',
        'methodname' => 'enrolcourse',
        'classpath' => 'local/th_bundleassign_api/externallib.php',
        'description' => 'API enrol course',
        'type' => 'write',
    ),
    'th_unenrol' => array(
        'classname' => 'local_th_bundleassign_api_external',
        'methodname' => 'unenrolcourse',
        'classpath' => 'local/th_bundleassign_api/externallib.php',
        'description' => 'API unenrol course',
        'type' => 'write',
    ),
    'test_connection' => array(
        'classname' => 'local_th_bundleassign_api_external',
        'methodname' => 'test_connection',
        'classpath' => 'local/th_bundleassign_api/externallib.php',
        'description' => 'API test connection',
        'type' => 'read',
    ),
    'th_updateuser' => array(
        'classname' => 'local_th_bundleassign_api_external',
        'methodname' => 'updateuser',
        'classpath' => 'local/th_bundleassign_api/externallib.php',
        'description' => 'API update user',
        'type' => 'read',
    ),
    'th_get_users_by_combo' => array(
        'classname' => 'local_th_bundleassign_api_external',
        'methodname' => 'get_users_by_combo', // đúng tên method trong externallib.php
        'classpath' => 'local/th_bundleassign_api/externallib.php',
        'description' => 'Get users by combo_shortname (return th_crm_code, date_assign)',
        'type' => 'read',
        'ajax' => true, // optional, có cũng được
    ),
    'th_update_all_remote_course' => array(
        'classname' => 'local_th_bundleassign_api_external',
        'methodname' => 'update_all_remote_course',
        'classpath' => 'local/th_bundleassign_api/externallib.php',
        'description' => 'Update all_remote_course by th_crm_code, userid+combo_shortname',
        'type' => 'write',
    ),
    'th_company' => array(
        'classname' => 'local_th_bundleassign_api_external',
        'methodname' => 'company',
        'classpath' => 'local/th_bundleassign_api/externallib.php',
        'description' => 'API company with POST/PUT/DELETE methods',
        'type' => 'write',
    ),
    'th_brand' => array(
        'classname' => 'local_th_bundleassign_api_external',
        'methodname' => 'brand',
        'classpath' => 'local/th_bundleassign_api/externallib.php',
        'description' => 'API send company logo, company name, company color to app',
        'type' => 'read',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    )
);

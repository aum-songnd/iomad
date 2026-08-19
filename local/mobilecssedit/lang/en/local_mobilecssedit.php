<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'Mobile app CSS inline editor';

// Setting name shown on the Mobile app appearance page.
$string['cssfilecontent']   = 'CSS file content (mobilecssurl)';
$string['editingfile']      = 'Editing file: {$a}';
$string['description']      = 'Mobile app CSS inline editor';

// Status messages.
$string['nolocalfile']      = 'The "Mobile custom CSS" (mobilecssurl) setting is not configured, or its URL does not point to a file inside the Moodle source code, so it cannot be edited directly here.';
$string['cannotwrite']      = 'Could not determine a local CSS file to write to, or the file does not exist.';
$string['notwritable']      = 'The file exists but the webserver does not have write permission to it (check filesystem permissions).';
$string['writefailed']      = 'Failed to write the file.';
$string['nopermission']     = 'You do not have permission to view or edit this CSS file. The "local/mobilecssedit:manage" capability is required (granted to Manager / site admin by default).';

// Capability (shown in Site administration > Users > Permissions > Define roles).
$string['mobilecssedit:manage'] = 'Manage (view and edit) the Mobile app CSS file via local_mobilecssedit';

// Privacy API (this plugin does not store any personal data).
$string['privacy:metadata']     = 'The local_mobilecssedit plugin does not store any personal data.';
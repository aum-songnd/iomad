<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'Mobile app CSS inline editor';

// Setting name shown on the Mobile app appearance page.
$string['cssfilecontent']   = 'CSS file content (mobilecssurl)';
$string['editingfile']      = 'Editing file: {$a}';
$string['description']      = 'Mobile app CSS inline editor';

// Create new CSS file feature.
$string['createcssfile']       = 'Create new CSS file';
$string['createcssfile_desc']  = 'Enter the URL / path of the CSS file to create. The system will create the file in the Moodle source code and save it to this company\'s mobilecssurl setting.';
$string['createcssfilehint']   = 'Example: /local/mobilecssedit/style/mycompany.css or a full URL on the same domain as the site. Only .css files inside the Moodle source code are accepted. Leave blank if you do not want to create a file now.';
$string['urlrequired']         = 'Please enter the URL / path of the CSS file to create.';
$string['invalidurl']          = 'Invalid URL: it must be a .css file inside the Moodle source code (same domain as the site, or a relative path), the containing directory must already exist, and the path must not contain "..".';
$string['nocompany']           = 'Could not determine the company currently being edited, so the file cannot be created.';
$string['dirnotwritable']      = 'The target directory exists but the webserver does not have write permission to it (check filesystem permissions).';
$string['filecreated']         = 'New CSS file created successfully.';
$string['filealreadyexists']   = 'The file already exists - the setting has been updated to point to it (its content was not changed).';
$string['autocreatedcomment']  = 'File automatically created by local_mobilecssedit';

// Company overview page (managecss.php).
$string['managecss']       = 'Manage mobile app CSS files per company';
$string['companyname']     = 'Company name';
$string['cssfileexists']   = 'File already exists - it cannot be created again here; edit its content from the theme settings page.';
$string['nocompanies']     = 'There are no companies to display.';

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
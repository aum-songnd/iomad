<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'Mobile app CSS inline editor';

// Setting name shown on the Mobile app appearance page.
$string['cssfilecontent']   = 'CSS file content (mobilecssurl)';
$string['editingfile']      = 'Editing file: {$a}';
$string['description']      = 'Mobile app CSS inline editor';

// View/edit content page (editcss.php).
$string['editcssfile']      = 'Edit content';
$string['backtomanagecss']  = '&laquo; Back to company list';
$string['contentsaved']     = 'File content saved successfully.';

// Create new CSS file feature.
$string['createcssfile']       = 'Create new CSS file';
$string['createcssfile_desc']  = 'Enter the file name to create (e.g. mycompany.css). The file will always be created inside local/mobilecssedit/style/ in the Moodle source code, and this company\'s mobilecssurl setting will be updated to point to it.';
$string['createcssfilehint']   = 'Example: mycompany.css. Enter a file name only (no folders, no URL) - it will be created in local/mobilecssedit/style/. Only .css files are accepted. Leave blank if you do not want to create a file now.';
$string['urlrequired']         = 'Please enter the name of the CSS file to create.';
$string['invalidurl']          = 'Invalid file name: it must be a plain file name ending in ".css" (letters, digits, "-", "_", "." only), with no folders, URL, or "..". The file will always be created inside local/mobilecssedit/style/.';
$string['nocompany']           = 'Could not determine the company currently being edited, so the file cannot be created.';
$string['dirnotwritable']      = 'The local/mobilecssedit/style/ directory does not exist or the webserver does not have write permission to it (check filesystem permissions).';
$string['filecreated']         = 'New CSS file created successfully.';
$string['filealreadyexists']   = 'The file already exists - the setting has been updated to point to it (its content was not changed).';
$string['autocreatedcomment']  = 'File automatically created by local_mobilecssedit';

// Company overview page (managecss.php).
$string['managecss']       = 'Manage mobile app CSS files per company';
$string['companyname']     = 'Company name';
$string['cssfileexists']   = 'File already exists - use the "Edit content" button below to view or edit it.';
$string['nocompanies']     = 'There are no companies to display.';

// Rename existing CSS file feature.
$string['renamecssfile']       = 'Rename';
$string['newfilenamerequired'] = 'Please enter a new file name.';
$string['nocurrentfile']       = 'There is no CSS file currently configured for this company to rename.';
$string['targetfileexists']    = 'A file with that name already exists in local/mobilecssedit/style/ - choose a different name.';
$string['filerenamed']         = 'File renamed successfully.';
$string['samefilename']        = 'The new file name is the same as the current one - nothing changed.';

// Status messages.
$string['nolocalfile']      = 'The "Mobile custom CSS" (mobilecssurl) setting is not configured, or it does not point to a file inside local/mobilecssedit/style/, so it cannot be edited directly here.';
$string['cannotwrite']      = 'Could not determine a local CSS file to write to, or the file does not exist.';
$string['notwritable']      = 'The file exists but the webserver does not have write permission to it (check filesystem permissions).';
$string['writefailed']      = 'Failed to write the file.';
$string['nopermission']     = 'You do not have permission to view or edit this CSS file. The "local/mobilecssedit:manage" capability is required (granted to Manager / site admin by default).';

// Capability (shown in Site administration > Users > Permissions > Define roles).
$string['mobilecssedit:manage'] = 'Manage (view and edit) the Mobile app CSS file via local_mobilecssedit';

// Privacy API (this plugin does not store any personal data).
$string['privacy:metadata']     = 'The local_mobilecssedit plugin does not store any personal data.';

$string['mobilecssurl_notset']     = 'Not configured yet.';
$string['mobilecssurl_managelink'] = 'Manage CSS files';
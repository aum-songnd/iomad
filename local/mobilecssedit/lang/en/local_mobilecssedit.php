<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'Mobile app CSS inline editor';

// Setting name shown on the Mobile app appearance page.
$string['cssfilecontent']   = 'CSS file content (mobilecssurl)';
$string['editingfile']      = 'Editing file: {$a}';
$string['description']      = 'Mobile app CSS inline editor';

// View/edit content page (editcss.php).
$string['editcssfile']      = 'View/Edit content';
$string['closecssfile']     = 'Close';
$string['backtomanagecss']  = '&laquo; Back to company list';
$string['contentsaved']     = 'File content saved successfully.';

// Create new CSS file feature. The file name is no longer typed by the
// admin - it is always derived automatically from the company's shortname
// (e.g. shortname "abc" -> "abc.css").
$string['createcssfile']       = 'Create new CSS file';
$string['createcssfile_desc']  = 'A CSS file named after the company shortname (e.g. mycompany.css) will always be created inside local/mobilecssedit/style/ in the Moodle source code, and this company\'s mobilecssurl setting will be updated to point to it.';
$string['createcssfilehint']   = 'The file name is generated automatically from the company shortname. It will always be created inside local/mobilecssedit/style/.';
$string['createcssfilefor']    = 'Create {$a}';
$string['urlrequired']         = 'Please enter the name of the CSS file to create.';
$string['invalidurl']          = 'Invalid file name: it must be a plain file name ending in ".css" (letters, digits, "-", "_", "." only), with no folders, URL, or "..". The file will always be created inside local/mobilecssedit/style/.';
$string['invalidshortname']    = 'This company\'s shortname cannot be turned into a valid CSS file name (it is empty or contains no usable characters). Please fix the company shortname first.';
$string['nocompany']           = 'Could not determine the company currently being edited, so the file cannot be created.';
$string['dirnotwritable']      = 'The local/mobilecssedit/style/ directory does not exist or the webserver does not have write permission to it (check filesystem permissions).';
$string['filecreated']         = 'New CSS file created successfully.';
$string['filealreadyexists']   = 'The file already exists - the setting has been updated to point to it (its content was not changed).';
$string['autocreatedcomment']  = 'File automatically created by local_mobilecssedit';

// Company overview page (managecss.php).
$string['managecss']       = 'Manage mobile app CSS files per company';
$string['companyname']     = 'Company name';
$string['companyshortname'] = 'Company shortname';
$string['nocompanies']     = 'There are no companies to display.';

$string['renamecssfile']       = 'Rename';
$string['updatefilenamefromshortname'] = 'Update filename';
$string['newfilenamerequired'] = 'Please enter a new file name.';
$string['nocurrentfile']       = 'There is no CSS file currently configured for this company to rename.';
$string['targetfileexists']    = 'A file with that name already exists in local/mobilecssedit/style/ - choose a different name.';
$string['filerenamed']         = 'File name updated to match the company shortname.';
$string['samefilename']        = 'The file name already matches the company shortname - nothing changed.';

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
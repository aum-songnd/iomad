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

require_once('../../config.php');

global $DB, $OUTPUT, $PAGE;

require_login();
require_capability('local/th_bundleassign_api:view', context_system::instance());

$url = new moodle_url('/local/th_bundleassign_api/view.php');
$title = get_string('pluginname', 'local_th_bundleassign_api');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$lang = current_language();

// Load available IOMAD companies.
$sql = "SELECT id, name
        FROM {company}
        WHERE suspended = 0
        ORDER BY name ASC, id ASC";
$companies = $DB->get_records_sql($sql);

$companyoptions = ['' => get_string('select_company', 'local_th_bundleassign_api')];
foreach ($companies as $company) {
    $label = trim($company->name) !== '' ? trim($company->name) : get_string('company_fallback_label', 'local_th_bundleassign_api', (int)$company->id);
    $companyoptions[(int)$company->id] = $label . ' (' . (int)$company->id . ')';
}

$thcompanies = $DB->get_records('th_company', null, 'id ASC');

if (optional_param('save', '', PARAM_ALPHA) === 'save' && confirm_sesskey()) {
    $submitted = optional_param_array('lms_company_id', [], PARAM_INT);

    $allowedids = array_map('intval', array_keys($thcompanies));
    $submittedids = array_map('intval', array_keys($submitted));
    foreach ($submittedids as $submittedid) {
        if (!in_array($submittedid, $allowedids, true)) {
            redirect($url, get_string('invalid_submitted_data', 'local_th_bundleassign_api'), null, \core\output\notification::NOTIFY_ERROR);
        }
    }

    $selectedcompanyids = [];
    foreach ($submitted as $thcompanyid => $lmscompanyid) {
        if (empty($lmscompanyid)) {
            continue;
        }
        $lmscompanyid = (int)$lmscompanyid;
        if (!array_key_exists($lmscompanyid, $companyoptions)) {
            redirect($url, get_string('selected_iomad_company_not_exist', 'local_th_bundleassign_api'), null, \core\output\notification::NOTIFY_ERROR);
        }
        if (in_array($lmscompanyid, $selectedcompanyids, true)) {
            redirect($url, get_string('company_select_once', 'local_th_bundleassign_api'), null, \core\output\notification::NOTIFY_ERROR);
        }
        $selectedcompanyids[] = $lmscompanyid;
    }

    $rowswithchanges = [];
    foreach ($thcompanies as $thcompany) {
        $thcompanyid = (int)$thcompany->id;
        $currentlmscompanyid = empty($thcompany->lms_company_id) ? null : (int)$thcompany->lms_company_id;
        $newlmscompanyid = null;

        // If nothing was chosen, we keep null so the database field will be cleared.
        if (array_key_exists($thcompanyid, $submitted) && !empty($submitted[$thcompanyid])) {
            $newlmscompanyid = (int)$submitted[$thcompanyid];
        }

        // Skip rows where the submitted value is identical to the current one.
        // No need to issue useless UPDATE queries.
        if ($currentlmscompanyid === $newlmscompanyid) {
            continue;
        }

        $rowswithchanges[$thcompanyid] = [
            'old' => $currentlmscompanyid,
            'new' => $newlmscompanyid,
        ];
    }

    $transaction = $DB->start_delegated_transaction();
    try {
        // Clear old values first for all changed rows.
        foreach ($rowswithchanges as $thcompanyid => $values) {
            if ($values['old'] === null) {
                // Nothing to clear if the row did not have a previous mapping.
                continue;
            }

            $clearrecord = new stdClass();
            $clearrecord->id = $thcompanyid;
            $clearrecord->lms_company_id = null;
            $DB->update_record('th_company', $clearrecord);
        }

        // Assign the requested new values after all conflicting old values were
        foreach ($rowswithchanges as $thcompanyid => $values) {
            if ($values['new'] === null) {
                continue;
            }

            $assignrecord = new stdClass();
            $assignrecord->id = $thcompanyid;
            $assignrecord->lms_company_id = $values['new'];
            $DB->update_record('th_company', $assignrecord);
        }

        $transaction->allow_commit();
    } catch (Exception $e) {
        $transaction->rollback($e);
    }

    redirect($url, get_string('saved_successfully', 'local_th_bundleassign_api'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

if (empty($thcompanies)) {
    echo $OUTPUT->notification(get_string('no_data_in_th_company', 'local_th_bundleassign_api'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 'save']);

$table = new html_table();
$table->head = [
    '#',
    get_string('company_name', 'local_th_bundleassign_api'),
    get_string('company_code', 'local_th_bundleassign_api'),
    get_string('lms_company_id', 'local_th_bundleassign_api')
];
$table->attributes = [
    'class' => 'table th_bundleassign_company_table',
];
$table->data = [];

$counter = 1;
foreach ($thcompanies as $thcompany) {
    $selectname = 'lms_company_id[' . (int)$thcompany->id . ']';
    $selected = empty($thcompany->lms_company_id) ? '' : (int)$thcompany->lms_company_id;

    if ($selected > 0 && !array_key_exists($selected, $companyoptions)) {
        $companyoptions[$selected] = get_string('missing_company', 'local_th_bundleassign_api', $selected);
    }

    $selecthtml = html_writer::select(
        $companyoptions,
        $selectname,
        $selected,
        false,
        [
            'class' => 'custom-select js-lms-company-select',
            'data-rowid' => (int)$thcompany->id,
        ]
    );

    $table->data[] = [
        $counter++,
        format_string($thcompany->name),
        s($thcompany->company_code),
        $selecthtml,
    ];
}

echo html_writer::table($table);
echo '<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">';
$PAGE->requires->js_call_amd('local_thlib/main', 'init', ['.th_bundleassign_company_table', $title, $lang]);
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => get_string('save', 'local_th_bundleassign_api'),
]);
echo html_writer::end_tag('form');

$js = <<<JS
require([], function() {
    var selects = Array.prototype.slice.call(document.querySelectorAll('.js-lms-company-select'));
    if (!selects.length) {
        return;
    }

    var refreshOptions = function() {
        var selectedValues = [];
        selects.forEach(function(select) {
            if (select.value !== '') {
                selectedValues.push(select.value);
            }
        });

        selects.forEach(function(select) {
            var currentValue = select.value;
            Array.prototype.slice.call(select.options).forEach(function(option) {
                if (option.value === '') {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }

                var usedByAnotherRow = selectedValues.indexOf(option.value) !== -1 && option.value !== currentValue;
                option.hidden = usedByAnotherRow;
                option.disabled = usedByAnotherRow;
            });
        });
    };

    selects.forEach(function(select) {
        select.addEventListener('change', refreshOptions);
    });

    refreshOptions();
});
JS;

$PAGE->requires->js_amd_inline($js);

echo $OUTPUT->footer();

<?php

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(dirname(__FILE__) . '/locallib.php');

// admin_externalpage_setup() tự lo require_login(), kiểm tra capability đã
// khai báo khi đăng ký trang (local/mobilecssedit:manage) trong settings.php,
// và thiết lập context/pagelayout/url tương ứng.
admin_externalpage_setup('local_mobilecssedit_managecss');

$pageurl = $PAGE->url;
$PAGE->set_title(get_string('managecss', 'local_mobilecssedit'));
$PAGE->set_heading(get_string('managecss', 'local_mobilecssedit'));

$context = context_system::instance();

// Xử lý submit tạo file cho 1 company (mỗi dòng trong bảng có form riêng).
$createforcompany = optional_param('createforcompany', 0, PARAM_INT);
if ($createforcompany) {
    require_sesskey();

    $cssurl = optional_param('cssurl', '', PARAM_RAW_TRIMMED);
    $result = local_mobilecssedit_create_css_file($cssurl, $createforcompany);

    $notiftype = $result['success']
        ? \core\output\notification::NOTIFY_SUCCESS
        : \core\output\notification::NOTIFY_ERROR;

    redirect($pageurl, $result['message'], null, $notiftype);
}

// Lấy danh sách company: toàn bộ nếu có quyền quản trị hệ thống công ty,
// ngược lại chỉ những company mà user hiện tại được quản lý (theo IOMAD).
if (has_capability('block/iomad_company_admin:company_add', $context)) {
    $companies = $DB->get_records('company', [], 'name ASC', 'id, name');
} else {
    $companies = [];
    if (class_exists('company') && method_exists('company', 'get_companies_select')) {
        $mycompanylist = company::get_companies_select(true);
        if (!empty($mycompanylist)) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($mycompanylist));
            $companies = $DB->get_records_select('company', "id $insql", $inparams, 'name ASC', 'id, name');
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecss', 'local_mobilecssedit'));

if (empty($companies)) {
    echo $OUTPUT->notification(get_string('nocompanies', 'local_mobilecssedit'), 'info');
} else {
    $table = new html_table();
    $table->head  = [get_string('companyname', 'local_mobilecssedit'), get_string('cssfilecontent', 'local_mobilecssedit')];
    $table->align = ['left', 'left'];
    $table->width = '100%';

    foreach ($companies as $company) {
        $configuredurl = local_mobilecssedit_get_configured_url((int) $company->id);
        $csspath = local_mobilecssedit_get_css_path((int) $company->id);

        if ($csspath) {
            // Đã có file trên đĩa -> chỉ hiển thị link, không cho tạo lại.
            $cssfilecell = html_writer::link(
                $configuredurl,
                s($configuredurl),
                ['target' => '_blank', 'rel' => 'noopener']
            );
            $cssfilecell .= html_writer::tag('div',
                get_string('cssfileexists', 'local_mobilecssedit'),
                ['class' => 'small text-muted']);
        } else {
            // Chưa có file -> ô nhập URL + nút tạo, nằm trong 1 form riêng cho dòng này.
            $formattrs = [
                'method' => 'post',
                'action' => $pageurl->out(false),
                'class'  => 'form-inline',
            ];
            $inner = html_writer::empty_tag('input', [
                'type'  => 'hidden',
                'name'  => 'sesskey',
                'value' => sesskey(),
            ]);
            $inner .= html_writer::empty_tag('input', [
                'type'  => 'hidden',
                'name'  => 'createforcompany',
                'value' => $company->id,
            ]);
            $inner .= html_writer::empty_tag('input', [
                'type'        => 'text',
                'name'        => 'cssurl',
                'placeholder' => '/local/mobilecssedit/style/tencongty.css',
                'class'       => 'form-control mr-2',
                'style'       => 'min-width:320px;display:inline-block;width:auto;',
            ]);
            $inner .= html_writer::tag('button',
                get_string('createcssfile', 'local_mobilecssedit'),
                ['type' => 'submit', 'class' => 'btn btn-primary btn-sm']);

            $cssfilecell = html_writer::tag('form', $inner, $formattrs);
        }

        $table->data[] = [s($company->name), $cssfilecell];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
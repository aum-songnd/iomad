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

// company đang được mở rộng (sau khi lưu nội dung / khi bấm mở) - dùng để
// tự động mở lại đúng dòng đó sau khi submit, thay vì thu gọn hết.
$expanded = optional_param('expanded', 0, PARAM_INT);

// Xử lý submit tạo file cho 1 company (mỗi dòng trong bảng có form riêng).
$createforcompany = optional_param('createforcompany', 0, PARAM_INT);
if ($createforcompany) {
    require_sesskey();

    $cssurl = optional_param('cssurl', '', PARAM_RAW_TRIMMED);
    $result = local_mobilecssedit_create_css_file($cssurl, $createforcompany);

    $notiftype = $result['success']
        ? \core\output\notification::NOTIFY_SUCCESS
        : \core\output\notification::NOTIFY_ERROR;

    $redirecturl = new moodle_url($pageurl, ['expanded' => $createforcompany]);
    redirect($redirecturl, $result['message'], null, $notiftype);
}

// Xử lý submit đổi tên file đã tồn tại cho 1 company (mỗi dòng trong bảng
// có form riêng). Chỉ đổi tên file trên đĩa (trong thư mục cố định), không
// đổi nội dung file.
$renameforcompany = optional_param('renameforcompany', 0, PARAM_INT);
if ($renameforcompany) {
    require_sesskey();

    $newfilename = optional_param('newfilename', '', PARAM_RAW_TRIMMED);
    $result = local_mobilecssedit_rename_css_file($renameforcompany, $newfilename);

    $notiftype = $result['success']
        ? \core\output\notification::NOTIFY_SUCCESS
        : \core\output\notification::NOTIFY_ERROR;

    $redirecturl = new moodle_url($pageurl, ['expanded' => $renameforcompany]);
    redirect($redirecturl, $result['message'], null, $notiftype);
}

// Xử lý submit lưu NỘI DUNG file CSS ngay tại trang này (thay vì phải sang
// trang khác) - đây là form nằm bên trong <details> của mỗi dòng.
$savecontentfor = optional_param('savecontentfor', 0, PARAM_INT);
if ($savecontentfor) {
    require_sesskey();

    $csspath = local_mobilecssedit_get_css_path($savecontentfor);
    $redirecturl = new moodle_url($pageurl, ['expanded' => $savecontentfor]);

    if ($csspath === null) {
        redirect($redirecturl, get_string('cannotwrite', 'local_mobilecssedit'), null,
            \core\output\notification::NOTIFY_ERROR);
    }
    if (!is_writable($csspath)) {
        redirect($redirecturl, get_string('notwritable', 'local_mobilecssedit'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    $content = optional_param('content', '', PARAM_RAW);
    $content = str_replace("\r\n", "\n", $content);

    if (file_put_contents($csspath, $content) === false) {
        redirect($redirecturl, get_string('writefailed', 'local_mobilecssedit'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    theme_reset_all_caches();
    redirect($redirecturl, get_string('contentsaved', 'local_mobilecssedit'), null,
        \core\output\notification::NOTIFY_SUCCESS);
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

// Khi <details> đóng: chỉ chiếm đúng chiều rộng nút "summary" nên nằm gọn
// ngang hàng với nút đổi tên (nhờ flex-wrap trên container cha). Khi mở ra
// ([open]): ép chiều rộng 100% để nó tự nhảy xuống dòng riêng (flex-wrap
// đẩy sang dòng mới), y như hiển thị nội dung bên dưới các nút như trước.
echo html_writer::tag('style', '
    .mobilecssedit-details[open] {
        flex-basis: 100%;
        width: 100%;
    }
');

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
            // Đã có file trên đĩa -> hiển thị link (không cho tạo lại), 1
            // form riêng để đổi tên file, và 1 khối <details> để xem/sửa
            // nội dung NGAY TRONG DÒNG này: mở ra sẽ đẩy các company khác
            // xuống, thu gọn lại thì các dòng khác tự trở về vị trí ban đầu
            // (hành vi mặc định của <details>, không cần JavaScript).
            $cssfilecell = html_writer::link(
                $configuredurl,
                s($configuredurl),
                ['target' => '_blank', 'rel' => 'noopener']
            );
            $cssfilecell .= html_writer::tag('div',
                get_string('cssfileexists', 'local_mobilecssedit'),
                ['class' => 'small text-muted']);

            $renameformattrs = [
                'method' => 'post',
                'action' => $pageurl->out(false),
                'class'  => 'form-inline flex-shrink-0',
            ];
            $renameinner = html_writer::empty_tag('input', [
                'type'  => 'hidden',
                'name'  => 'sesskey',
                'value' => sesskey(),
            ]);
            $renameinner .= html_writer::empty_tag('input', [
                'type'  => 'hidden',
                'name'  => 'renameforcompany',
                'value' => $company->id,
            ]);
            $renameinner .= html_writer::empty_tag('input', [
                'type'        => 'text',
                'name'        => 'newfilename',
                'value'       => basename($csspath),
                'placeholder' => 'tencongty.css',
                'class'       => 'form-control mr-2',
                'style'       => 'min-width:220px;display:inline-block;width:auto;',
            ]);
            $renameinner .= html_writer::tag('button',
                get_string('renamecssfile', 'local_mobilecssedit'),
                ['type' => 'submit', 'class' => 'btn btn-secondary btn-sm']);

            $renameform = html_writer::tag('form', $renameinner, $renameformattrs);

            // Khối xem/sửa nội dung dạng "xổ xuống" (native <details>).
            $filecontent = file_get_contents($csspath);

            $contentformattrs = [
                'method' => 'post',
                'action' => $pageurl->out(false),
                'class'  => 'mt-2',
            ];
            $contentform = html_writer::empty_tag('input', [
                'type'  => 'hidden',
                'name'  => 'sesskey',
                'value' => sesskey(),
            ]);
            $contentform .= html_writer::empty_tag('input', [
                'type'  => 'hidden',
                'name'  => 'savecontentfor',
                'value' => $company->id,
            ]);
            $contentform .= html_writer::tag('textarea', s($filecontent), [
                'name'  => 'content',
                'rows'  => 20,
                'style' => 'width:100%; font-family:monospace; font-size:13px;',
            ]);
            $contentform .= html_writer::tag('button',
                get_string('savechanges'),
                ['type' => 'submit', 'class' => 'btn btn-primary btn-sm mt-1']);

            $detailsattrs = ['class' => 'mobilecssedit-details'];
            if ($expanded == $company->id) {
                // Vừa lưu/mở dòng này -> tự động mở lại sau khi submit.
                $detailsattrs['open'] = 'open';
            }

            $details = html_writer::tag('summary',
                get_string('editcssfile', 'local_mobilecssedit'),
                ['class' => 'btn btn-outline-primary btn-sm', 'style' => 'cursor:pointer;']);
            $details .= html_writer::tag('form', $contentform, $contentformattrs);

            $detailsblock = html_writer::tag('details', $details, $detailsattrs);

            // Bọc form đổi tên và nút xem/sửa nội dung trong 1 hàng ngang -
            // khi mở rộng nội dung, chỉ cột "details" cao lên (đẩy các dòng
            // company khác trong bảng xuống), nút đổi tên vẫn giữ nguyên vị trí.
            $actionsrow = html_writer::start_tag('div', [
                'class' => 'd-flex align-items-start mt-1',
                'style' => 'gap:8px; flex-wrap:wrap;',
            ]);
            $actionsrow .= $renameform;
            $actionsrow .= $detailsblock;
            $actionsrow .= html_writer::end_tag('div');

            $cssfilecell .= $actionsrow;
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
                'placeholder' => 'tencongty.css',
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
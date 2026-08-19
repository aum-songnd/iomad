<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Chuyển 1 giá trị URL (tuyệt đối theo wwwroot, hoặc tương đối dạng
 * "/theme/xxx/style/abc.css") thành đường dẫn tương đối so với $CFG->dirroot.
 *
 * Hàm này KHÔNG kiểm tra file có tồn tại hay không (dùng được cả cho
 * trường hợp sắp tạo file mới). Chỉ làm sạch input và loại các URL
 * trỏ ra ngoài domain / chứa path traversal rõ ràng ("..").
 *
 * @param string $url
 * @return string|null Đường dẫn tương đối bắt đầu bằng "/", hoặc null nếu
 *                      URL không hợp lệ / không map được vào mã nguồn Moodle.
 */
function local_mobilecssedit_resolve_relative_path(string $url): ?string {
    global $CFG;

    $url = trim($url);
    if ($url === '') {
        return null;
    }

    // Bỏ query string / fragment nếu có (vd: ...aum.css?v=123).
    $url = preg_replace('/[?#].*$/', '', $url);

    $wwwroot = rtrim($CFG->wwwroot, '/');
    if (strpos($url, $wwwroot) === 0) {
        $relative = substr($url, strlen($wwwroot));
    } else if (preg_match('#^https?://#i', $url)) {
        // URL tuyệt đối nhưng khác domain wwwroot -> không map được sang file cục bộ.
        return null;
    } else {
        // Giá trị đã là đường dẫn tương đối, vd: /theme/xxx/style/aum.css.
        $relative = $url;
    }

    $relative = '/' . ltrim($relative, '/');

    // Chặn path traversal rõ ràng ngay từ input (realpath() chỉ dùng được
    // sau khi file đã tồn tại nên không thể dựa hoàn toàn vào nó khi tạo mới).
    if (strpos($relative, '..') !== false) {
        return null;
    }

    // Chỉ chấp nhận file .css.
    if (strtolower(pathinfo($relative, PATHINFO_EXTENSION)) !== 'css') {
        return null;
    }

    return $relative;
}

/**
 * Xác định đường dẫn file vật lý (trên ổ đĩa) tương ứng với giá trị
 * cấu hình "mobilecssurl" theo company hiện đang chỉnh sửa (IOMAD),
 * được lưu bởi theme_th_lambda_st dưới dạng
 * theme_th_lambda_st/mobilecssurl{$companyid}.
 *
 * @param int|null $companyid ID company. Nếu null, lấy từ
 *                             $SESSION->currenteditingcompany.
 * @return string|null Đường dẫn tuyệt đối tới file CSS, hoặc null nếu
 *                      không xác định được / file nằm ngoài mã nguồn Moodle /
 *                      file chưa tồn tại trên đĩa.
 */
function local_mobilecssedit_get_css_path(?int $companyid = null): ?string {
    global $CFG, $SESSION;

    if ($companyid === null) {
        $companyid = local_mobilecssedit_get_current_companyid();
    }
    if (empty($companyid)) {
        // Không có company nào đang được chỉnh sửa -> không có setting để đọc.
        return null;
    }

    $url = get_config('theme_th_lambda_st', 'mobilecssurl' . $companyid);
    if (empty($url)) {
        return null;
    }

    $relative = local_mobilecssedit_resolve_relative_path($url);
    if ($relative === null) {
        return null;
    }

    $fullpath = $CFG->dirroot . $relative;

    // Chống path traversal: file thực tế phải nằm trong dirroot.
    $real = realpath($fullpath);
    $realdirroot = realpath($CFG->dirroot);
    if ($real === false || $realdirroot === false || strpos($real, $realdirroot) !== 0) {
        return null;
    }

    return $real;
}

/**
 * Lấy company đang chỉnh sửa hiện tại (IOMAD), dùng chung cho settings.php
 * và cho việc lưu setting mobilecssurl{$companyid} khi tạo file mới.
 *
 * @return int 0 nếu không xác định được company.
 */
function local_mobilecssedit_get_current_companyid(): int {
    global $SESSION;
    return !empty($SESSION->currenteditingcompany) ? (int) $SESSION->currenteditingcompany : 0;
}

/**
 * Lấy giá trị URL đang được cấu hình (theme_th_lambda_st/mobilecssurl{$companyid})
 * cho 1 company, dùng chung cho mọi nơi cần hiển thị/link URL này (settings.php,
 * block_mobilecssedit) thay vì gọi get_config() trực tiếp rải rác nhiều nơi.
 *
 * @param int $companyid
 * @return string|null URL đã cấu hình, hoặc null nếu chưa cấu hình.
 */
function local_mobilecssedit_get_configured_url(int $companyid): ?string {
    if (empty($companyid)) {
        return null;
    }
    $url = get_config('theme_th_lambda_st', 'mobilecssurl' . $companyid);
    return empty($url) ? null : $url;
}

/**
 * Tạo mới 1 file CSS trên đĩa tương ứng với URL do admin nhập, đồng thời
 * lưu URL đó vào setting theme_th_lambda_st/mobilecssurl{$companyid} để
 * lần load trang sau sẽ tự động ra đúng màn hình chỉnh sửa (admin_setting_cssfile).
 *
 * Không cho phép ghi đè file đã tồn tại (tránh mất nội dung đang có) -
 * nếu file đã tồn tại thì chỉ cập nhật setting để trỏ tới file đó.
 *
 * @param string $url Giá trị admin nhập vào (URL tuyệt đối hoặc đường dẫn tương đối).
 * @param int $companyid
 * @return array{success:bool, message:string, path:?string}
 */
function local_mobilecssedit_create_css_file(string $url, int $companyid): array {
    global $CFG, $DB;

    $url = trim($url);
    if ($url === '') {
        return ['success' => false, 'message' => get_string('urlrequired', 'local_mobilecssedit'), 'path' => null];
    }
    if (empty($companyid)) {
        return ['success' => false, 'message' => get_string('nocompany', 'local_mobilecssedit'), 'path' => null];
    }
    // companyid có thể đến từ dữ liệu POST (vd từ block_mobilecssedit) nên cần
    // xác nhận lại đây là 1 company có thật, tránh lưu config rác theo id tuỳ ý.
    if (!$DB->record_exists('company', ['id' => $companyid])) {
        return ['success' => false, 'message' => get_string('nocompany', 'local_mobilecssedit'), 'path' => null];
    }

    $relative = local_mobilecssedit_resolve_relative_path($url);
    if ($relative === null) {
        return ['success' => false, 'message' => get_string('invalidurl', 'local_mobilecssedit'), 'path' => null];
    }

    $fullpath = $CFG->dirroot . $relative;
    $dir = dirname($fullpath);

    // Thư mục cha phải đã tồn tại thật sự trong dirroot (không tự tạo thư mục mới,
    // để tránh admin tạo cấu trúc thư mục tuỳ ý ngoài ý muốn).
    $realdir = realpath($dir);
    $realdirroot = realpath($CFG->dirroot);
    if ($realdir === false || $realdirroot === false || strpos($realdir, $realdirroot) !== 0) {
        return ['success' => false, 'message' => get_string('invalidurl', 'local_mobilecssedit'), 'path' => null];
    }

    $alreadyexists = file_exists($fullpath);

    if (!$alreadyexists) {
        if (!is_writable($realdir)) {
            return ['success' => false, 'message' => get_string('dirnotwritable', 'local_mobilecssedit'), 'path' => null];
        }
        $default = "/* " . get_string('autocreatedcomment', 'local_mobilecssedit') . " */\n";
        if (file_put_contents($fullpath, $default) === false) {
            return ['success' => false, 'message' => get_string('writefailed', 'local_mobilecssedit'), 'path' => null];
        }
    }

    // Xác nhận lại file thực sự nằm trong dirroot sau khi tạo (phòng vệ kép).
    $real = realpath($fullpath);
    if ($real === false || strpos($real, $realdirroot) !== 0) {
        if (!$alreadyexists) {
            @unlink($fullpath);
        }
        return ['success' => false, 'message' => get_string('invalidurl', 'local_mobilecssedit'), 'path' => null];
    }

    set_config('mobilecssurl' . $companyid, $url, 'theme_th_lambda_st');
    theme_reset_all_caches();

    $messagekey = $alreadyexists ? 'filealreadyexists' : 'filecreated';
    return ['success' => true, 'message' => get_string($messagekey, 'local_mobilecssedit'), 'path' => $real];
}
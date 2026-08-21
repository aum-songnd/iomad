<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Thư mục CỐ ĐỊNH (trong mã nguồn plugin) dùng để lưu mọi file CSS được
 * tạo bởi plugin này, thay vì cho phép admin nhập 1 URL/đường dẫn tuỳ ý
 * trỏ tới bất kỳ đâu trong $CFG->dirroot. Việc cố định 1 thư mục duy nhất
 * giúp loại bỏ hoàn toàn nguy cơ path traversal / ghi đè file mã nguồn
 * khác, vì admin chỉ còn nhập TÊN FILE, không nhập đường dẫn.
 *
 * @return string Đường dẫn tuyệt đối tới thư mục lưu file CSS.
 */
function local_mobilecssedit_get_style_dir(): string {
    global $CFG;
    return $CFG->dirroot . '/local/mobilecssedit/style';
}

/**
 * URL công khai (theo wwwroot) tương ứng với thư mục ở trên, dùng để build
 * giá trị lưu vào theme_th_lambda_st/mobilecssurl{$companyid} (setting này
 * cần 1 URL để mobile app tải file CSS về).
 *
 * @return string
 */
function local_mobilecssedit_get_style_url(): string {
    global $CFG;
    return rtrim($CFG->wwwroot, '/') . '/local/mobilecssedit/style';
}

/**
 * Làm sạch + kiểm tra tên file do admin nhập vào. Chỉ chấp nhận TÊN FILE
 * đơn thuần (không có "/", không có thư mục cha, không phải URL) gồm các
 * ký tự an toàn, kết thúc bằng ".css".
 *
 * Đây là hàng rào chính chống path traversal: vì input chỉ còn là 1 tên
 * file (không có dấu "/") nên không thể thoát ra khỏi thư mục cố định
 * local_mobilecssedit_get_style_dir().
 *
 * @param string $name Giá trị admin nhập, vd "tencongty.css".
 * @return string|null Tên file đã làm sạch, hoặc null nếu không hợp lệ.
 */
function local_mobilecssedit_sanitize_filename(string $name): ?string {
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    // Bỏ query string / fragment nếu admin lỡ copy nguyên URL vào.
    $name = preg_replace('/[?#].*$/', '', $name);

    // Chỉ lấy phần tên file cuối cùng (loại bỏ mọi thư mục cha nếu có,
    // vd "../../abc.css" hay "/theme/x/abc.css" -> "abc.css").
    $name = basename($name);

    if ($name === '' || $name === '.' || $name === '..') {
        return null;
    }

    // Chỉ cho phép chữ, số, dấu gạch dưới/ngang và dấu chấm.
    if (!preg_match('/^[A-Za-z0-9_\-.]+$/', $name)) {
        return null;
    }

    // Không cho phép chuỗi ".." xuất hiện dù đã basename() (phòng vệ kép).
    if (strpos($name, '..') !== false) {
        return null;
    }

    // Chỉ chấp nhận file .css, và phải có phần tên trước đuôi mở rộng.
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'css') {
        return null;
    }
    if (pathinfo($name, PATHINFO_FILENAME) === '') {
        return null;
    }

    return $name;
}

/**
 * Tạo tên file CSS "{shortname}.css" từ shortname của company, đã làm sạch
 * qua clean_param(PARAM_FILE) rồi qua local_mobilecssedit_sanitize_filename()
 * (phòng vệ kép) để đảm bảo luôn ra 1 tên file an toàn, không phụ thuộc vào
 * việc admin gõ tay tên file nữa (shortname vốn đã là định danh duy nhất
 * của company nên dùng lại làm tên file luôn, tránh trùng lặp giữa các
 * company và tránh phải hỏi thêm admin 1 giá trị mới).
 *
 * @param string $shortname Shortname của company (company->shortname).
 * @return string|null Tên file dạng "shortname.css" đã làm sạch, hoặc null
 *                      nếu shortname rỗng / không còn ký tự hợp lệ nào sau
 *                      khi làm sạch.
 */
function local_mobilecssedit_filename_from_shortname(string $shortname): ?string {
    $shortname = trim($shortname);
    if ($shortname === '') {
        return null;
    }

    // clean_param(PARAM_FILE) loại bỏ dấu tiếng Việt, khoảng trắng và mọi
    // ký tự không an toàn cho tên file (Moodle core).
    $base = clean_param($shortname, PARAM_FILE);
    if ($base === '' || $base === '.' || $base === '..') {
        return null;
    }

    return local_mobilecssedit_sanitize_filename($base . '.css');
}

/**
 * Xác định đường dẫn file vật lý (trên ổ đĩa) tương ứng với giá trị
 * cấu hình "mobilecssurl" theo company hiện đang chỉnh sửa (IOMAD),
 * được lưu bởi theme_th_lambda_st dưới dạng
 * theme_th_lambda_st/mobilecssurl{$companyid}.
 *
 * File luôn được tìm trong thư mục cố định
 * local_mobilecssedit_get_style_dir() - chỉ phần TÊN FILE trong giá trị
 * đã lưu được sử dụng, mọi phần đường dẫn/URL khác đều bị bỏ qua.
 *
 * @param int|null $companyid ID company. Nếu null, lấy từ
 *                             $SESSION->currenteditingcompany.
 * @return string|null Đường dẫn tuyệt đối tới file CSS, hoặc null nếu
 *                      không xác định được / tên file không hợp lệ /
 *                      file chưa tồn tại trên đĩa.
 */
function local_mobilecssedit_get_css_path(?int $companyid = null): ?string {
    if ($companyid === null) {
        $companyid = local_mobilecssedit_get_current_companyid();
    }
    if (empty($companyid)) {
        // Không có company nào đang được chỉnh sửa -> không có setting để đọc.
        return null;
    }

    $stored = get_config('theme_th_lambda_st', 'mobilecssurl' . $companyid);
    if (empty($stored)) {
        return null;
    }

    // $stored có thể là 1 tên file đơn thuần, hoặc (dữ liệu cũ) 1 URL đầy đủ -
    // trong mọi trường hợp chỉ phần tên file cuối cùng được dùng, mọi phần
    // đường dẫn/domain khác bị bỏ qua hoàn toàn.
    $filename = local_mobilecssedit_sanitize_filename($stored);
    if ($filename === null) {
        return null;
    }

    $styledir = local_mobilecssedit_get_style_dir();
    $fullpath = $styledir . '/' . $filename;

    if (!file_exists($fullpath)) {
        return null;
    }

    // Chống path traversal: file thực tế phải nằm trong thư mục cố định.
    $real = realpath($fullpath);
    $realstyledir = realpath($styledir);
    if ($real === false || $realstyledir === false || strpos($real, $realstyledir) !== 0) {
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
 * Tạo mới 1 file CSS trong thư mục CỐ ĐỊNH local_mobilecssedit_get_style_dir()
 * theo tên file do admin nhập, đồng thời lưu URL tương ứng vào setting
 * theme_th_lambda_st/mobilecssurl{$companyid} để lần load trang sau sẽ tự
 * động ra đúng màn hình chỉnh sửa (admin_setting_cssfile).
 *
 * Không cho phép ghi đè file đã tồn tại (tránh mất nội dung đang có) -
 * nếu file đã tồn tại thì chỉ cập nhật setting để trỏ tới file đó.
 *
 * @param string $filename Tên file do admin nhập, vd "tencongty.css".
 *                          KHÔNG còn nhận URL hay đường dẫn - mọi phần
 *                          thư mục/domain trong input đều bị loại bỏ.
 * @param int $companyid
 * @return array{success:bool, message:string, path:?string}
 */
function local_mobilecssedit_create_css_file(string $filename, int $companyid): array {
    global $DB;

    $filename = trim($filename);
    if ($filename === '') {
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

    $cleanfilename = local_mobilecssedit_sanitize_filename($filename);
    if ($cleanfilename === null) {
        return ['success' => false, 'message' => get_string('invalidurl', 'local_mobilecssedit'), 'path' => null];
    }

    $styledir = local_mobilecssedit_get_style_dir();

    // Thư mục cố định phải tồn tại - đây là thư mục thuộc mã nguồn plugin
    // (local/mobilecssedit/style) nên tự tạo nếu thiếu là an toàn (không
    // giống trường hợp trước đây phải tự dò thư mục tuỳ ý ngoài ý muốn).
    if (!is_dir($styledir)) {
        if (!@mkdir($styledir, 0775, true)) {
            return ['success' => false, 'message' => get_string('dirnotwritable', 'local_mobilecssedit'), 'path' => null];
        }
    }

    $fullpath = $styledir . '/' . $cleanfilename;
    $alreadyexists = file_exists($fullpath);

    if (!$alreadyexists) {
        if (!is_writable($styledir)) {
            return ['success' => false, 'message' => get_string('dirnotwritable', 'local_mobilecssedit'), 'path' => null];
        }
        $default = "/* " . get_string('autocreatedcomment', 'local_mobilecssedit') . " */\n";
        if (file_put_contents($fullpath, $default) === false) {
            return ['success' => false, 'message' => get_string('writefailed', 'local_mobilecssedit'), 'path' => null];
        }
    }

    // Xác nhận lại file thực sự nằm trong thư mục cố định (phòng vệ kép).
    $real = realpath($fullpath);
    $realstyledir = realpath($styledir);
    if ($real === false || $realstyledir === false || strpos($real, $realstyledir) !== 0) {
        if (!$alreadyexists) {
            @unlink($fullpath);
        }
        return ['success' => false, 'message' => get_string('invalidurl', 'local_mobilecssedit'), 'path' => null];
    }

    $publicurl = local_mobilecssedit_get_style_url() . '/' . $cleanfilename;
    set_config('mobilecssurl' . $companyid, $publicurl, 'theme_th_lambda_st');
    theme_reset_all_caches();

    $messagekey = $alreadyexists ? 'filealreadyexists' : 'filecreated';
    return ['success' => true, 'message' => get_string($messagekey, 'local_mobilecssedit'), 'path' => $real];
}

/**
 * Đổi tên file CSS ĐÃ TỒN TẠI của 1 company (chỉ đổi tên trên đĩa trong
 * thư mục cố định local_mobilecssedit_get_style_dir(), KHÔNG đổi nội dung
 * file), đồng thời cập nhật lại theme_th_lambda_st/mobilecssurl{$companyid}
 * để trỏ tới tên mới.
 *
 * @param int $companyid
 * @param string $newfilename Tên file mới, vd "tencongty-moi.css".
 * @return array{success:bool, message:string, path:?string}
 */
function local_mobilecssedit_rename_css_file(int $companyid, string $newfilename): array {
    global $DB;

    if (empty($companyid)) {
        return ['success' => false, 'message' => get_string('nocompany', 'local_mobilecssedit'), 'path' => null];
    }
    if (!$DB->record_exists('company', ['id' => $companyid])) {
        return ['success' => false, 'message' => get_string('nocompany', 'local_mobilecssedit'), 'path' => null];
    }

    $newfilename = trim($newfilename);
    if ($newfilename === '') {
        return ['success' => false, 'message' => get_string('newfilenamerequired', 'local_mobilecssedit'), 'path' => null];
    }

    $currentpath = local_mobilecssedit_get_css_path($companyid);
    if ($currentpath === null) {
        return ['success' => false, 'message' => get_string('nocurrentfile', 'local_mobilecssedit'), 'path' => null];
    }

    $cleannewname = local_mobilecssedit_sanitize_filename($newfilename);
    if ($cleannewname === null) {
        return ['success' => false, 'message' => get_string('invalidurl', 'local_mobilecssedit'), 'path' => null];
    }

    $styledir = local_mobilecssedit_get_style_dir();
    $currentfilename = basename($currentpath);

    // Tên mới trùng tên hiện tại -> không có gì để làm.
    if ($cleannewname === $currentfilename) {
        return ['success' => true, 'message' => get_string('samefilename', 'local_mobilecssedit'), 'path' => $currentpath];
    }

    $newpath = $styledir . '/' . $cleannewname;

    // Không cho phép đổi tên đè lên 1 file khác đã tồn tại (tránh mất dữ liệu
    // của company/file khác).
    if (file_exists($newpath)) {
        return ['success' => false, 'message' => get_string('targetfileexists', 'local_mobilecssedit'), 'path' => null];
    }

    if (!is_writable($styledir) || !@rename($currentpath, $newpath)) {
        return ['success' => false, 'message' => get_string('writefailed', 'local_mobilecssedit'), 'path' => null];
    }

    // Xác nhận lại file mới thực sự nằm trong thư mục cố định (phòng vệ kép).
    $real = realpath($newpath);
    $realstyledir = realpath($styledir);
    if ($real === false || $realstyledir === false || strpos($real, $realstyledir) !== 0) {
        return ['success' => false, 'message' => get_string('invalidurl', 'local_mobilecssedit'), 'path' => null];
    }

    $publicurl = local_mobilecssedit_get_style_url() . '/' . $cleannewname;
    set_config('mobilecssurl' . $companyid, $publicurl, 'theme_th_lambda_st');
    theme_reset_all_caches();

    return ['success' => true, 'message' => get_string('filerenamed', 'local_mobilecssedit'), 'path' => $real];
}
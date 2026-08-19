<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Xác định đường dẫn file vật lý (trên ổ đĩa) tương ứng với giá trị
 * cấu hình "mobilecssurl" theo company hiện đang chỉnh sửa (IOMAD),
 * được lưu bởi theme_th_lambda_st dưới dạng
 * theme_th_lambda_st/mobilecssurl{$companyid}.
 *
 * @param int|null $companyid ID company. Nếu null, lấy từ
 *                             $SESSION->currenteditingcompany.
 * @return string|null Đường dẫn tuyệt đối tới file CSS, hoặc null nếu
 *                      không xác định được / file nằm ngoài mã nguồn Moodle.
 */
function local_mobilecssedit_get_css_path(?int $companyid = null): ?string {
    global $CFG, $SESSION;

    if ($companyid === null) {
        $companyid = !empty($SESSION->currenteditingcompany)
            ? (int) $SESSION->currenteditingcompany
            : 0;
    }
    if (empty($companyid)) {
        // Không có company nào đang được chỉnh sửa -> không có setting để đọc.
        return null;
    }

    $url = get_config('theme_th_lambda_st', 'mobilecssurl' . $companyid);
    if (empty($url)) {
        return null;
    }
    $url = trim($url);

    // Bỏ query string / fragment nếu có (vd: ...aum.css?v=123).
    $url = preg_replace('/[?#].*$/', '', $url);

    // Chuẩn hoá về đường dẫn tương đối so với wwwroot.
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
    $fullpath = $CFG->dirroot . $relative;

    // Chống path traversal: file thực tế phải nằm trong dirroot.
    $real = realpath($fullpath);
    $realdirroot = realpath($CFG->dirroot);
    if ($real === false || $realdirroot === false || strpos($real, $realdirroot) !== 0) {
        return null;
    }

    return $real;
}
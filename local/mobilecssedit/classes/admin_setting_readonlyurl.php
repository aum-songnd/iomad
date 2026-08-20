<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Setting CHỈ HIỂN THỊ (read-only) cho theme_th_lambda_st/mobilecssurl{$companyid}.
 *
 * Trước đây field này là admin_setting_configtext (PARAM_URL) nên admin có
 * thể gõ tay bất kỳ giá trị nào, không tạo file, không kiểm tra tồn tại ->
 * dẫn tới lệch dữ liệu so với local/mobilecssedit/style/: managecss.php
 * đọc file trên đĩa nên coi như "chưa có file" dù setting vẫn còn giá trị
 * cũ trong DB.
 *
 * Từ giờ ô này CHỈ hiển thị giá trị hiện tại (không cho sửa trực tiếp) và
 * dẫn link sang managecss.php - nơi DUY NHẤT được phép tạo/đổi tên/sửa nội
 * dung file CSS, đảm bảo setting luôn khớp với file thật trên đĩa.
 */
class local_mobilecssedit_admin_setting_readonlyurl extends admin_setting {

    /** @var int Company hiện đang chỉnh sửa. */
    protected $companyid;

    public function __construct($name, $visiblename, $description, $companyid) {
        $this->companyid = (int) $companyid;
        parent::__construct($name, $visiblename, $description, '');
    }

    // Không lưu gì qua config_write thông thường - luôn đọc trực tiếp bằng get_config().
    public function get_setting() {
        return get_config('theme_th_lambda_st', 'mobilecssurl' . $this->companyid);
    }

    // Bỏ qua mọi dữ liệu POST lên field này - CHỈ managecss.php (qua
    // local_mobilecssedit_create_css_file()/_rename_css_file()) mới được
    // phép ghi vào theme_th_lambda_st/mobilecssurl{$companyid}.
    public function write_setting($data) {
        return '';
    }

    public function output_html($data, $query = '') {
        global $CFG;

        $managecssurl = new moodle_url('/local/mobilecssedit/managecss.php');

        if (empty($data)) {
            $valuehtml = html_writer::tag('span',
                get_string('mobilecssurl_notset', 'local_mobilecssedit'),
                ['class' => 'text-muted']);
        } else {
            $valuehtml = html_writer::tag('code', s($data));
        }

        $element = html_writer::tag('div', $valuehtml, ['class' => 'mb-1']);
        $element .= html_writer::link(
            $managecssurl,
            get_string('mobilecssurl_managelink', 'local_mobilecssedit'),
            ['class' => 'btn btn-secondary btn-sm']
        );

        return format_admin_setting($this, $this->visiblename, $element,
            $this->description, true, '', null, $query);
    }
}
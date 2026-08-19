<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Setting dạng ô nhập text: admin nhập URL (giống định dạng mobilecssurl,
 * vd https://.../theme/th_lambda_st/style/newcompany.css hoặc đường dẫn
 * tương đối /theme/th_lambda_st/style/newcompany.css). Khi bấm "Save changes"
 * trên trang settings, plugin sẽ:
 *   1. Tạo file CSS tại đúng địa chỉ đó (nếu chưa tồn tại) trong mã nguồn Moodle.
 *   2. Lưu URL đó vào theme_th_lambda_st/mobilecssurl{$companyid}.
 * Lần load trang kế tiếp, settings.php sẽ tự nhận ra file đã tồn tại và
 * chuyển sang hiển thị màn hình chỉnh sửa nội dung (admin_setting_cssfile)
 * như bình thường.
 */
class local_mobilecssedit_admin_setting_createcssfile extends admin_setting {

    /** @var int Company hiện đang chỉnh sửa. */
    protected $companyid;

    public function __construct($name, $visiblename, $description, $companyid) {
        $this->companyid = (int) $companyid;
        parent::__construct($name, $visiblename, $description, '');
    }

    protected function has_manage_capability(): bool {
        return has_capability('local/mobilecssedit:manage', context_system::instance());
    }

    public function get_setting() {
        // Không lưu DB dưới tên setting này - đây chỉ là 1 hành động (action),
        // giá trị thật được lưu vào theme_th_lambda_st/mobilecssurl{$companyid}.
        return '';
    }

    public function write_setting($data) {
        if (!$this->has_manage_capability()) {
            return get_string('nopermission', 'local_mobilecssedit');
        }

        $url = is_string($data) ? trim($data) : '';
        if ($url === '') {
            // Admin không nhập gì -> không làm gì cả, không báo lỗi.
            return '';
        }

        $result = local_mobilecssedit_create_css_file($url, $this->companyid);
        if (!$result['success']) {
            return $result['message'];
        }

        return '';
    }

    public function output_html($data, $query = '') {
        if (!$this->has_manage_capability()) {
            return '';
        }

        $attributes = [
            'type'        => 'text',
            'name'        => $this->get_full_name(),
            'id'          => $this->get_id(),
            'value'       => s($data),
            'size'        => 60,
            'placeholder' => '/local/mobilecssedit/style/tencongty.css',
            'class'       => 'form-control',
        ];

        $input = html_writer::empty_tag('input', $attributes);

        $hint = html_writer::tag('div',
            get_string('createcssfilehint', 'local_mobilecssedit'),
            ['class' => 'small text-muted mt-1']);

        $element = $input . $hint;

        return format_admin_setting($this, $this->visiblename, $element,
            $this->description, true, '', null, $query);
    }
}

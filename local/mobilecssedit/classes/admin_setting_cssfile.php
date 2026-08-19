<?php
defined('MOODLE_INTERNAL') || die();

class local_mobilecssedit_admin_setting_cssfile extends admin_setting {

    /** @var string Đường dẫn tuyệt đối tới file CSS cần sửa. */
    protected $filepath;

    public function __construct($name, $visiblename, $description, $filepath) {
        $this->filepath = $filepath;
        parent::__construct($name, $visiblename, $description, '');
    }

    /**
     * Kiểm tra capability riêng của plugin. Được gọi lại ở đây (ngoài
     * settings.php) để phòng trường hợp write_setting()/output_html()
     * bị gọi trực tiếp từ nơi khác mà không qua màn hình settings.php.
     */
    protected function has_manage_capability(): bool {
        return has_capability('local/mobilecssedit:manage', context_system::instance());
    }

    public function get_setting() {
        return ''; // Không lưu DB, luôn đọc trực tiếp từ file.
    }

    public function write_setting($data) {
        if (!$this->has_manage_capability()) {
            return get_string('nopermission', 'local_mobilecssedit');
        }
        if (!file_exists($this->filepath)) {
            return get_string('cannotwrite', 'local_mobilecssedit') . ' (' . $this->filepath . ')';
        }
        if (!is_writable($this->filepath)) {
            return get_string('notwritable', 'local_mobilecssedit');
        }

        $data = str_replace("\r\n", "\n", $data);
        if (file_put_contents($this->filepath, $data) === false) {
            return get_string('writefailed', 'local_mobilecssedit');
        }

        theme_reset_all_caches();
        return '';
    }

    public function output_html($data, $query = '') {
        if (!$this->has_manage_capability()) {
            $element = html_writer::tag('div',
                get_string('nopermission', 'local_mobilecssedit'),
                ['class' => 'alert alert-warning']);
            return format_admin_setting($this, $this->visiblename, $element,
                $this->description, true, '', null, $query);
        }

        if (!file_exists($this->filepath)) {
            $element = html_writer::tag('div',
                get_string('cannotwrite', 'local_mobilecssedit') . ' ' . s($this->filepath),
                ['class' => 'alert alert-danger']);
            return format_admin_setting($this, $this->visiblename, $element,
                $this->description, true, '', null, $query);
        }

        $filecontent = file_get_contents($this->filepath);

        $info = html_writer::tag('div',
            get_string('editingfile', 'local_mobilecssedit', s($this->filepath)),
            ['class' => 'small text-muted mb-1']);

        $textarea = html_writer::tag('textarea', s($filecontent), [
            'name'  => $this->get_full_name(),
            'id'    => $this->get_id(),
            'rows'  => 25,
            'style' => 'width:100%; font-family:monospace; font-size:13px;',
        ]);

        $element = $info . $textarea;

        return format_admin_setting($this, $this->visiblename, $element,
            $this->description, true, '', null, $query);
    }
}
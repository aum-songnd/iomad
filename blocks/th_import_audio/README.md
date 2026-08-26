# **Tài liệu phát triển tính năng thêm audio hàng loạt vào ngân hàng câu hỏi**

1. **Tên plugin**: th_import_audio
2. **Kiểu plugin**: block
3. **Project**: TNU, AOF, TNUT
4. **Chức năng chung**: Thêm audio hàng loạt vào ngân hàng câu hỏi
5. **Người phát triển**: linhnt720@wru.vn
6. **Người yêu cầu**: Minhpl@aum.edu.vn
7. **Tham chiếu ERP:** TASK-127
8. **Mã nguồn:** https://github.com/thsambala/th/tree/master/blocks/th_import_audio

# 1. Yêu cầu: (bắt buộc)
- Yêu cầu: Thêm audio hàng loạt vào ngân hàng câu hỏi
- Đầu vào: File zip chứa danh sách audio
    - Các audio được đặt tên trùng với tên câu hỏi trong NHCH
    - Nếu muốn thêm audio vào nhiều câu hỏi thì nối các tên câu hỏi cần thêm ngăn cách nhau bằng dấu cộng. 
    - Ví dụ: TDT-2111+TDT-2112+TDT2113.mp3
    
    ![image](https://user-images.githubusercontent.com/57883256/209601843-cdc79897-ec9b-4eab-801b-17e9b202beca.png)
- Đầu ra: Thông báo thêm audio thành công

# 2. Mô tả chi tiết/ hướng dẫn sử dụng/ hướng dẫn cài đặt: (bắt buộc)

- Capability có quyền truy cập và sử chức năng: block/th_import_audio:view

    ```php
    $capabilities = array(
        'block/th_import_audio:view' => array(
            'riskbitmask' => RISK_SPAM | RISK_XSS,
            'captype' => 'write',
            'contextlevel' => CONTEXT_COURSE,
            'archetypes' => array(
                'manager' => CAP_ALLOW
            ),
        ),
    );
    ```

- Giao diện chính chức năng:
![image](https://user-images.githubusercontent.com/57883256/209601722-27a671e3-c206-497d-85fe-b59204ea0a35.png)
    - file zip chứa danh sách audio
    
- Sau khi kéo file zip chứa danh sách audio người dùng chọn gửi để hệ thống trả về giao diện xác nhận như hình dưới:
![image](https://user-images.githubusercontent.com/57883256/209602198-b8919f12-076d-4f3f-b3d9-d714ac5966a4.png)
    - STT
    - Tên audio
    - Tên câu hỏi
    - Câu hỏi
    - Tên khóa học
    - Trạng thái

- Kiểm tra các thông tin đầu vào nếu đã chính xác chọn submit để hệ thống thêm audio vào NHCH

    ![image](https://user-images.githubusercontent.com/57883256/209602469-6d87069b-99a3-4b7e-94c6-a6ba225f7110.png)

- Lưu ý: 
    - Nếu nội dung câu hỏi có chứa cụm từ [audio] thì sẽ chèn audio thay thế cụm từ đó. Nếu không thì sẽ mặc định chèn vào cuối của nội dung câu hỏi
    - Tên câu hỏi không được chứa dấu cộng (+).

# 3. Phân tích thiết kế: database, chú ý về các method, method call flowchart 
**Database:**
- Các bảng cần dùng:
    - course
    - question
    - context
    - question_categories

- Lấy câu hỏi:    
    ```sql
    SELECT q.* FROM {question} as q, {context} as c, {question_categories} as qc WHERE q.name = '$filename' AND q.category= qc.id AND qc.contextid = c.id AND c.contextlevel = '50' AND c.instanceid = '$course_id'
   ```
- Bảng mới: th_log_import_audio

    | Tên         | Kiểu dữ liệu | Chức năng     |
    |-------------|--------------|---------------|
    | id          | int          |               |
    | contextid   | char         |               |
    | option      | int          |               |
    | itemid      | char         |               |
    | filename    | char         | Tên file      |
    | courseid    | int          | Tmã khóa học  |
    | timecreated | int          | Thời gian tạo |

**Method:**
1. Các Method: save_question, file_save_draft_area_files, create_file_from_string, file_get_unused_draft_itemid
2. Chi tiết các Method:
    - save_question
    ```php
    /**
     * Saves (creates or updates) a question.
     *
     * Given some question info and some data about the answers
     * this function parses, organises and saves the question
     * It is used by {@link question.php} when saving new data from
     * a form, and also by {@link import.php} when importing questions
     * This function in turn calls {@link save_question_options}
     * to save question-type specific data.
     *
     * Whether we are saving a new question or updating an existing one can be
     * determined by testing !empty($question->id). If it is not empty, we are updating.
     *
     * The question will be saved in category $form->category.
     *
     * @param object $question the question object which should be updated. For a
     *      new question will be mostly empty.
     * @param object $form the object containing the information to save, as if
     *      from the question editing form.
     * @param object $course not really used any more.
     * @return object On success, return the new question object. On failure,
     *       return an object as follows. If the error object has an errors field,
     *       display that as an error message. Otherwise, the editing form will be
     *       redisplayed with validation errors, from validation_errors field, which
     *       is itself an object, shown next to the form fields. (I don't think this
     *       is accurate any more.)
     */
    public function save_question($question, $form)
    ```
    - file_save_draft_area_files
    ```php
    /**
     * Saves files from a draft file area to a real one (merging the list of files).
    * Can rewrite URLs in some content at the same time if desired.
    *
    * @category files
    * @global stdClass $USER
    * @param int $draftitemid the id of the draft area to use. Normally obtained
    *      from file_get_submitted_draft_itemid('elementname') or similar.
    *      When set to -1 (probably, by a WebService) it won't process file merging, keeping the original state of the file area.
    * @param int $contextid This parameter and the next two identify the file area to save to.
    * @param string $component
    * @param string $filearea indentifies the file area.
    * @param int $itemid helps identifies the file area.
    * @param array $options area options (subdirs=>false, maxfiles=-1, maxbytes=0)
    * @param string $text some html content that needs to have embedded links rewritten
    *      to the @@PLUGINFILE@@ form for saving in the database.
    * @param bool $forcehttps force https urls.
    * @return string|null if $text was passed in, the rewritten $text is returned. Otherwise NULL.
    */
    function file_save_draft_area_files($draftitemid, $contextid, $component, $filearea, 
    $itemid, array $options=null, $text=null, $forcehttps=false)
    ```
    - create_file_from_string
    ```php
    /**
     * Create new file from string - make sure
     * params are valid.
     *
     * @param string $newfilename name of new file
     * @param string $content of file
     * @param int $userid id of author, default $USER->id
     * @return file_info new file
     */
    public function create_file_from_string($newfilename, $content, $userid = NULL)
    ```
    - file_get_unused_draft_itemid
    ```php
    /**
     * Generate a draft itemid
    *
    * @category files
    * @global moodle_database $DB
    * @global stdClass $USER
    * @return int a random but available draft itemid that can be used to create a new draft
    * file area.
    */
    function file_get_unused_draft_itemid()
    ```

# 4. mã nguồn: hướng dẫn viết mã nguồn chi tiết, những thay đổi mã nguồn cần để viết tính năng này (nếu cần)

# 5. Triển khai: (Hướng dẫn triển khai, lưu ý khi upload nên appstore. nếu cần)

# 6. Kiểm thử: (nếu cần)

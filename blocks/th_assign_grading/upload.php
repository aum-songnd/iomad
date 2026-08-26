<?php

require_once '../../config.php';
require_once $CFG->dirroot . '/blocks/th_assign_grading/lib.php';
require_once $CFG->dirroot . '/blocks/th_assign_grading/th_assign_turn_upload_form.php';
require_once $CFG->dirroot . '/course/lib.php';
require_once $CFG->dirroot . '/local/thlib/classes/PHPExcel/IOFactory.php';
require_once $CFG->dirroot . '/local/thlib/classes/PHPExcel.php';
require_once $CFG->dirroot . '/blocks/th_assign_grading/classes/lib.php';
class th_assign_grading_value_binder implements PHPExcel_Cell_IValueBinder {
    public function bindValue(PHPExcel_Cell $cell, $value = null) {
        if (is_string($value)) {
            $value = PHPExcel_Shared_String::SanitizeUTF8($value);
        }

        $cell->setValueExplicit($value, self::dataTypeForValue($value));
        return true;
    }

    public static function dataTypeForValue($pValue = null) {
        if (is_null($pValue)) {
            return PHPExcel_Cell_DataType::TYPE_NULL;
        } elseif ($pValue === '') {
            return PHPExcel_Cell_DataType::TYPE_STRING;
        } elseif ($pValue instanceof PHPExcel_RichText) {
            return PHPExcel_Cell_DataType::TYPE_INLINE;
        } elseif (is_string($pValue) && $pValue[0] === '=' && strlen($pValue) > 1) {
            return PHPExcel_Cell_DataType::TYPE_FORMULA;
        } elseif (is_bool($pValue)) {
            return PHPExcel_Cell_DataType::TYPE_BOOL;
        } elseif (is_float($pValue) || is_int($pValue)) {
            return PHPExcel_Cell_DataType::TYPE_NUMERIC;
        } elseif (is_string($pValue) && preg_match('/^\\-?([0-9]+\\.?[0-9]*|[0-9]*\\.?[0-9]+)$/', $pValue)) {
            return PHPExcel_Cell_DataType::TYPE_NUMERIC;
        } elseif (is_string($pValue) && array_key_exists($pValue, PHPExcel_Cell_DataType::getErrorCodes())) {
            return PHPExcel_Cell_DataType::TYPE_ERROR;
        } else {
            return PHPExcel_Cell_DataType::TYPE_STRING;
        }
    }
}

global $DB, $CFG, $COURSE, $USER;
$courseid = $COURSE->id;
$returnto = optional_param('returnto', 'course', PARAM_ALPHANUM); // Switch trang web chuyển hướng chung.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourse', 'block_th_assign_grading', $courseid);
}

require_login($courseid);
require_capability('block/th_assign_grading:view', context_course::instance($COURSE->id));
$th_assign_turn_upload_key = optional_param('key', 0, PARAM_ALPHANUMEXT);
$pageurl = new moodle_url('/blocks/th_assign_grading/upload.php');
$context = context_system::instance();

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('th_assign_grading', 'block_th_assign_grading'));
$PAGE->set_title($SITE->fullname . ': ' . get_string('title', 'block_th_assign_grading'));
$companyid = 0;
if (class_exists('iomad')) {

    // Set the companyid
    $companyid = iomad::get_my_companyid($context);
    // Kiểm tra cuối cùng
    if (empty($companyid)) {
        print_error('nocompany', 'block_th_assign_grading');
    }
}
$th_seo_upload_form = new th_assign_turn_upload_form();

if (empty($th_assign_turn_upload_key)) {
    if ($th_seo_upload_form->is_cancelled()) {

        $path = $CFG->dataroot;
        // print_object($path);
        // exit;
        if (!file_exists($path . '/th_assign_grading_uploads')) {
            mkdir($path . '/th_assign_grading_uploads', 0744);
        }

        //delete all file upload
        $files = glob("$CFG->dataroot/th_assign_grading_uploads/*"); // get all file names
        foreach ($files as $file) {
            // iterate files
            if (is_file($file)) {
                unlink($file); // delete file
            }
        }

        $returnurl = new moodle_url('/blocks/th_assign_grading');
        redirect($returnurl);
    } else if ($data = $th_seo_upload_form->get_data()) {

        $filename = $th_seo_upload_form->get_new_filename('file');

        $path = $CFG->dataroot;
        if (!file_exists($path . '/th_assign_grading_uploads')) {
            mkdir($path . '/th_assign_grading_uploads', 0744);
        }
        $inputFileName = $CFG->dataroot . '/th_assign_grading_uploads/' . $filename;
        $th_seo_upload_form->save_file('file', $inputFileName, true);

        $old_error_reporting = error_reporting();
        error_reporting($old_error_reporting & ~E_DEPRECATED);
        PHPExcel_Cell::setValueBinder(new th_assign_grading_value_binder());
        $inputFileType = PHPExcel_IOFactory::identify($inputFileName);
        $objReader = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel = $objReader->load($inputFileName);
        error_reporting($old_error_reporting);

        $allsheet = $objPHPExcel->getAllSheets();
        $a = $objPHPExcel->getActiveSheet();

        //Dem so luong sheet.
        // $count_sheet = count($allsheet);
        $count_sheet = 1;
        $data_upload = [];
        for ($i = 0; $i < $count_sheet; ++$i) {

            $sheet = $objPHPExcel->getSheet($i);
            $highestRow = $sheet->getHighestRow();

            // Lấy tổng số cột của file.
            $highestColumn = $sheet->getHighestColumn();

            //  Thực hiện việc lặp qua từng dòng của file, để lấy thông tin
            for ($row = 2; $row <= $highestRow; $row++) {
                // Lấy dữ liệu từng dòng và đưa vào mảng $rowData
                $rowData[$i] = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE);
                $rowData[$i][0][5] = $row;
                $data_upload[] = $rowData[$i];
            }
        }

        $check = [];
        $username_occurrences = [];
        foreach ($data_upload as $value) {
            if (!empty($value[0][0]) && !empty($value[0][1])) {

                if (preg_match('/^[a-zA-Z0-9_.@-]{3,100}$/', $value[0][0]) && is_numeric($value[0][1])) {
                    $username = $value[0][0];
                    $turn_number = $value[0][1];

                    $user = $DB->get_record_sql("SELECT * FROM {user} WHERE username = ?", array($username));
                    if (!empty($user)) {
                        // THÊM KIỂM TRA USERNAME CÓ THUỘC đơn vị KHÔNG
                        $user_in_company = $DB->get_record_sql(
                            "SELECT cu.* 
                            FROM {company_users} cu 
                            WHERE cu.userid = ? AND cu.companyid = ?",
                            array($user->id, $companyid)
                        );
                        if (empty($user_in_company)) {
                            $check['erorr_massage'][] = "username:$username ở dòng {$value[0][5]} không thuộc đơn vị hiện tại. Vui lòng kiểm tra lại!";
                            $check['upload_data'][] = $value;
                            continue; // Bỏ qua các kiểm tra tiếp theo cho user này
                        }
                        // KẾT THÚC KIỂM TRA đơn vị

                        if (!isset($username_occurrences[$username])) {
                            $username_occurrences[$username] = 1;
                        } else {
                            $username_occurrences[$username]++;
                        }
                        //check o day

                        if ($username_occurrences[$username] > 1) {
                            $check['erorr_massage'][] = "username:$username xuất hiện nhiều lần trong file upload! Vui lòng kiểm tra lại dòng {$value[0][5]}.";
                            $check['upload_data'][] = $value;
                        } else if ($turn_number < 0) {
                            $check['erorr_massage'][] = "lượt chấm ở dòng {$value[0][5]} không hợp lệ. Vui lòng kiểm tra lại!";
                            $check['upload_data'][] = $value;
                        } else {
                            $check['upload_data'][] = $value;
                            $check['correct_data'][] = $value;
                            $check['valid'][] = true;
                        }
                    } else {
                        $check['erorr_massage'][] = "username ở dòng {$value[0][5]} không tồn tại. Vui lòng kiểm tra lại!";
                        $check['upload_data'][] = $value;
                    }
                } else {
                    $check['erorr_massage'][] = "username ở dòng {$value[0][5]} không hợp lệ. Vui lòng kiểm tra lại!";
                    $check['upload_data'][] = $value;
                }
            } else if (empty($value[0][0])) {
                $check['erorr_massage'][] = "username ở dòng {$value[0][5]} trong file upload chưa được điền!";
                $check['upload_data'][] = $value;
            } else {
                $check['erorr_massage'][] = "Lượt chấm ở dòng {$value[0][5]} trong file upload chưa được điền!";
                $check['upload_data'][] = $value;
            }
        }


        // Save data in Session.
        $th_assign_turn_upload_key = $course->id . '_' . time();
        $SESSION->local_th_assign_grading[$th_assign_turn_upload_key] = $check;

        //delete all file upload
        $files = glob("$CFG->dataroot/th_assign_grading_uploads/*"); // get all file names
        foreach ($files as $file) {
            // iterate files
            if (is_file($file)) {
                unlink($file); // delete file
            }
        }
    } else {
        echo $OUTPUT->header();
        echo "<center><h4>Quản lý phân chia công việc cho CTV</h4></center>";
        $baseurl = new moodle_url('/blocks/th_assign_grading/upload.php');

        if ($editcontrols = block_th_assign_grading_controls($context, $baseurl)) {
            echo $OUTPUT->render($editcontrols);
        }
        echo $th_seo_upload_form->display();
        echo $OUTPUT->footer();
    }
}


if ($th_assign_turn_upload_key) {
    $form2 = new confirm_form(null, array('th_assign_turn_upload_key' => $th_assign_turn_upload_key));

    if ($form2->is_cancelled()) {

        $path = $CFG->dataroot;
        if (!file_exists($path . '/th_assign_grading_uploads')) {
            mkdir($path . '/th_assign_grading_uploads', 0744);
        }

        //delete all file upload
        $files = glob("$CFG->dataroot/th_assign_grading_uploads/*"); // get all file names
        foreach ($files as $file) {
            // iterate files
            if (is_file($file)) {
                unlink($file); // delete file
            }
        }

        // Cancelled forms redirect to the course main page.
        $returnurl = new moodle_url('/blocks/th_assign_grading/upload.php');
        redirect($returnurl);
    } else if ($formdata = $form2->get_data()) {
        if (
            !empty($th_assign_turn_upload_key) && !empty($SESSION->local_th_assign_grading) &&
            array_key_exists($th_assign_turn_upload_key, $SESSION->local_th_assign_grading)
        ) {

            $data = $SESSION->local_th_assign_grading[$th_assign_turn_upload_key];

            $correct_data = $data['correct_data'];
            // print_object($correct_data);
            // exit;
            foreach ($correct_data as $data) {
                $username = $data[0][0];
                $assign_turn_count = $data[0][1];

                $user = $DB->get_record_sql("SELECT * FROM {user} WHERE username = ?", array($username));
                if (!empty($user)) {
                    $attemp_data = $DB->get_record_sql("SELECT * FROM {th_assign_turns} WHERE userid = $user->id");
                    if (empty($attemp_data)) {
                        $newData = array(
                            'userid' => $user->id,
                            'numberturns' => $assign_turn_count,
                            'unused' => $assign_turn_count
                        );
                        $insert_success = $DB->insert_record('th_assign_turns', (object)$newData);
                    } else {
                        $newData = array(
                            'id' => $attemp_data->id,
                            'userid' => $user->id,
                            'numberturns' => $attemp_data->numberturns + $assign_turn_count,
                            'unused' => $attemp_data->unused + $assign_turn_count
                        );
                        $update_success = $DB->update_record('th_assign_turns', (object)$newData);
                    }
                }
            }

            echo $OUTPUT->header();
            $baseurl = new moodle_url('/blocks/th_assign_grading/upload.php');

            if ($editcontrols = block_th_assign_grading_controls($context, $baseurl)) {
                echo $OUTPUT->render($editcontrols);
            }
            echo $OUTPUT->heading('Gán lượt chấm chữa hàng loạt thành công!');
            echo $OUTPUT->footer();
        }
    } else {

        echo $OUTPUT->header();
        $baseurl = new moodle_url('/blocks/th_assign_grading/upload.php');

        if ($editcontrols = block_th_assign_grading_controls($context, $baseurl)) {
            echo $OUTPUT->render($editcontrols);
        }
        if (!empty($th_assign_turn_upload_key) && !empty($check)) {
            if (!empty($check['erorr_massage'])) {
                $errors = $check['erorr_massage'];
                $html1 = th_send_display_table_error($errors);
                echo $OUTPUT->heading('Gợi ý');
                echo $html1;
            }

            if (!empty($check['correct_data'])) {

                $correct_data = $check['correct_data'];

                $html = th_display_table_valid_users($correct_data);
                echo '<h2 class = "title"><center>Danh sách các người dùng hợp lệ</center></h2>';
                echo $html;
                $lang = current_language();
                echo '<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">';
                $PAGE->requires->js_call_amd('local_thlib/main', 'init', array('.th_display_table_valid_users', 'Danh sách các người dùng hợp lệ', $lang));
            }
        }

        echo $form2->display();
        echo $OUTPUT->footer();
    }
}

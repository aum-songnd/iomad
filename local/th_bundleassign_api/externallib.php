<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/email/lib.php');
require_once "$CFG->libdir/externallib.php";
require_once 'lib.php';
require_once "$CFG->dirroot/user/profile/lib.php";
require_once($CFG->dirroot . '/local/iomad/lib/company.php');
require_once($CFG->dirroot . '/local/iomad/lib/user.php');
// /var/www/html/vmc/user/profile/lib.php


class local_th_bundleassign_api_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function enrolcourse_parameters() {
        global $CFG;
    
        return new external_function_parameters(
            [
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'company_code' => new external_value(PARAM_TEXT, 'Sambala company code', VALUE_OPTIONAL),
                            'userinfo' => new external_single_structure(
                                [
                                    'username' => new external_value(PARAM_TEXT, 'Username', VALUE_OPTIONAL),
                                    'email' => new external_value(PARAM_TEXT, 'Email', VALUE_OPTIONAL),
                                    'phone' => new external_value(PARAM_TEXT, 'Phone number', VALUE_OPTIONAL),
                                    'institution' => new external_value(PARAM_TEXT, 'Institution', VALUE_OPTIONAL),
                                    'th_student_code' => new external_value(PARAM_TEXT, 'Student code', VALUE_OPTIONAL),
                                    'th_crm_code' => new external_value(PARAM_TEXT, 'CRM code', VALUE_OPTIONAL),
                                    'th_dob' => new external_value(PARAM_TEXT, 'Date of birth as timestamp', VALUE_OPTIONAL),
                                    'phonenumber' => new external_value(PARAM_TEXT, 'Phone number', VALUE_OPTIONAL),
                                    'th_student_class' => new external_value(PARAM_TEXT, 'Student class', VALUE_OPTIONAL),
                                    'userfullname' => new external_value(PARAM_TEXT, 'Full name', VALUE_OPTIONAL),
                                    'th_user_role' => new external_value(PARAM_TEXT, 'User role', VALUE_OPTIONAL),
                                    'th_student_cohort' => new external_value(PARAM_TEXT, 'Student cohort', VALUE_OPTIONAL),
                                ],
                                'User information', VALUE_OPTIONAL
                            ),
                            'courses_combo' => new external_multiple_structure(
                                new external_single_structure(
                                    [
                                        'course_combo_shortname' => new external_value(PARAM_TEXT, 'Course combo short name', VALUE_OPTIONAL),
                                        'date_assign' => new external_value(PARAM_FLOAT, 'Date assigned as timestamp', VALUE_OPTIONAL),
                                        'study_time' => new external_value(PARAM_INT, 'Study time in minutes', VALUE_OPTIONAL),
                                        'stage' => new external_value(PARAM_TEXT, 'Stage', VALUE_OPTIONAL),
                                        'slotname' => new external_value(PARAM_TEXT, 'Slot name', VALUE_OPTIONAL),
                                        'course' => new external_value(PARAM_TEXT, 'Course short name', VALUE_OPTIONAL),
                                        'sequence_stage' => new external_value(PARAM_INT, 'Sequence stage', VALUE_OPTIONAL),
                                        'sequence_slot' => new external_value(PARAM_INT, 'Sequence slot', VALUE_OPTIONAL),
                                        'is_introductory_course' => new external_value(PARAM_BOOL, 'Is introductory course', VALUE_OPTIONAL),
                                        'is_assign' => new external_value(PARAM_BOOL, 'Is assigned', VALUE_OPTIONAL),
                                    ]
                                ),
                                'Courses combo', VALUE_OPTIONAL
                            ),
                            'combo_test' => new external_multiple_structure(
                                new external_single_structure(
                                    [   
                                        'course_combo_shortname' => new external_value(PARAM_TEXT, 'Course combo short name', VALUE_OPTIONAL),
                                        'default_code' => new external_value(PARAM_TEXT, 'Course short name', VALUE_OPTIONAL),
                                        'date_assign' => new external_value(PARAM_FLOAT, 'Date assigned as timestamp', VALUE_OPTIONAL),
                                        'study_time' => new external_value(PARAM_INT, 'Study time in minutes', VALUE_OPTIONAL),
                                    ]
                                ),
                                'Courses combo test', VALUE_OPTIONAL
                            ),
                        ]
                    ),
                    'Data array'
                ),
            ]
        );
    }
    
    
    
    /**
     * [enrolcourse] Gán học viên vào khóa học
     * @param   [array] $enrol  Mảng các giá trị(userfullname,phonenumber,email,
     * courseshortname,campaigncode,campaignname,courseprice,ordercode,ordername,description,totalprice)
     * @return  [stdClass]
     */
  
     public static function enrolcourse($data) {
        global $DB, $CFG, $SESSION;
    
        require_once $CFG->libdir . '/enrollib.php';
        require_once $CFG->dirroot . '/user/lib.php';
    
        $results = array();
        $results['data'] = array(); // Khởi tạo mảng data
        
        foreach($data as $item) {
            $user_result = array(
                'company' => '',
                'userinfor' => array(
                    'status' => '',
                    'message' => '',
                    'th_crm_code' => ''
                ),
                'courses' => array()
            );
    
            $companycode = $item['company_code'];
            $companymap = $DB->get_record('th_company', ['company_code' => $companycode], 'id,lms_company_id', IGNORE_MISSING);
            $userinfo = $item['userinfo'];
            $courses_combo = $item['courses_combo'];
            $combo_test = isset($item['combo_test']) ? $item['combo_test'] : [];
    
            // Process company_code
            if (empty($companycode)) {
                $user_result['company'] = 'Mã đơn vị không hợp lệ.';
                $results['data'][] = $user_result;
                return $results;
            } else{
                if (!$companymap || empty($companymap->lms_company_id)) {
                    $user_result['company'] = 'Đơn vị chưa được đồng bộ.';
                    $results['data'][] = $user_result;
                    return $results;
                }
                $user_result['company'] = 'Đơn vị hợp lệ.';
            }
            // Process userinfo
            if ($userinfo) {
                if (isset($userinfo['username'])) {
                    $username = $userinfo['username'];
                } else {
                    $user_result['userinfor']['status'] = 'error';
                    $user_result['userinfor']['message'] = 'Không có Username!';
                    $results['data'][] = $user_result;
                    return $results;
                }

                if (isset($userinfo['userfullname'])) {
                    $userfullname = trim($userinfo['userfullname']);
                    if ($userfullname != '') {
                        if (strlen($userfullname) <= 200) {
                            $userfullname_arr = explode(" ", $userfullname);
                            if (count($userfullname_arr) < 2) {
                                $firstname = "User";
                                $lastname  = $userfullname_arr[0];
                            } else {
                                $firstname = trim(str_replace(end($userfullname_arr), "", $userfullname));
                                $lastname  = trim(end($userfullname_arr));
                            }
                            if (strlen($firstname) > 100) {
                                $user_result['userinfor']['status'] = 'error';
                                $user_result['userinfor']['message'] = 'Tên dài hơn 100 kí tự!';
                                $results['data'][] = $user_result;
                                return $results;
                
                            }
                            if (strlen($lastname) > 100) {
                                $user_result['userinfor']['status'] = 'error';
                                $user_result['userinfor']['message'] = 'Họ dài hơn 100 kí tự!';
                                $results['data'][] = $user_result;
                                return $results;
                            }
                        } else {
                            $user_result['userinfor']['status'] = 'error';
                            $user_result['userinfor']['message'] = 'Họ và tên dài hơn 200 kí tự!';
                            $results['data'][] = $user_result;
                            return $results;
                        }
                    } else {
                        $user_result['userinfor']['status'] = 'error';
                        $user_result['userinfor']['message'] = 'Không có họ và tên!';
                        $results['data'][] = $user_result;
                        return $results;
                    }
                } else {
                    $user_result['userinfor']['status'] = 'error';
                    $user_result['userinfor']['message'] = 'Không có họ và tên!';
                    $results['data'][] = $user_result;
                    return $results;
                }
    
                if (isset($userinfo['phonenumber'])) {
                    $phonenumber = trim($userinfo['phonenumber']);
                    if ($phonenumber === '') {
                        $user_result['userinfor']['status'] = 'error';
                        $user_result['userinfor']['message'] = 'Số điện thoại không hợp lệ!';
                        $results['data'][] = $user_result;
                        return $results;
                    } else {
                        $phone2      = $phonenumber;
                        $phonenumber = th_bundleassign_api_check_phone($phonenumber);
                        if ($phonenumber == false) {
                            $user_result['userinfor']['status'] = 'error';
                            $user_result['userinfor']['message'] = 'Số điện thoại không hợp lệ!';
                            $results['data'][] = $user_result;
                            return $results;
                        }
                        if (strlen($phone2) > 20) {
                            $user_result['userinfor']['status'] = 'error';
                            $user_result['userinfor']['message'] = 'Số điện thoại dài hơn 20 kí tự!';
                            $results['data'][] = $user_result;
                            return $results;
                        }
                    }
                } else {
                    $user_result['userinfor']['status'] = 'error';
                    $user_result['userinfor']['message'] = 'Không có Số điện thoại!';
                    $results['data'][] = $user_result;
                    return $results;
                }
    
                if (isset($userinfo['email'])) {
                    $email = trim($userinfo['email']);
                    if ($email != ''){
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $user_result['userinfor']['status'] = 'error';
                            $user_result['userinfor']['message'] = 'Email không hợp lệ!';
                            $results['data'][] = $user_result;
                            return $results;
                        } 
                    } else{
                        $user_result['userinfor']['status'] = 'error';
                        $user_result['userinfor']['message'] = 'Email không tồn tại!';
                        $results['data'][] = $user_result;
                        return $results;
                    }
                } else {
                    $user_result['userinfor']['status'] = 'error';
                    $user_result['userinfor']['message'] = 'Email không tồn tại!';
                    $results['data'][] = $user_result;
                    return $results;
                }
    
                if (isset($userinfo['th_crm_code'])) {
                    $th_crm_code = trim($userinfo['th_crm_code']);
                } else {
                    $user_result['userinfor']['status'] = 'error';
                    $user_result['userinfor']['message'] = 'Mã KH không tồn tại!';
                    $results['data'][] = $user_result;
                    return $results;
                }
            } else {
                $user_result['userinfor']['status'] = 'error';
                $user_result['userinfor']['message'] = 'Không có thông tin người mua';
                $results['data'][] = $user_result;
                return $results;
            }
    
            // Process courses_combo
            if ($courses_combo) {
                if (!is_array($courses_combo) or empty($courses_combo)) {
                    $user_result['userinfor']['status'] = 'error';
                    $user_result['userinfor']['message'] = 'Danh sách Khóa học không đúng định dạng!';
                    $results['data'][] = $user_result;
                    return $results;
                }
            }elseif ($combo_test) {
                if (!is_array($combo_test) or empty($combo_test)) {
                    $user_result['userinfor']['status'] = 'error';
                    $user_result['userinfor']['message'] = 'Danh sách Khóa học không đúng định dạng!';
                    $results['data'][] = $user_result;
                    return $results;
                }
            }
             else {
                $user_result['userinfor']['status'] = 'error';
                $user_result['userinfor']['message'] = "Không nhận được khóa học!";
                $results['data'][] = $user_result;
                return $results;
            }
    
            // Check if user exists and process accordingly
            if ($user = $DB->get_record('user', array('username' => $username, 'deleted' => 0))) {
                $isusercompany =  $DB->record_exists('company_users', ['companyid' => (int)$companymap->lms_company_id, 'userid' => $user->id]);
                if ($isusercompany) {
                    $user_result['userinfor']['status'] = 'success';
                    $user_result['userinfor']['message'] = 'Tài khoản đã tồn tại!';
                    $user_result['userinfor']['th_crm_code'] = $userinfo['th_crm_code'];
                } else{
                    $user_result['userinfor']['status'] = 'error';
                    $user_result['userinfor']['message'] = 'Tài khoản không thuộc đơn vị!';
                    $user_result['userinfor']['th_crm_code'] = $userinfo['th_crm_code'];
                    $results['data'][] = $user_result;
                    return $results;
                }
            } else {
                // User creation and enrolment logic
                if ($user = $DB->get_record('user', array('phone2' => $userinfo['phonenumber']))) {
                    $isusercompany =  $DB->record_exists('company_users', ['companyid' => (int)$companymap->lms_company_id, 'userid' => $user->id]);
                    if ($isusercompany) {
                        $user_result['userinfor']['status'] = 'success';
                        $user_result['userinfor']['message'] = 'Số điện thoại đã tồn tại!';
                        $user_result['userinfor']['th_crm_code'] = $userinfo['th_crm_code'];
                    } else{
                        $user_result['userinfor']['status'] = 'error';
                        $user_result['userinfor']['message'] = 'Tài khoản không thuộc đơn vị!';
                        $user_result['userinfor']['th_crm_code'] = $userinfo['th_crm_code'];
                        $results['data'][] = $user_result;
                        return $results;
                    }
                } elseif ($user = $DB->get_record('user', array('email' => $userinfo['email']))) {
                    $isusercompany =  $DB->record_exists('company_users', ['companyid' => (int)$companymap->lms_company_id, 'userid' => $user->id]);
                    if ($isusercompany) {
                        $user_result['userinfor']['status'] = 'success';
                        $user_result['userinfor']['message'] = 'Email đã tồn tại!';
                        $user_result['userinfor']['th_crm_code'] = $userinfo['th_crm_code'];
                    } else{
                        $user_result['userinfor']['status'] = 'error';
                        $user_result['userinfor']['message'] = 'Tài khoản không thuộc đơn vị!';
                        $user_result['userinfor']['th_crm_code'] = $userinfo['th_crm_code'];
                        $results['data'][] = $user_result;
                        return $results;
                    }
                } else {
                    try {
                        // Create the user
                        $user = new stdClass();
                        $user->companyid = (int)$companymap->lms_company_id;
                        $user->username = $username;
                        $user->email = $email;
                        $user->firstname = $firstname;
                        $user->lastname = $lastname;
                        // $user->password = '';
                        $user->auth = 'manual';
                        $user->phone2 = $phonenumber;
                        $user->institution = $userinfo['institution'];
                        $user->sendnewpasswordemails = 1;
                        $user->preference_auth_forcepasswordchange = 1;
                        $user->due = 0;
                        $user->profile_field_th_crm_code = $userinfo['th_crm_code'];
                        $user->profile_field_th_student_code = $userinfo['th_student_code'];
                        $user->profile_field_th_student_class = $userinfo['th_student_class'];
                        
                        $user->profile_field_th_dob = $userinfo['th_dob'];
                        $user->profile_field_th_user_role = $userinfo['th_user_role'];
                        $user->profile_field_th_student_cohort = $userinfo['th_student_cohort'];

                        // giả lập company context
                        $companyid = (int)$companymap->lms_company_id;
                        $hascurrenteditingcompany = isset($SESSION->currenteditingcompany);
                        $previouseditingcompany = $hascurrenteditingcompany ? $SESSION->currenteditingcompany : null;
                        $hasfoundcompanyid = property_exists($CFG, 'foundcompanyid');
                        $previousfoundcompanyid = $hasfoundcompanyid ? $CFG->foundcompanyid : null;

                        $SESSION->currenteditingcompany = $companyid;
                        $CFG->foundcompanyid = $companyid;

                        try {
                            $user_id = \company_user::create($user);
                        } finally {
                            if ($hascurrenteditingcompany) {
                                $SESSION->currenteditingcompany = $previouseditingcompany;
                            } else {
                                unset($SESSION->currenteditingcompany);
                            }

                            if ($hasfoundcompanyid) {
                                $CFG->foundcompanyid = $previousfoundcompanyid;
                            } else {
                                unset($CFG->foundcompanyid);
                            }
                        }

                        // Persist custom profile fields using the standard Moodle/Iomad profile API.
                        $user->id = $user_id;
                        profile_save_data($user);
                        \core\event\user_created::create_from_userid($user_id)->trigger();
                        $user_result['userinfor'] = array(
                            'status' => 'success',
                            'message' => 'Người dùng đã được tạo thành công!',
                            'th_crm_code' => $userinfo['th_crm_code']
                        );
                    } catch (Exception $e) {
                        $user_result['userinfor'] = array(
                            'status' => 'error',
                            'message' => 'Không thể tạo người dùng: ' . $e->getMessage(),
                            'th_crm_code' => $userinfo['th_crm_code']
                        );
                    }
                }
            }
    
            // Enroll the user into courses
            $sql = "SELECT * FROM {user} 
                    WHERE deleted = 0 
                    AND (username = :username OR email = :email OR phone2 = :phone2)";
    
            $params = [
                'username' => $username,
                'email' => $email,
                'phone2' => $phone2,
            ];
    
            if ($user = $DB->get_record_sql($sql, $params)) {
                $user_id = $user->id;
                // Nếu user đang bị suspend thì mở lại trước khi enrol
                if (isset($user->suspended) && (int)$user->suspended === 1) {
                    $updateuser = new stdClass();
                    $updateuser->id = $user_id;
                    $updateuser->suspended = 0;
                    $DB->update_record('user', $updateuser);

                    if (!empty($user_result['userinfor']['message'])) {
                        $user_result['userinfor']['message'] .= ' Người dùng đang bị tạm ngưng, đã mở tạm ngưng thành công!';
                    } else {
                        $user_result['userinfor']['status'] = 'success';
                        $user_result['userinfor']['message'] = 'Người dùng đang bị tạm ngưng, đã mở tạm ngưng thành công!';
                        $user_result['userinfor']['th_crm_code'] = $userinfo['th_crm_code'] ?? '';
                    }

                    // cập nhật lại object user để xử lý tiếp trong runtime
                    $user->suspended = 0;
                }
                $has_error = false;
                $tmp = array();
                if (!empty($courses_combo)) {
                    foreach ($courses_combo as $course_combo) {
                        $course = $DB->get_record('course', [
                            'shortname' => $course_combo['course'],
                        ]);
                    
                        if (!$course) {
                            $user_result['courses'][] = [
                                'courseshortname' => $course_combo['course'],
                                'status' => 'error',
                                'message' => 'Không tìm thấy khóa học!',
                                'course_combo_shortname' => $course_combo['course_combo_shortname']
                            ];
                            $has_error = true;
                        }
                        else{
                            $iscompanycourse = $DB->record_exists('company_course', ['companyid' => $companymap->lms_company_id, 'courseid' => (int)$course->id]); 
                            $isopencourse = $DB->record_exists('iomad_courses', ['courseid' => (int)$course->id, 'shared' => 1]);     
                            if (!$iscompanycourse && !$isopencourse) {
                                $user_result['courses'][] = [
                                    'courseshortname' => $course_combo['course'],
                                    'status' => 'error',
                                    'message' => 'Khóa học không thuộc đơn vị!',
                                    'course_combo_shortname' => $course_combo['course_combo_shortname']
                                ];
                                $tmp[$course_combo['course']] = true;
                            }
                        }
                    }
                    if(!empty($tmp)) {
                        $has_error = true;
                    }

                    if ($has_error) {
                        foreach ($courses_combo as $course_combo) {
                            $course = $DB->get_record('course', [
                                'shortname' => $course_combo['course'],]);
                            if(isset($tmp[$course_combo['course']])) {
                                continue; 
                            }
                            if ($course) {
                                $user_result['courses'][] = [
                                    'courseshortname' => $course_combo['course'],
                                    'status' => 'error',
                                    'message' => 'Khóa học hợp lệ nhưng chưa thể gán do có khóa lỗi trong chương trình học!',
                                    'course_combo_shortname' => $course_combo['course_combo_shortname']
                                ];
                            }
                        }
            
                        // Trả kết quả ngay khi có lỗi
                        $results['data'][] = $user_result;
                        return $results;
                    }

                    foreach ($courses_combo as $course_combo) {
                        if($course_combo['is_introductory_course'] == false) {
                            $course = $DB->get_record('course', [
                                'shortname' => $course_combo['course'],

                            ]);
                            $context = $course ? context_course::instance($course->id, IGNORE_MISSING) : null;
                            $instance = $course ? $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']) : null;
        
                            if (!$course) {
                                $user_result['courses'][] = ['courseshortname' => $course_combo['course'], 'status' => 'error', 'message' => 'Không tìm thấy khóa học!', 'course_combo_shortname' => $course_combo['course_combo_shortname']];
                                $has_error = true;
                            } elseif (is_enrolled($context, $user_id)) {
                                $user_result['courses'][] = ['courseshortname' => $course_combo['course'], 'status' => 'success', 'message' => 'Người dùng đã đăng ký khóa học này!', 'course_combo_shortname' => $course_combo['course_combo_shortname']];
                                $has_error = false;
                            } else {
                                if (!$has_error) {
                                    if (isset($course_combo['is_introductory_course']) && $course_combo['is_introductory_course'] == false) { 
                                        if($course_combo['is_assign'] == false) {
                                            $user_result['courses'][] = ['courseshortname' => $course_combo['course'], 'status' => 'waiting', 'message' => 'Người dùng chờ đăng ký vào khóa học này!', 'course_combo_shortname' => $course_combo['course_combo_shortname']];
                                        }else {
                                            $course = $DB->get_record('course', [
                                                'shortname' => $course_combo['course'],
                
                                            ]);
                                            
                                            $context = $course ? context_course::instance($course->id, IGNORE_MISSING) : null;
                                            
                                            if ($instance) {
                                                try {                         
                                                    $plugin = enrol_get_plugin('manual');
                                                    $timestart = time();

                                                    $dateAssign = (int)$course_combo['date_assign']; // timestamp như 1745280000
                                                    $studyMonths = (int)$course_combo['study_time']; // số tháng như 24

                                                    // Tạo đối tượng DateTimeImmutable tại UTC để tránh mọi sai lệch
                                                    $datetime = DateTimeImmutable::createFromFormat('U', $dateAssign, new DateTimeZone('UTC'));

                                                    // Cộng thêm số tháng
                                                    $datetime = $datetime->modify("+{$studyMonths} months");

                                                    // Lấy lại timestamp UTC đúng chuẩn
                                                    $timeend = $datetime->getTimestamp() - (7 * 3600);

                                                    // $timeend = strtotime("+" . ($course_combo['study_time'] ?? 0) . " months", $timestart);
                                                    $plugin->enrol_user($instance, $user_id, 5, $timestart, $timeend);
                                                    $user_result['courses'][] = ['courseshortname' => $course_combo['course'], 'status' => 'success', 'message' => 'Đăng ký thành công!', 'course_combo_shortname' => $course_combo['course_combo_shortname'], 'study_time' => $course_combo['study_time']];
                                                } catch (Exception $e) {
                                                    $user_result['courses'][] = ['courseshortname' => $course_combo['course'], 'status' => 'error', 'message' => 'Đăng ký không thành công: ' . $e->getMessage(), 'course_combo_shortname' => $course_combo['course_combo_shortname']];
                                                }
                                            } 
                                            else {
                                                $user_result['courses'][] = ['courseshortname' => $course_combo['course'], 'status' => 'error', 'message' => 'Không tìm thấy trường hợp đăng ký thủ công!', 'course_combo_shortname' => $course_combo['course_combo_shortname']];
                                            }
                                        }                           
                                    }
                                }else{
                                    if (isset($course_combo['is_introductory_course']) && $course_combo['is_introductory_course'] == false) {
                                        $user_result['courses'][] = ['courseshortname' => $course_combo['course'], 'status' => 'error', 'message' => 'Khóa học hợp lệ nhưng chưa thể gán do có khóa lỗi trong chương trình học!', 'course_combo_shortname' => $course_combo['course_combo_shortname']];
                                        $has_error = true;
                                    }
                                }
                            }
                        }
                        
                    }
                    
                    // If no error, handle introductory courses as needed
                    $target_course_combo = array_filter($courses_combo, fn($c) => $c['is_introductory_course'] ?? false);
                    $target_course_combo = reset($target_course_combo);
    
                    if ($target_course_combo) {
                        
                        $course = $DB->get_record('course', [
                            'shortname' => $target_course_combo['course'],
                        ]);
                        $instance = $course ? $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']) : null;
                        $context = $course ? context_course::instance($course->id, IGNORE_MISSING) : null;

                        // if (is_enrolled($context, $user_id)) {
                           
                        //     $user_result['courses'][] = ['courseshortname' => $target_course_combo['course'], 'status' => 'success', 'message' => 'Người dùng đã đăng ký khóa học này!', 'course_combo_shortname' => $target_course_combo['course_combo_shortname']];
                        // }
                        if(!$course) {
                            $user_result['courses'][] = ['courseshortname' => $target_course_combo['course'], 'status' => 'error', 'message' => 'Không tìm thấy khóa học!', 'course_combo_shortname' => $target_course_combo['course_combo_shortname']];
                        }
                        elseif ($course && $instance) {
                            try {
                                if (!$has_error) {
                                    $plugin = enrol_get_plugin('manual');
                                    $timestart = time();
                                    // $timeend = strtotime("+" . ($target_course_combo['study_time'] ?? 0) . " months", $timestart);

                                    $dateAssign = (int)$target_course_combo['date_assign']; // timestamp như 1745280000
                                    $studyMonths = (int)$target_course_combo['study_time']; // số tháng như 24
                                    
                                    // Tạo đối tượng DateTimeImmutable tại UTC để tránh mọi sai lệch
                                    $datetime = DateTimeImmutable::createFromFormat('U', $dateAssign, new DateTimeZone('UTC'));
                                    
                                    // Cộng thêm số tháng
                                    $datetime = $datetime->modify("+{$studyMonths} months");
                                    
                                    // Lấy lại timestamp UTC đúng chuẩn
                                    $timeend = $datetime->getTimestamp() - (7 * 3600);
    
                                    $plugin->enrol_user($instance, $user_id, 5, $timestart, $timeend); // ID vai trò sinh viên = 5
                                    
                                    $record = (object)[
                                        'userid' => $user_id,
                                        'combo_shortname' => $target_course_combo['course_combo_shortname'],
                                        'date_assign' => $target_course_combo['date_assign'],
                                        'study_time' => $target_course_combo['study_time'],
                                        'status' => 1,
                                        'all_remote_course' => json_encode($courses_combo),
                                    ];
                                    $data = $DB->get_record('th_bundleassign_api', array('userid' => $user_id, 'combo_shortname' => $target_course_combo['course_combo_shortname']), '*', IGNORE_MISSING);
                                    
                                    if ($data) {
                                        $data->status = 1;   
                                        $data->date_assign = $target_course_combo['date_assign'];
                                        $DB->update_record('th_bundleassign_api', $data);
                                    } else {
                                        $DB->insert_record('th_bundleassign_api', $record);
                                    }

                                    $user_result['courses'][] = ['courseshortname' => $target_course_combo['course'], 'status' => 'success', 'message' => 'Đăng ký thành công!', 'course_combo_shortname' => $target_course_combo['course_combo_shortname'], 'study_time' => $target_course_combo['study_time']];
                                } else {
                                    $user_result['courses'][] = ['courseshortname' => $target_course_combo['course'], 'status' => 'error', 'message' => 'Chưa gán khóa học nhập môn!', 'course_combo_shortname' => $target_course_combo['course_combo_shortname'], 'study_time' => $target_course_combo['study_time']];
                                }
                            } catch (Exception $e) {
                                $user_result['courses'][] = ['courseshortname' => $target_course_combo['course'], 'status' => 'error', 'message' => 'Đăng ký không thành công: ' . $e->getMessage(), 'course_combo_shortname' => $target_course_combo['course_combo_shortname']];
                            }
                        } 
                        else {
                            $user_result['courses'][] = ['courseshortname' => $target_course_combo['course'], 'status' => 'error', 'message' => 'Không tìm thấy trường hợp đăng ký thủ công!', 'course_combo_shortname' => $target_course_combo['course_combo_shortname']];
                        }
                    }
                } else { 
                    foreach ($combo_test as $course_item) {
                        $course = $DB->get_record('course', [
                            'shortname' => $course_item['default_code'],
                        ]);
                
                        if (!$course) {
                            $user_result['courses'][] = [
                                'courseshortname' => $course_item['default_code'],
                                'status' => 'error',
                                'message' => 'Không tìm thấy khóa học!',
                                'course_combo_shortname' => '' 
                            ];
                            continue;
                        }
                        else{
                            $iscompanycourse = $DB->record_exists('company_course', ['companyid' => $companymap->lms_company_id, 'courseid' => (int)$course->id]); 
                            $isopencourse = $DB->record_exists('iomad_courses', ['courseid' => (int)$course->id, 'shared' => 1]);     
                            if(!$iscompanycourse && !$isopencourse) {
                                $user_result['courses'][] = [
                                    'courseshortname' => $course_item['default_code'],
                                    'status' => 'error',
                                    'message' => 'Khóa học không thuộc đơn vị!',
                                    'course_combo_shortname' => '' 
                                ];
                                continue;
                            }
                        }
                
                        $context = context_course::instance($course->id, IGNORE_MISSING);
                        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
                
                        if (is_enrolled($context, $user_id)) {
                            $user_result['courses'][] = [
                                'courseshortname' => $course_item['default_code'],
                                'status' => 'success',
                                'message' => 'Người dùng đã đăng ký khóa học này!',
                                'course_combo_shortname' => '' 
                            ];
                        } else {
                            if ($instance) {
                                try {
                                    $plugin = enrol_get_plugin('manual');
                                    $timestart = time();
                                    $timeend = $timestart + ($course_item['study_time'] * 86400);
                
                                    $plugin->enrol_user($instance, $user_id, 5, $timestart, $timeend);
                
                                    $user_result['courses'][] = [
                                        'courseshortname' => $course_item['default_code'],
                                        'status' => 'success',
                                        'message' => 'Đăng ký thành công!',
                                        'study_time' => $course_item['study_time'],
                                        'course_combo_shortname' => $course_item['course_combo_shortname'] 
                                    ];
                                } catch (Exception $e) {
                                    $user_result['courses'][] = [
                                        'courseshortname' => $course_item['default_code'],
                                        'status' => 'error',
                                        'message' => 'Đăng ký không thành công: ' . $e->getMessage(),
                                        'course_combo_shortname' => $course_item['course_combo_shortname'] 
                                    ];
                                }
                            } else {
                                $user_result['courses'][] = [
                                    'courseshortname' => $course_item['default_code'],
                                    'status' => 'error',
                                    'message' => 'Không tìm thấy phương thức ghi danh!',
                                    'course_combo_shortname' => $course_item['course_combo_shortname'] 
                                ];
                            }
                        }
                    }
                }
            }
    
            $results['data'][] = $user_result; // Add the current user result to the main data array
        }
    
        return $results;
    }
    
    
    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 2.2
     */

    public static function enrolcourse_returns() {
        return new external_single_structure(
            array(
                'data' => new external_multiple_structure(  // Thêm khóa 'data' bao bọc kết quả trả về
                    new external_single_structure(
                        array(
                            'company' => new external_value(PARAM_TEXT, 'Company validation message', VALUE_OPTIONAL),
                            'userinfor' => new external_single_structure(
                                array(
                                    'status' => new external_value(PARAM_TEXT, 'The status of user creation or update'),
                                    'message' => new external_value(PARAM_TEXT, 'The message related to user creation or update'),
                                    'th_crm_code' => new external_value(PARAM_TEXT, 'CRM code of the user')  // Thêm 'th_crm_code' nếu cần
                                )
                            ),
                            'courses' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'courseshortname' => new external_value(PARAM_TEXT, 'The short name of the course'),
                                        'status' => new external_value(PARAM_TEXT, 'The status of enrolment'),
                                        'message' => new external_value(PARAM_TEXT, 'The message about the course enrolment'),
                                        'course_combo_shortname' => new external_value(PARAM_TEXT, 'The short name of the combo course')
                                    )
                                )
                            )
                        )
                    )
                )
            )
        );
    }
    

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function unenrolcourse_parameters() {
        return new external_function_parameters(
            array(
                'unenrol' => new external_single_structure(
                    array(
                        'company_code' => new external_value(PARAM_TEXT, 'Sambala company code', VALUE_OPTIONAL),
                        'username' => new external_value(PARAM_TEXT, 'The username of the user to unenrol'),
                        'course_combo_shortname' => new external_value(PARAM_TEXT, 'The short name of the course combo', VALUE_DEFAULT, ''),
                        'courseshortname' => new external_multiple_structure(
                            new external_value(PARAM_TEXT, 'The short name of a course'),
                            'List of course shortnames to unenrol from'
                        )
                    )
                )
            )
        );
    }
    
    /**
     * [unenrolcourse] Gán học viên vào khóa học
     * @param   [array] $unenrol  Mảng các giá trị(userfullname,phonenumber,email,
     * courseshortname,campaigncode,campaignname,courseprice,ordercode,ordername,description,totalprice)
     * @return  [stdClass]
     */
    public static function unenrolcourse($unenrol) {
        global $DB;
    
        // Validate the input parameters
        $params = self::validate_parameters(self::unenrolcourse_parameters(), ['unenrol' => $unenrol]);
    
        // Extract the input values
        $username = $params['unenrol']['username'];
        $companycode = $params['unenrol']['company_code'];
        $course_combo_shortname = $params['unenrol']['course_combo_shortname'];
        $courseshortnames = $params['unenrol']['courseshortname'];
    
        $results = [
            'company' => '',
            'username' => '',
            'course_combo' => []
        ];

        if (empty($companycode)) {
            $results['company'] = 'Mã đơn vị không hợp lệ.';
            return $results;
        } else {
            $companymap = $DB->get_record('th_company', ['company_code' => $companycode], 'id,lms_company_id', IGNORE_MISSING);
            if (!$companymap || empty($companymap->lms_company_id)) {
                $results['company'] = 'Đơn vị chưa được đồng bộ.';
                return $results;
            }else{
                $results['company'] = 'Đơn vị hợp lệ.';
                $lmscompanyid = (int)$companymap->lms_company_id;
                // Check if the user exists
                $user = $DB->get_record('user', ['username' => $username], 'id, username');
                if (!$user) {
                    $results['username'] = "Người dùng có username '{$username}' không tồn tại.";
                    return $results;
                } else{
                    $isusercompany =  $DB->record_exists('company_users', ['companyid' => $lmscompanyid, 'userid' => $user->id]);
                    if(!$isusercompany) {
                        $results['username'] = "Người dùng có username '{$username}' không thuộc đơn vị.";
                        return $results;
                    } else{
                        $results['username'] = "Người dùng có username '{$username}' hợp lệ.";
                    }
                }
            }
        }
    
        // Prepare results for each course
        $course_combo_results = [];
    
        foreach ($courseshortnames as $courseshortname) {
            $courseshortname = trim($courseshortname);
    
            // Default result structure
            $result = [
                'status' => 'failed',
                'courseshortname' => $courseshortname,
                'message' => '',
                'course_combo_shortname' => $course_combo_shortname
            ];
    
            if (empty($courseshortname)) {
                $result['message'] = 'Course shortname is empty';
                $course_combo_results[] = $result;
                continue;
            }
    
            // Get the course record
            $course = $DB->get_record('course', ['shortname' => $courseshortname], 'id, shortname');
            if (!$course) {
                $result['message'] = 'Course not found';
                $course_combo_results[] = $result;
                continue;
            }

            // check company course
            $iscompanycourse = $DB->record_exists('company_course', ['companyid' => $lmscompanyid, 'courseid' => (int)$course->id]);
            $issharedcourse = $DB->record_exists('iomad_courses', ['courseid' => (int)$course->id, 'shared' => 1]);
            if (!$iscompanycourse && !$issharedcourse) {
                $result['message'] = 'Course does not belong to company or is not shared.';
                $course_combo_results[] = $result;
                continue;
            }
    
            // Attempt to unenrol the user from the course
            $enrol = enrol_get_plugin('manual');
            if ($enrol && $user && $course) {
                $instances = enrol_get_instances($course->id, true);
                $unenrolled = false;
    
                foreach ($instances as $instance) {
                    if ($instance->enrol === 'manual') {
                        $enrol->unenrol_user($instance, $user->id);
                        $unenrolled = true;
                        break;
                    }
                }
    
                if ($unenrolled && $user) {
                    $result['status'] = 'success';
                    $result['message'] = 'Unenrolled successfully';
                
                    $data = $DB->get_record('th_bundleassign_api', array('userid' => $user->id, 'combo_shortname' => $course_combo_shortname), '*', IGNORE_MISSING);
                    
                    if ($data) {
                        $DB->delete_records('th_bundleassign_api', array('id' => $data->id)); // Xóa bản ghi
                    }
                
                } else {
                    $result['message'] = 'No valid enrolment instances found';
                }
                
            } else {
                $result['message'] = 'Enrol plugin not found';
            }
    
            // Append the result
            $course_combo_results[] = $result;
        }
    
        // Return the response structure
        $results['course_combo'] = $course_combo_results;
        return $results;
    }
    
    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 2.2
     */
    public static function unenrolcourse_returns() {
        return new external_single_structure(
            array(
                'company' => new external_value(PARAM_TEXT, 'Company validation message', VALUE_OPTIONAL),
                'username' => new external_value(PARAM_TEXT, 'The username of the user'),
                'course_combo' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'status' => new external_value(PARAM_TEXT, 'The status of the unenrolment (success or failed)'),
                            'courseshortname' => new external_value(PARAM_TEXT, 'The short name of the course'),
                            'message' => new external_value(PARAM_TEXT, 'Message related to the unenrolment result'),
                            'course_combo_shortname' => new external_value(PARAM_TEXT, 'The short name of the combo course')
                        )
                    )
                )
            )
        );
    }


    public static function test_connection_parameters() {
        return new external_function_parameters([]);
    }

    public static function test_connection() {
        try {
            global $DB;
            $DB->get_record_select('user', 'id = 1');
            return ['status' => 'success', 'message' => 'API is reachable'];
        } catch (Exception $e) {
            return ['status' => 'failed', 'message' => 'API connection failed: ' . $e->getMessage()];
        }
    }

    public static function test_connection_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'API connection status'),
            'message' => new external_value(PARAM_TEXT, 'API connection message')
        ]);
    }

    public static function updateuser_parameters() {
        return new external_function_parameters([
            'userinfo' => new external_single_structure([
                'userfullname' => new external_value(PARAM_TEXT,  'User fullname', VALUE_OPTIONAL),
                'phonenumber'  => new external_value(PARAM_TEXT,  'Phone number', VALUE_OPTIONAL),
                'email'        => new external_value(PARAM_EMAIL, 'User email', VALUE_OPTIONAL),
                'th_crm_code'  => new external_value(PARAM_TEXT,  'CRM code', VALUE_REQUIRED),
                'th_dob'       => new external_value(PARAM_TEXT,  'Date of birth (YYYY-MM-DD)', VALUE_OPTIONAL),
    
                // NEW (OPTIONAL)
                'institution'       => new external_value(PARAM_TEXT, 'Institution', VALUE_OPTIONAL),
                'th_student_code'   => new external_value(PARAM_TEXT, 'Student code', VALUE_OPTIONAL),
                'th_student_class'  => new external_value(PARAM_TEXT, 'Student class', VALUE_OPTIONAL),
                'th_student_cohort' => new external_value(PARAM_TEXT, 'Student cohort', VALUE_OPTIONAL),
            ], 'User information'),
        ]);
    }
    
    public static function updateuser($userinfo) {
        global $DB, $CFG;
    
        require_once $CFG->libdir . '/enrollib.php';
        require_once $CFG->dirroot . '/user/lib.php';
    
        $results = ['message' => '', 'status' => ''];
    
        // Init vars to avoid notices.
        $firstname = null; $lastname = null;
        $phonenumber = null; $phone2 = null;
        $email = null;
        $th_crm_code = null;
    
        // NEW optional vars
        $institution = null;
        $th_student_code = null;
        $th_student_class = null;
        $th_student_cohort = null;
    
        // Helper: upsert custom profile field (user_info_data).
        $upsert_profile_field = function(int $userid, string $shortname, string $value) use ($DB, &$results) {
            $field = $DB->get_record('user_info_field', ['shortname' => $shortname], '*', IGNORE_MISSING);
            if (!$field) {
                $results['message'] .= "Không tìm thấy field ({$shortname}). ";
                $results['status'] = 'error';
                return;
            }
    
            $existing = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $field->id], '*', IGNORE_MISSING);
            if ($existing) {
                $existing->data = $value;
                $DB->update_record('user_info_data', $existing);
            } else {
                $newdata = new stdClass();
                $newdata->userid     = $userid;
                $newdata->fieldid    = $field->id;
                $newdata->data       = $value;
                $newdata->dataformat = 0;
                $DB->insert_record('user_info_data', $newdata);
            }
        };
    
        if (empty($userinfo) || !is_array($userinfo)) {
            $results['message'] .= 'Không có thông tin thay đổi. ';
            $results['status'] = 'error';
            return $results;
        }
    
        // === VALIDATE INPUTS (giữ logic cũ: chỉ xử lý khi field có dữ liệu) ===
    
        // Họ tên
        if (!empty($userinfo['userfullname'])) {
            $userfullname = trim($userinfo['userfullname']);
            if ($userfullname !== '') {
                if (strlen($userfullname) <= 200) {
                    $userfullname_arr = explode(' ', $userfullname);
                    if (count($userfullname_arr) < 2) {
                        $firstname = 'User';
                        $lastname  = $userfullname_arr[0];
                    } else {
                        $firstname = trim(str_replace(end($userfullname_arr), '', $userfullname));
                        $lastname  = trim(end($userfullname_arr));
                    }
    
                    if (strlen($firstname) > 100) {
                        $results['message'] .= 'Tên dài hơn 100 kí tự. ';
                        $results['status'] = 'error';
                    }
                    if (strlen($lastname) > 100) {
                        $results['message'] .= 'Họ dài hơn 100 kí tự. ';
                        $results['status'] = 'error';
                    }
                } else {
                    $results['message'] .= 'Họ và tên dài hơn 200 kí tự. ';
                    $results['status'] = 'error';
                }
            } else {
                $results['message'] .= 'Không có họ và tên. ';
                $results['status'] = 'error';
            }
        }
    
        // Số điện thoại
        if (!empty($userinfo['phonenumber'])) {
            $phonenumber = trim($userinfo['phonenumber']);
            if ($phonenumber === '') {
                $results['message'] .= 'Số điện thoại không hợp lệ. ';
                $results['status'] = 'error';
            } else {
                $phone2 = $phonenumber;
                $phonenumber = th_bundleassign_api_check_phone($phonenumber);
                if ($phonenumber == false) {
                    $results['message'] .= 'Số điện thoại không hợp lệ. ';
                    $results['status'] = 'error';
                }
                if (strlen($phone2) > 20) {
                    $results['message'] .= 'Số điện thoại dài hơn 20 kí tự. ';
                    $results['status'] = 'error';
                }
            }
        }
    
        // Email: nếu có gửi lên key email (kể cả rỗng) thì xử lý; nếu rỗng thì fallback nomail (khi có phone)
        if (array_key_exists('email', $userinfo)) {
            $emailraw = trim((string)$userinfo['email']);
            if ($emailraw !== '') {
                if (!filter_var($emailraw, FILTER_VALIDATE_EMAIL)) {
                    $results['message'] .= 'Email không hợp lệ. ';
                    $results['status'] = 'error';
                } else if (strlen($emailraw) > 100) {
                    $results['message'] .= 'Email dài hơn 100 kí tự. ';
                    $results['status'] = 'error';
                } else {
                    $email = $emailraw;
                }
            } else {
                // email rỗng: chỉ fallback nếu có phone hợp lệ
                if (!empty($phonenumber)) {
                    $email = $phonenumber . '@nomail.com';
                    if (strlen($email) > 100) {
                        $results['message'] .= 'Email dài hơn 100 kí tự. ';
                        $results['status'] = 'error';
                    }
                }
            }
        }
    
        // CRM code (REQUIRED)
        if (!empty($userinfo['th_crm_code'])) {
            $th_crm_code = trim($userinfo['th_crm_code']);
        } else {
            $results['message'] .= 'Mã KH không tồn tại. ';
            $results['status'] = 'error';
        }
    
        // DOB (optional) - nếu có thì validate format YYYY-MM-DD
        $dob = null;
        if (!empty($userinfo['th_dob'])) {
            $dob = trim($userinfo['th_dob']);
            if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                $results['message'] .= 'Ngày sinh không đúng định dạng YYYY-MM-DD. ';
                $results['status'] = 'error';
            }
        }
    
        // === NEW 4 FIELDS (OPTIONAL: có thì update, không có thì bỏ qua, rỗng thì bỏ qua) ===
    
        if (array_key_exists('institution', $userinfo)) {
            $institution = trim((string)$userinfo['institution']);
            if ($institution === '') {
                $institution = null; // bỏ qua
            } else if (strlen($institution) > 40) {
                $results['message'] .= 'Institution dài hơn 40 kí tự. ';
                $results['status'] = 'error';
            }
        }
    
        if (array_key_exists('th_student_code', $userinfo)) {
            $th_student_code = trim((string)$userinfo['th_student_code']);
            if ($th_student_code === '') {
                $th_student_code = null; // bỏ qua
            } else if (strlen($th_student_code) > 255) {
                $results['message'] .= 'Mã học viên dài hơn 255 kí tự. ';
                $results['status'] = 'error';
            }
        }
    
        if (array_key_exists('th_student_class', $userinfo)) {
            $th_student_class = trim((string)$userinfo['th_student_class']);
            if ($th_student_class === '') {
                $th_student_class = null; // bỏ qua
            } else if (strlen($th_student_class) > 255) {
                $results['message'] .= 'Lớp học dài hơn 255 kí tự. ';
                $results['status'] = 'error';
            }
        }
    
        if (array_key_exists('th_student_cohort', $userinfo)) {
            $th_student_cohort = trim((string)$userinfo['th_student_cohort']);
            if ($th_student_cohort === '') {
                $th_student_cohort = null; // bỏ qua
            } else if (strlen($th_student_cohort) > 255) {
                $results['message'] .= 'Khóa tuyển sinh dài hơn 255 kí tự. ';
                $results['status'] = 'error';
            }
        }
    
        // Nếu có lỗi validate thì trả về luôn.
        if (!empty($results['message'])) {
            if (empty($results['status'])) {
                $results['status'] = 'error';
            }
            return $results;
        }
    
        // === FIND USER BY th_crm_code ===
        $crmfield = $DB->get_record('user_info_field', ['shortname' => 'th_crm_code'], '*', IGNORE_MISSING);
        if (!$crmfield) {
            $results['status'] = 'error';
            $results['message'] .= 'Không tìm thấy field CRM (th_crm_code).';
            return $results;
        }
    
        $sql = "SELECT u.*
                  FROM {user} u
                  JOIN {user_info_data} uid ON uid.userid = u.id
                 WHERE uid.fieldid = :fieldid
                   AND uid.data    = :crmcode
                   AND u.deleted   = 0";
        $user = $DB->get_record_sql($sql, ['fieldid' => $crmfield->id, 'crmcode' => $th_crm_code]);
    
        if (!$user) {
            $results['status'] = 'error';
            $results['message'] .= 'Không tìm thấy người dùng với mã CRM này.';
            return $results;
        }
    
        // === BUILD UPDATE OBJECT ===
        $u = new stdClass();
        $u->id = (int)$user->id;
    
        if (!empty($email)) {
            $u->email = $email;
        }
        if (!empty($firstname) && !empty($lastname)) {
            $u->firstname = $firstname;
            $u->lastname  = $lastname;
        }
    
        // NEW: core field institution
        if ($institution !== null) {
            $u->institution = $institution;
        }
    
        // Update username nếu là Học viên
        if (!empty($phonenumber)) {
            $fieldrole = $DB->get_record('user_info_field', ['shortname' => 'th_user_role'], '*', IGNORE_MISSING);
            if (!$fieldrole) {
                $results['message'] .= 'Không tìm thấy field vai trò (th_user_role). ';
                $results['status'] = 'error';
                return $results;
            }
    
            $userroledata = $DB->get_record('user_info_data', [
                'userid'  => $user->id,
                'fieldid' => $fieldrole->id,
            ], '*', IGNORE_MISSING);
    
            if (!$userroledata || strcasecmp(trim((string)$userroledata->data), 'Học viên') !== 0) {
                $results['message'] .= 'Chỉ cập nhật username cho người dùng có vai trò Học viên. ';
                $results['status'] = 'error';
                return $results;
            }
    
            $conflict = $DB->get_record('user', [
                'username'   => $phonenumber,
                'mnethostid' => $CFG->mnet_localhost_id,
                'deleted'    => 0,
            ], '*', IGNORE_MISSING);
    
            if ($conflict && (int)$conflict->id !== (int)$user->id) {
                $results['message'] .= 'Username (số điện thoại) đã tồn tại trong hệ thống. ';
                $results['status'] = 'error';
                return $results;
            }
    
            $u->username = $phonenumber;
            $u->phone2   = $phone2;
        }
    
        // === SAVE core user fields ===
        user_update_user($u, false, false);
    
        // === SAVE custom profile fields (optional) ===
        if (!empty($dob)) {
            $upsert_profile_field((int)$user->id, 'th_dob', $dob);
        }
    
        // NEW optional custom fields
        if ($th_student_code !== null) {
            $upsert_profile_field((int)$user->id, 'th_student_code', $th_student_code);
        }
        if ($th_student_class !== null) {
            $upsert_profile_field((int)$user->id, 'th_student_class', $th_student_class);
        }
        if ($th_student_cohort !== null) {
            $upsert_profile_field((int)$user->id, 'th_student_cohort', $th_student_cohort);
        }
    
        // Nếu upsert báo lỗi field thiếu thì status đã bị set error trong helper.
        if (!empty($results['status']) && $results['status'] === 'error') {
            return $results;
        }
    
        $results['status']  = 'success';
        $results['message'] = 'Cập nhật thành công.';
        return $results;
    }
    
    public static function updateuser_returns() {
        return new external_single_structure([
            'message' => new external_value(PARAM_TEXT, 'Message about the result'),
            'status'  => new external_value(PARAM_TEXT, 'Status: success or error'),
        ]);
    }
    
    public static function get_users_by_combo_parameters() {
        return new external_function_parameters([
            // Dùng VALUE_DEFAULT để tránh debugging() khi param optional không có default
            'course_combo_shortname' => new external_value(
                PARAM_TEXT,
                'Combo shortname (combo_shortname)',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }
    
    public static function get_users_by_combo($course_combo_shortname = '') {
        global $DB;
    
        $params = self::validate_parameters(
            self::get_users_by_combo_parameters(),
            ['course_combo_shortname' => $course_combo_shortname]
        );
    
        $combo = trim((string)($params['course_combo_shortname'] ?? ''));
    
        if ($combo === '') {
            return [
                'message' => 'Thiếu tham số course_combo_shortname hoặc giá trị rỗng.',
                'data' => []
            ];
        }
    
        $crmfield = $DB->get_record('user_info_field', ['shortname' => 'th_crm_code'], 'id', IGNORE_MISSING);
        if (!$crmfield) {
            return [
                'message' => 'Không tìm thấy custom profile field th_crm_code trong hệ thống.',
                'data' => []
            ];
        }
    
        $sql = "
            SELECT
                COALESCE(uid.data, '') AS th_crm_code,
                tba.date_assign AS date_assign
            FROM {th_bundleassign_api} tba
            JOIN {user} u ON u.id = tba.userid AND u.deleted = 0
            LEFT JOIN {user_info_data} uid
                   ON uid.userid = u.id
                  AND uid.fieldid = :crmfieldid
            WHERE tba.combo_shortname = :combo
            ORDER BY tba.date_assign DESC, u.id ASC
        ";
    
        $records = $DB->get_records_sql($sql, [
            'crmfieldid' => (int)$crmfield->id,
            'combo'      => $combo,
        ]);
    
        $data = [];
        foreach ($records as $r) {
            $data[] = [
                'th_crm_code' => (string)($r->th_crm_code ?? ''),
                'date_assign' => (int)$r->date_assign,
            ];
        }
    
        if (empty($data)) {
            return [
                'message' => "Không có dữ liệu cho course_combo_shortname = {$combo}.",
                'data' => []
            ];
        }
    
        return [
            'message' => 'Lấy dữ liệu thành công.',
            'data' => $data
        ];
    }
    
    public static function get_users_by_combo_returns() {
        return new external_single_structure([
            'message' => new external_value(PARAM_TEXT, 'Result message'),
            'data' => new external_multiple_structure(
                new external_single_structure([
                    'th_crm_code' => new external_value(PARAM_TEXT, 'CRM code'),
                    'date_assign' => new external_value(PARAM_INT, 'Date assign (timestamp)'),
                ])
            ),
        ]);
    }
    
    
    
    public static function update_all_remote_course_parameters() {
        return new external_function_parameters([
            'userinfor' => new external_single_structure([
                'th_crm_code' => new external_value(PARAM_TEXT, 'CRM code', VALUE_REQUIRED),
            ]),
    
            // Nghiệp vụ update thì bắt buộc có courses_combo
            'courses_combo' => new external_multiple_structure(
                new external_single_structure([
                    // Dùng VALUE_DEFAULT để tránh debugging()
                    'course_combo_shortname' => new external_value(PARAM_TEXT, 'Course combo short name', VALUE_DEFAULT, ''),
                    'date_assign' => new external_value(PARAM_FLOAT, 'Date assigned as timestamp', VALUE_DEFAULT, 0),
                    'study_time' => new external_value(PARAM_INT, 'Study time', VALUE_DEFAULT, 0),
                    'stage' => new external_value(PARAM_TEXT, 'Stage', VALUE_DEFAULT, ''),
                    'slotname' => new external_value(PARAM_TEXT, 'Slot name', VALUE_DEFAULT, ''),
                    'course' => new external_value(PARAM_TEXT, 'Course short name', VALUE_DEFAULT, ''),
                    'sequence_stage' => new external_value(PARAM_INT, 'Sequence stage', VALUE_DEFAULT, 0),
                    'sequence_slot' => new external_value(PARAM_INT, 'Sequence slot', VALUE_DEFAULT, 0),
                    'is_introductory_course' => new external_value(PARAM_BOOL, 'Is introductory course', VALUE_DEFAULT, 0),
                    'is_assign' => new external_value(PARAM_BOOL, 'Is assigned', VALUE_DEFAULT, 0),
                ]),
                'Courses combo',
                VALUE_REQUIRED
            ),
        ]);
    }
    
    public static function update_all_remote_course($userinfor, $courses_combo) {
        global $DB;
    
        try {
            $params = self::validate_parameters(
                self::update_all_remote_course_parameters(),
                ['userinfor' => $userinfor, 'courses_combo' => $courses_combo]
            );
    
            $crm = trim((string)($params['userinfor']['th_crm_code'] ?? ''));
            if ($crm === '') {
                return ['status' => 1, 'message' => 'Thiếu th_crm_code.'];
            }
    
            if (empty($params['courses_combo']) || !is_array($params['courses_combo'])) {
                return ['status' => 1, 'message' => 'Thiếu courses_combo hoặc sai định dạng.'];
            }
    
            // --- Find user by th_crm_code ---
            $crmfield = $DB->get_record('user_info_field', ['shortname' => 'th_crm_code'], 'id', IGNORE_MISSING);
            if (!$crmfield) {
                return ['status' => 1, 'message' => 'Không tìm thấy field th_crm_code trong hệ thống.'];
            }
    
            $sqluser = "SELECT u.id
                          FROM {user} u
                          JOIN {user_info_data} uid ON uid.userid = u.id
                         WHERE u.deleted = 0
                           AND uid.fieldid = :fieldid
                           AND uid.data = :crm";
            $user = $DB->get_record_sql($sqluser, ['fieldid' => (int)$crmfield->id, 'crm' => $crm], IGNORE_MISSING);
    
            if (!$user) {
                return ['status' => 1, 'message' => 'Không tìm thấy user theo th_crm_code.'];
            }
    
            $userid = (int)$user->id;
    
            // --- Determine combo_shortname to update ---
            $combo_shortname = '';
    
            // ưu tiên item is_introductory_course = true
            foreach ($params['courses_combo'] as $c) {
                if (!empty($c['is_introductory_course']) && !empty($c['course_combo_shortname'])) {
                    $combo_shortname = trim((string)$c['course_combo_shortname']);
                    break;
                }
            }
    
            // nếu không có introductory, lấy item đầu tiên có course_combo_shortname
            if ($combo_shortname === '') {
                foreach ($params['courses_combo'] as $c) {
                    if (!empty($c['course_combo_shortname'])) {
                        $combo_shortname = trim((string)$c['course_combo_shortname']);
                        break;
                    }
                }
            }
    
            if ($combo_shortname === '') {
                return ['status' => 1, 'message' => 'Không xác định được course_combo_shortname trong courses_combo.'];
            }
    
            // --- Update th_bundleassign_api ---
            $record = $DB->get_record(
                'th_bundleassign_api',
                ['userid' => $userid, 'combo_shortname' => $combo_shortname],
                '*',
                IGNORE_MISSING
            );
    
            if (!$record) {
                return ['status' => 1, 'message' => 'Không tìm thấy bản ghi th_bundleassign_api theo userid + combo_shortname.'];
            }
    
            $record->all_remote_course = json_encode($params['courses_combo'], JSON_UNESCAPED_UNICODE);
    
            $DB->update_record('th_bundleassign_api', $record);
    
            return ['status' => 0, 'message' => 'Cập nhật all_remote_course thành công.'];
    
        } catch (Exception $e) {
            return ['status' => 1, 'message' => 'Lỗi cập nhật: ' . $e->getMessage()];
        }
    }
    
    public static function update_all_remote_course_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_INT, '0 success / 1 failed'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }    
    
    public static function company_parameters() {
        return new external_function_parameters(
            [
                'name' => new external_value(PARAM_TEXT, 'Company name', VALUE_DEFAULT, ''),
                'company_code' => new external_value(PARAM_TEXT, 'Company code', VALUE_DEFAULT, ''),
                'company_code_old' => new external_value(PARAM_TEXT, 'New company code for update (required for update)', VALUE_DEFAULT, ''),
            ]
        );
    }

    public static function company($name = '', $company_code = '', $company_code_old = '') {
        global $DB;

        $params = self::validate_parameters(self::company_parameters(), [
            'name' => $name,
            'company_code' => $company_code,
            'company_code_old' => $company_code_old,
        ]);

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'POST');

        $name = trim($params['name']);
        $company_code = trim($params['company_code']);
        $company_code_old = trim($params['company_code_old']);

        if ($method === 'POST') {
            if ($name === '') {
                return ['status' => 'failed', 'message' => 'Name is required.'];
            }
            if (core_text::strlen($name) > 255) {
                return ['status' => 'failed', 'message' => 'Name exceeds 255 characters.'];
            }
            if ($company_code === '') {
                return ['status' => 'failed', 'message' => 'Company code is required.'];
            }
            if (core_text::strlen($company_code) > 255) {
                return ['status' => 'failed', 'message' => 'Company code exceeds 255 characters.'];
            }

            $record = $DB->get_record('th_company', ['company_code' => $company_code], '*', IGNORE_MISSING);
            if ($record) {
                return ['status' => 'failed', 'message' => 'Company code already exists.'];
            }

            $newrecord = new stdClass();
            $newrecord->name = $name;
            $newrecord->company_code = $company_code;
            $DB->insert_record('th_company', $newrecord);

            // Ghi logs
            $event = \local_th_bundleassign_api\event\th_company_created::create([
                'context' => \context_system::instance(),
                'other' => $params,
            ]);
            $event->trigger();

            return ['status' => 'success', 'message' => 'Company created successfully.'];
        }

        if ($method === 'PUT') {
            if ($company_code_old === '') {
                return ['status' => 'failed', 'message' => 'Old company code is required for update.'];
            }
            if ($name === '' && $company_code === '') {
                return ['status' => 'failed', 'message' => 'Name or company code is required.'];
            }
            if ($name != '' && core_text::strlen($name) > 255) {
                return ['status' => 'failed', 'message' => 'Name exceeds 255 characters.'];
            }
            if ($company_code != '' && core_text::strlen($company_code) > 255) {
                return ['status' => 'failed', 'message' => 'Company code exceeds 255 characters.'];
            }
            // kiểm tra company_code_old tồn tại
            $record = $DB->get_record('th_company', ['company_code' => $company_code_old], '*', IGNORE_MISSING);
            if (!$record) {
                return ['status' => 'failed', 'message' => 'Company does not exist.'];
            }
            // kiểm tra company_code mới phải không trùng với các company_code đã tồn tại trong bảng
            $record2 = $DB->get_record('th_company', ['company_code' => $company_code], '*', IGNORE_MISSING);
            if ($record2 && $record2->company_code != $record->company_code) {
                return ['status' => 'failed', 'message' => 'Company code already exists.'];
            }
            // kiểm tra tên mới phải không trùng với tên khác trong bảng
            // $record3 = $DB->get_record('th_company', ['name' => $name], '*', IGNORE_MISSING);
            // if ($record3 && $record3->name != $record->name) {
            //     return ['status' => 'failed', 'message' => 'Company name already exists.'];
            // }

            // Lưu thông tin cũ để ghi log
            $params['name_old'] = $record->name;

            if($name != ''){
                $record->name = $name;
            }else{
                $params['name'] = $record->name;
            }
            
            if($company_code != ''){
                $record->company_code = $company_code;
            }else{
                $params['company_code'] = $record->company_code;
            }
            $DB->update_record('th_company', $record);

            // Ghi logs
            $event = \local_th_bundleassign_api\event\th_company_updated::create([
                'context' => \context_system::instance(),
                'other' => $params,
            ]);
            $event->trigger();

            return ['status' => 'success', 'message' => 'Company updated successfully.'];
        }

        if ($method === 'DELETE') {
            $delete_code = $params['company_code'];

            if (empty($delete_code)) {
                return ['status' => 'failed', 'message' => 'Company code is required.'];
            }

            try {
                $record = $DB->get_record('th_company', ['company_code' => $delete_code], 'id,name', IGNORE_MISSING);
                if (!$record) {
                    return ['status' => 'failed', 'message' => 'Company does not exist.'];
                }
                // Lưu thông tin cũ để ghi log
                $params['name'] = $record->name;
                $DB->delete_records('th_company', ['id' => (int)$record->id]);

                // Ghi logs
                $event = \local_th_bundleassign_api\event\th_company_deleted::create([
                    'context' => \context_system::instance(),
                    'other' => $params,
                ]);
                $event->trigger();

                return ['status' => 'success', 'message' => 'Company deleted successfully.'];
            } catch (Exception $e) {
                return ['status' => 'failed', 'message' => 'Failed to delete company: ' . $e->getMessage()];
            }
        }

        return ['status' => 'failed', 'message' => 'Method not supported. Use POST, PUT or DELETE.'];
    }

    public static function company_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_TEXT, 'Result status'),
                'message' => new external_value(PARAM_TEXT, 'Result message'),
            ]
        );
    }

     public static function brand_parameters() {
        return new external_function_parameters(
            [
                'id' => new external_value(PARAM_INT, 'user id', VALUE_DEFAULT, 0),
            ]
        );
    }

    public static function brand($id = 0) {
        global $DB, $CFG;
        
        $result = array(
            'status' => '',
            'company' => [
                'name'  => '',
                'code'  => '',
                'url'  => '',
                'logo'  => '',
                'color' => '',
                'primary_shade' => ''
            ]
        );

        if($id === 0) {
            $result['status'] = 'userid is required';
            return $result;
        }

        $sql = "SELECT * FROM {company_users} WHERE userid = :userid";
        $usercompany = $DB->get_record_sql($sql, ['userid' => $id]);
        
        if(!$usercompany) {
            $result['status'] = 'The user does not belong to any company';
            return $result;
        }
        
        $companyid = $usercompany->companyid;
                // Get company info
        $companyrecord = $DB->get_record('company', ['id' => $companyid], 'id, name, shortname');
        if ($companyrecord) {
            $result['company']['name'] = $companyrecord->name;
            $result['company']['code'] = $companyrecord->shortname;
        }
        $company = new company($companyid);
        $hostname = $company->get_wwwroot();
        $result['company']['url'] = $hostname ? $hostname : '';
        // Get theme colors config
        $th_config = get_config('theme_th_lambda_st');
        $syscontext = context_system::instance();
        $themerev = theme_get_revision();
        
        // Get company logos from system context (core_admin, filearea = logo{companyid})
        $logocompact = get_config('core_admin', 'logocompact' . $companyid);
        if (!empty($logocompact)) {
            $logourl = moodle_url::make_webservice_pluginfile_url(
                $syscontext->id,
                'core_admin',
                'logocompact' . $companyid,
                '0x0/',
                $themerev,
                $logocompact
            );

            $wstoken = optional_param('wstoken', '', PARAM_ALPHANUM);
            if (empty($wstoken)) {
                $wstoken = optional_param('token', '', PARAM_ALPHANUM);
            }

            if (!empty($wstoken)) {
                $logourl->param('token', $wstoken);
            }

            $result['company']['logo'] = $logourl->out(false);
        }
        
        $config_key = 'maincolor' . $companyid;
        if (!empty($th_config->$config_key)) {
            $result['company']['color'] = $th_config->$config_key;
        }

        $config_key = 'text_mobile_color' . $companyid;
        if (!empty($th_config->$config_key)) {
            $result['company']['primary_shade'] = $th_config->$config_key;
        }

        $result['status'] = 'success!';
        return $result;
    }

    public static function brand_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Result status'),
            'company' => new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Company name'),
                'code' => new external_value(PARAM_TEXT, 'Company code'),
                'url' => new external_value(PARAM_TEXT, 'Company hostname'),
                'logo' => new external_value(PARAM_TEXT, 'Logo URL'),
                'color' => new external_value(PARAM_TEXT, 'Main theme color'),
                'primary_shade' => new external_value(PARAM_TEXT, 'Text color for mobile applications'),
            ])
        ]);
    }
}

<?php
require '../../config.php';
require_once $CFG->dirroot . '/blocks/th_curriculum_completion_report/data_filter_form.php';
require_once $CFG->dirroot . '/blocks/th_curriculum_completion_report/lib.php';

global $CFG, $DB, $COURSE;

$course = $DB->get_record('course', array('id' => $COURSE->id));
if (!$course) {
    new \moodle_exception('invalidcourse');
}

require_login($COURSE->id);
require_capability('block/th_curriculum_completion_report:view', context_course::instance($COURSE->id));

$baseurl = new moodle_url('/blocks/th_curriculum_completion_report/view.php');
$pluginname = get_string('pluginname', 'block_th_curriculum_completion_report');
$heading = get_string('heading', 'block_th_curriculum_completion_report');

$PAGE->set_url($baseurl);
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);
$PAGE->navbar->add($heading, $baseurl);
$lang = current_language();
$PAGE->requires->js_call_amd('block_th_curriculum_completion_report/main', 'init', array('.table', "Báo cáo tiến độ hoàn thành chương trình học", $lang));
$companyid = 0;
if (class_exists('iomad')) {

	// Set the companyid
	$companyid = iomad::get_my_companyid($context);
	// Kiểm tra cuối cùng
	if (empty($companyid)) {
	    print_error('nocompany', 'block_th_curriculum_completion_report');
	}
}
$mform = new data_filter_form(null, ['companyid' => $companyid]);

if ($mform->is_cancelled()) {
    redirect($baseurl);
} else if ($mform->is_submitted() && $mform->is_validated()) {
    $data = $mform->get_data();

    $data->student_class = array_filter($data->student_class, 'strlen');
    $data->student_cohort = array_filter($data->student_cohort, 'strlen');

    // form data
    if (!empty($data->userid)) {
        list($sql, $params) = $DB->get_in_or_equal($data->userid, SQL_PARAMS_NAMED);

        $users = $DB->get_records_sql("SELECT * FROM {user} WHERE id $sql AND deleted = 0 AND suspended = 0", $params);
    } else {
        $users = $DB->get_records_sql("SELECT DISTINCT u.*
                            FROM {user} u
                            JOIN {role_assignments} ra ON u.id = ra.userid
                            JOIN {role} r ON ra.roleid = r.id
                            JOIN {company_users} cu ON u.id = cu.userid
                            WHERE r.shortname = 'student'
                            AND cu.companyid = ?
                            ORDER BY u.lastname, u.firstname;", [$companyid]);
    }
    $config = get_config('block_th_curriculum_completion_report');
    // Custom field name
    $th_student_cohort = $config->th_student_cohort;
    $th_student_cohort_field = 'profile_field_' . $th_student_cohort;

    $th_crm_code = $config->th_crm_code;
    $th_crm_code_field = 'profile_field_' . $th_crm_code;

    $th_student_class = $config->th_student_class;
    $th_student_class_field = 'profile_field_' . $th_student_class;

    $th_dob = $config->th_dob;
    $th_dob_field = 'profile_field_' . $th_dob;
    // idnumber 
    $ktck_shortname = $config->config_ktck;

    $final_point = $config->config_point;
    // Get all courses
    $courses = $DB->get_records_sql("SELECT DISTINCT c.*
                                FROM {course} c
                                JOIN {th_curriculum_courses} btc ON btc.courseid = c.id
                                JOIN {company_course} cc ON cc.courseid = c.id
                                WHERE c.visible = 1
                                AND cc.companyid = :companyid
                                ORDER BY c.shortname
                                ", ['companyid' => $companyid]);

    $course_fullnames = array_map(function ($course) {
        return get_string('course', 'block_th_curriculum_completion_report') . ' ' . $course->shortname;
    }, $courses);

    $stt = 1;
    //Create table
    $table = new html_table();
    $table->attributes = array('class' => 'table', 'border' => '1');
    $table->head = array_merge(array(
        'STT',
        get_string('fullname', 'block_th_curriculum_completion_report'),
        get_string('crm', 'block_th_curriculum_completion_report'),
        get_string('dateofbirth', 'block_th_curriculum_completion_report'),
        get_string('student_cohort', 'block_th_curriculum_completion_report'),
        get_string('student_class', 'block_th_curriculum_completion_report'),
        get_string('percentcompleted', 'block_th_curriculum_completion_report'),
        get_string('total_passed_quiz', 'block_th_curriculum_completion_report')
    ), $course_fullnames);
    //END

    $course_c = ['C4.05', 'C4.06', 'C4.07', 'C4.08'];

    foreach ($users as $user) {
        $total_course_enrol = 0;
        $course_finish = 0;
        profile_load_data($user);

        if (!empty($data->student_cohort) && !match_custom_field($user, $th_student_cohort_field, $data->student_cohort)) {
            continue;
        }
        if (!empty($data->student_class) && !match_custom_field($user, $th_student_class_field, $data->student_class)) {
            continue;
        }

        $course_rows = [];
        $total_passed_all = 0;
        $total_quiz_all = 0;
        foreach ($courses as $course) {
            if (is_enrolled(\context_course::instance($course->id), $user->id)) {
                if (in_array($course->shortname, $course_c)) {
                    $count_sql = "SELECT COUNT(qa.id) FROM {quiz_attempts} qa
                                JOIN {quiz} q ON q.id = qa.quiz AND q.course = :courseid
                                WHERE userid = :userid";
                    $params_quiz_attempt = [
                        'courseid' => $course->id,
                        'userid' => $user->id
                    ];

                    $count = $DB->get_field_sql($count_sql, $params_quiz_attempt);
                    $course_rows[] = $count ?? 0;
                } else {
                    $total_course_enrol++;
                    $progress = get_course_quiz_progress($user->id, $course->id, explode(',', $ktck_shortname), (float)$final_point);

                    $total_passed_all += $progress['pass_quizzes'];
                    $total_quiz_all += $progress['total_quizzes'];

                    if ($progress['course_completed']) {
                        $course_finish++;
                        $course_rows[] = 'Đã hoàn thành';
                    } else {
                        $course_rows[] = sprintf('%d / %d', $progress['pass_quizzes'], $progress['total_quizzes']);
                    }
                }
            } else {
                $course_rows[] = '';
            }
        }

        $row = array_merge([
            $stt,
            $user->firstname .' ' . $user->lastname,
            $user->$th_crm_code_field,
            $user->$th_dob_field,
            $user->$th_student_cohort_field,
            $user->$th_student_class_field,
            $total_course_enrol > 0 ? round(($course_finish / $total_course_enrol) * 100, 2). '%' : '0%',
            $total_passed_all .' / '.$total_quiz_all
        ], $course_rows);

        $table->data[] = $row;
        $stt++;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

// Link to manage courses
$manage_url = new moodle_url('/blocks/th_curriculum_completion_report/manage_courses.php');
echo html_writer::tag('p', 
    get_string('selectcourses', 'block_th_curriculum_completion_report') . ' ' . 
    html_writer::link($manage_url, get_string('here', 'block_th_curriculum_completion_report'))
);

echo $mform->display();

if ($mform->is_submitted() && $mform->is_validated() && !empty($table) && !empty($table->data)) {
    echo "<div class = 'th_curriculum_completion_report'>";
    echo html_writer::table($table);
    echo "</div>";
}

if ($mform->is_submitted() && (!isset($table) || empty($table->data))) {
    echo $OUTPUT->notification(get_string('noresultsfound', 'block_th_curriculum_completion_report'), 'notifymessage');
}
echo $OUTPUT->footer();

<?php
require('../../config.php');

require_login();
require_capability('block/th_curriculum_completion_report:view', context_system::instance());
$context = context_system::instance();
$companyid = 0;
if (class_exists('iomad')) {

	// Set the companyid
	$companyid = iomad::get_my_companyid($context);
	// Kiểm tra cuối cùng
	if (empty($companyid)) {
	    print_error('nocompany', 'block_th_curriculum_completion_report');
	}
}
$baseurl = new moodle_url('/blocks/th_curriculum_completion_report/manage_courses.php');
$pluginname = get_string('pluginname', 'block_th_curriculum_completion_report');

$managename = get_string('managecourses', 'block_th_curriculum_completion_report');

$PAGE->set_url($baseurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title($managename);
$PAGE->set_heading($managename);
$PAGE->navbar->add($pluginname);
$PAGE->navbar->add($managename, $baseurl);

// Handle add course.
if ($courseid = optional_param('addcourseid', 0, PARAM_INT)) {
    // Kiểm tra course có thuộc company không
    $course_in_company = $DB->record_exists('company_course', [
        'courseid' => $courseid,
        'companyid' => $companyid
    ]);
    
    if ($DB->record_exists('course', ['id' => $courseid]) &&
        $course_in_company &&
        !$DB->record_exists('th_curriculum_courses', ['courseid' => $courseid])) {
        $DB->insert_record('th_curriculum_courses', [
            'courseid' => $courseid,
            'timemodified' => time(),
        ]);
        redirect($PAGE->url);
    }
}

// Handle delete.
if ($delid = optional_param('del', 0, PARAM_INT)) {
    $DB->delete_records('th_curriculum_courses', ['id' => $delid]);
    redirect($PAGE->url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($managename);

// Link back to view report
$view_url = new moodle_url('/blocks/th_curriculum_completion_report/view.php');
echo html_writer::tag('p', 
    html_writer::link($view_url, get_string('backtoreport', 'block_th_curriculum_completion_report'), ['class' => 'btn btn-secondary'])
);

// Show add course form - chỉ hiển thị courses thuộc company
$allcourses = $DB->get_records_sql(
    "SELECT c.id, c.shortname
     FROM {course} c
     JOIN {company_course} cc ON cc.courseid = c.id
     WHERE c.visible = 1
     AND cc.companyid = :companyid
     ORDER BY c.shortname",
    ['companyid' => $companyid]
);

$courses_menu = [];
foreach ($allcourses as $course) {
    $courses_menu[$course->id] = $course->shortname;
}

$select = new single_select($PAGE->url, 'addcourseid', $courses_menu);
$select->label = get_string('selectcourse', 'block_th_curriculum_completion_report');
echo $OUTPUT->render($select);

// Show course list - chỉ hiển thị courses thuộc company
$courses = $DB->get_records_sql("
    SELECT b.id, c.shortname, c.fullname
    FROM {th_curriculum_courses} b
    JOIN {course} c ON c.id = b.courseid
    JOIN {company_course} cc ON cc.courseid = c.id
    WHERE cc.companyid = :companyid
    ORDER BY c.shortname
", ['companyid' => $companyid]);

if ($courses) {
    $table = new html_table();
    $table->head = [get_string('shortname'), get_string('fullname'), get_string('actions')];
    foreach ($courses as $c) {
        $deletebtn = html_writer::tag('button', get_string('delete'), [
            'type' => 'button',
            'class' => 'btn btn-danger btn-sm confirm-delete-course',
            'data-courseid' => $c->id
        ]);
        $table->data[] = [$c->shortname, $c->fullname, $deletebtn];
    }
    echo html_writer::table($table);
}

$PAGE->requires->js_amd_inline("
    require(['core/notification'], function(Notification) {
        document.querySelectorAll('.confirm-delete-course').forEach(function(button) {
            button.addEventListener('click', function() {
                const courseid = button.dataset.courseid;
                Notification.confirm(
                    '" . get_string('confirmdelete', 'block_th_curriculum_completion_report') . "',
                    '" . get_string('confirmdeletecontent', 'block_th_curriculum_completion_report') . "',
                    '" . get_string('yes') . "',
                    '" . get_string('no') . "',
                    function() {
                        window.location.href = '" . $PAGE->url->out(false) . "?del=' + courseid;
                    }
                );
            });
        });
    });
");

echo $OUTPUT->footer();

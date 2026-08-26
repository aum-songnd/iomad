<?php
require_once($CFG->dirroot . '/mod/quiz/lib.php');

/**
 * Return quiz progress info for a user in a course.
 * - Only includes quizzes with activity completion enabled **and**
 *   selected in course completion condition.
 *
 * @param int $userid
 * @param int $courseid
 * @param array $final_quiz_idnumbers List of course_module idnumbers considered as final exam
 * @param float $pass_point The minimum grade required to consider a quiz as passed
 * @return array [
 *     'pass_quizzes' => int,
 *     'total_quizzes' => int,
 *     'course_completed' => bool
 * ]
 */
function get_course_quiz_progress($userid, $courseid, array $final_quiz_idnumbers = ['kt', 'ess'], float $pass_point = 5.0): array {
    global $DB;

    // Get quiz module ID
    $moduleid = $DB->get_field('modules', 'id', ['name' => 'quiz']);
    if (!$moduleid) {
        throw new moodle_exception('Quiz module not found');
    }

    // Get all quizzes in the course with completion enabled
    $sql = "
        SELECT q.*, cm.id AS cmid, cm.idnumber AS cmidnumber
        FROM {quiz} q
        JOIN {course_modules} cm ON cm.instance = q.id
        JOIN {course_completion_criteria} ccc ON ccc.module = 'quiz'
            AND ccc.moduleinstance = cm.id
            AND ccc.criteriatype = 4
            AND ccc.course = :course
        WHERE cm.module = :moduleid
          AND cm.course = :courseid
          AND cm.completion > 0
    ";

    $params = [
        'course' => $courseid,
        'moduleid' => $moduleid,
        'courseid' => $courseid,
    ];

    $quizzes = $DB->get_records_sql($sql, $params);

    $pass_quizzes = 0;
    $total_quizzes = count($quizzes);

    $practice_quizzes = [];
    $final_quizzes = [];

    foreach ($quizzes as $quiz) {
        $cmidnumber = trim($quiz->cmidnumber ?? '');
        if (in_array($cmidnumber, $final_quiz_idnumbers)) {
            $final_quizzes[] = $quiz;
        } else {
            $practice_quizzes[] = $quiz;
        }
    }

    // Check: All practice quizzes passed
    $all_practice_passed = count($practice_quizzes) > 0;
    foreach ($practice_quizzes as $quiz) {
        $grades = quiz_get_user_grades($quiz, $userid);
        if (empty($grades) || $grades[$userid]->rawgrade <= $pass_point) {
            $all_practice_passed = false;
        } else {
            $pass_quizzes ++;
        }
    }

    // Check: All final quizzes passed
    $all_final_passed = count($final_quizzes) > 0;
    foreach ($final_quizzes as $quiz) {
        $grades = quiz_get_user_grades($quiz, $userid);
        if (empty($grades) || $grades[$userid]->rawgrade <= $pass_point) {
            $all_final_passed = false;
        }else {
            $pass_quizzes ++;
        }
    }

    return [
        'pass_quizzes' => $pass_quizzes,
        'total_quizzes' => $total_quizzes,
        'course_completed' => $all_practice_passed || $all_final_passed
    ];
}

function match_custom_field($user, $fieldname, $accepted_values) {
    if (!isset($user->$fieldname)) {
        return false;
    }
    
    foreach ($accepted_values as $val) {
        if (trim($user->$fieldname) === trim($val)) {
            return true;
        }
    }
    return false;
}
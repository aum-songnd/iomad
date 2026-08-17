<?php
namespace local_th_customstappapi\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Achievement Helper Class
 * Các hàm hỗ trợ để lấy thông tin thành tích, điểm số và dữ liệu học của học viên.
 */
class achievement_helper {

    /**
     * Lấy tổng số section và số section hoàn thành (dựa trên quiz completion).
     */
    public static function get_completed_sections(int $userid, array $courseids = []): \stdClass {
        global $DB;

        if (empty($courseids)) {
            return (object)[
                'totalsections' => 0,
                'completedsections' => 0
            ];
        }

        $params = ['userid' => $userid];     

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $params += $inparams;

        $sql = "
            SELECT
                COUNT(DISTINCT cm.id) AS totalsections,
                SUM(CASE WHEN cmc.completionstate IN (1,2) THEN 1 ELSE 0 END) AS completedsections
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {course_modules} cm ON cm.course = e.courseid
            JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
            LEFT JOIN {course_modules_completion} cmc
                ON cmc.coursemoduleid = cm.id AND cmc.userid = ue.userid
            WHERE ue.userid = :userid
            AND ue.status = 0
            AND e.status = 0
            AND cm.deletioninprogress = 0
            AND cm.completion <> 0
            AND cm.course $insql
        ";

        return $DB->get_record_sql($sql, $params)
            ?: (object)['totalsections' => 0, 'completedsections' => 0];
    }


    /**
     * Tính điểm trung bình bài luyện tập (quiz) từ bảng quiz_grades, chuẩn hoá thang 10, làm tròn 1 chữ số.
     * Chỉ tính các quiz có grade.
     */
    protected static function get_practice_avg_grade(int $userid, array $courseids = []): ?float {
        global $DB;

        if (empty($courseids)) {
            return 0;
        }

        $params = ['userid' => $userid];
        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $params += $inparams;

        $sql = "
            SELECT
                COUNT(qg.id) AS gradedcount,
                ROUND(
                    AVG(
                        CASE
                            WHEN q.grade > 0 AND qg.grade IS NOT NULL
                            THEN (qg.grade / q.grade) * 10
                            ELSE NULL
                        END
                    ),
                    1
                ) AS avg10
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {course_modules} cm ON cm.course = e.courseid
            JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
            JOIN {quiz} q ON q.id = cm.instance
            JOIN {quiz_grades} qg ON qg.quiz = q.id AND qg.userid = ue.userid
            WHERE ue.userid = :userid
            AND ue.status = 0
            AND e.status = 0
            AND cm.deletioninprogress = 0
            AND cm.completion <> 0
            AND qg.grade IS NOT NULL
            AND cm.course $insql
        ";

        $row = $DB->get_record_sql($sql, $params);

        return $row && (int)$row->gradedcount > 0 ? (float)$row->avg10 : 0;
    }

    protected static function get_skills_grade(int $userid, int $entrance_cid): array {
        global $DB;

        $exams = $DB->get_records_sql(
            "SELECT 
                qg.id AS gradeid,
                ROUND(qg.grade, 2) AS sumgrades,
                u.id AS userid,
                cm.id AS cmid,
                cm.idnumber
            FROM {quiz_grades} qg
                JOIN {user} u ON u.id = qg.userid
                JOIN {quiz} q ON q.id = qg.quiz
                JOIN {modules} m ON m.name = 'quiz'
                JOIN {course_modules} cm ON cm.instance = q.id AND cm.module = m.id
            WHERE qg.userid = :userid
                AND q.course = :courseid
                AND (
                    cm.idnumber LIKE '%reading%'
                    OR cm.idnumber LIKE '%listening%'
                    OR cm.idnumber LIKE '%ess%'
                    OR cm.idnumber LIKE '%rec%'
                )
        ", [
            'userid' => $userid,
            'courseid' => $entrance_cid,
        ]);

        $speaking = null;
        $writing = null;
        $reading = null;
        $listening = null;

        if (!empty($exams)) {
            foreach ($exams as $exam) {
                if (stripos($exam->idnumber, 'reading') !== false && is_numeric($exam->sumgrades)) {
                    $reading = $exam->sumgrades;
                } elseif (stripos($exam->idnumber, 'listening') !== false && is_numeric($exam->sumgrades)) {
                    $listening = $exam->sumgrades;
                } elseif (stripos($exam->idnumber, 'ess') !== false && is_numeric($exam->sumgrades)) {
                    $writing = $exam->sumgrades;
                } elseif (stripos($exam->idnumber, 'rec') !== false && is_numeric($exam->sumgrades)) {
                    $speaking = $exam->sumgrades;
                }
            }
        }

        $idnumber = get_config('local_th_customstappapi','config_averagescore');
        if(!$idnumber) {
            $idnumber = 'trinhdotrungbinh';
        }

        $cefr_sql = "
            SELECT gg.finalgrade
            FROM {grade_grades} gg
            JOIN {grade_items} gi ON gi.id = gg.itemid
            WHERE gg.userid = :userid
                AND gi.courseid = :courseid
                AND gi.idnumber LIKE :idnumber
                AND gg.finalgrade IS NOT NULL
            LIMIT 1
        ";

        $cefr_score = $DB->get_record_sql($cefr_sql, [
            'userid' => $userid,
            'courseid' => $entrance_cid,
            'idnumber' => '%' . $idnumber . '%'
        ]) ?: '';

        return [
            'overall' => $cefr_score ? round($cefr_score->finalgrade, 2) : 0,
            'listening' => $listening ? round($listening, 2) : 0,
            'speaking' => $speaking ? round($speaking, 2) : 0,
            'reading' => $reading ? round($reading, 2) : 0,
            'writing' => $writing ? round($writing, 2) : 0,
        ];
    }

    /**
     * Trả về điểm trung bình chung và các điểm cho từng kỹ năng.
     */
    public static function get_avg_grade(int $userid, array $courseids = [], int $entrance_cid): array {
        $practice = self::get_practice_avg_grade($userid, $courseids);

        if(!$entrance_cid) {
            return [
                'practice' => ['value' => $practice],
                'skills' => [
                    'overall' => null,
                    'listening' => null,
                    'speaking' => null,
                    'reading' => null,
                    'writing' => null,
                ],
            ];
        }

        $skills = self::get_skills_grade($userid, $entrance_cid);

        return [
            'practice' => ['value' => $practice],
            'skills' => $skills,
        ];
    }

    /**
     * Trả về số phút học trong 7 ngày gần nhất.
     */
    public static function get_study_minutes_last7days(int $userid, int $idlelimit = 1800, array $courseids = []): array {
        global $DB;

        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $tzname = \core_date::get_user_timezone($user);
        $tz = new \DateTimeZone($tzname ?: 'UTC');

        $today = new \DateTime('now', $tz);
        $start = (clone $today)->modify('-6 days')->setTime(0, 0, 0);
        $end   = (clone $today)->modify('+1 day')->setTime(0, 0, 0);

        $startts = $start->getTimestamp();
        $endts   = $end->getTimestamp();

        $logsbyday = [];
        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $d = (clone $start)->modify("+{$i} days")->format('Y-m-d');
            $dates[] = $d;
            $logsbyday[$d] = [];
        }

        $coursefilter = '';
        $params = [
            'userid' => $userid,
            'startts' => $startts,
            'endts' => $endts,
        ];

        if (!empty($courseids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
            $coursefilter = " AND courseid $insql ";
            $params += $inparams;
        }

        $sql = "
            SELECT timecreated
            FROM {logstore_standard_log}
            WHERE userid = :userid AND anonymous = 0
                AND (realuserid = userid OR realuserid IS NULL)
                AND courseid <> 0
                $coursefilter
                AND timecreated >= :startts
                AND timecreated < :endts
            ORDER BY timecreated ASC
        ";

        $rs = $DB->get_recordset_sql($sql, $params);

        foreach ($rs as $row) {
            $t = (int)$row->timecreated;
            $day = (new \DateTime('@' . $t))->setTimezone($tz)->format('Y-m-d');
            if (isset($logsbyday[$day])) {
                $logsbyday[$day][] = (object)['timecreated' => $t];
            }
        }
        $rs->close();

        // Tính phút theo từng ngày (dùng đúng hàm bạn đưa).
        $daily = [];
        $totalminutes = 0;

        foreach ($dates as $d) {
            $seconds = self::calculate_total_study_time($logsbyday[$d], $idlelimit);

            $minutes = (int)round($seconds / 60);

            $daily[] = [
                'date' => $d,
                'minutes' => $minutes,
            ];
            $totalminutes += $minutes;
        }

        return [
            'total' => $totalminutes,
            'avg_per_day' => (int)round($totalminutes / 7),
            'daily' => $daily,
        ];
    }

    protected static function calculate_total_study_time($logs, $idlelimit) {
        $prev = null;
        $sessionstart = null;
        $total = 0;

        foreach ($logs as $log) {
            $time = (int)$log->timecreated;

            if ($prev === null) {
                $sessionstart = $time;
            } elseif ($time - $prev > $idlelimit) {
                $total += $prev - $sessionstart;
                $sessionstart = $time;
            }

            $prev = $time;
        }

        if ($sessionstart !== null && $prev !== null) {
            $total += $prev - $sessionstart;
        }

        return $total;
    }
}
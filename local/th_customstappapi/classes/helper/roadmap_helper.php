<?php
namespace local_th_customstappapi\helper;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/entry_test_helper.php');

use local_th_customstappapi\helper\entry_test_helper;
use moodle_exception;

class roadmap_helper {
    public const ENTRY_ONLY      = 1;
    public const ENTRY_AND_TRIAL = 2;

    /**
     * Get ordered course IDs from user's roadmap definition.
     *
     * @param int $userid Moodle user ID
     * @return int[] Ordered list of course IDs
     * @throws moodle_exception
     */
    public static function get_user_roadmap_courseids(int $userid): array {
        global $DB;

        // Create cache instance per user
        // $cache = \cache::make('local_th_customstappapi', 'user_roadmap_courseids');

        // 1. Cache hit
        // $cached = $cache->get($userid);
        // if ($cached !== false) {
        //     return $cached;
        // }

        // 2. Validate user existence
        if (!$DB->record_exists('user', ['id' => $userid])) {
            throw new moodle_exception(
                'invaliduser',
                'error',
                '',
                null,
                'User does not exist.'
            );
        }

        // 3. Load roadmap JSON records
        $records = $DB->get_records_sql(
            "SELECT all_remote_course
               FROM {th_bundleassign_api}
              WHERE userid = :userid
                AND status = 1",
            ['userid' => $userid]
        );

        if (empty($records)) {
            // throw new moodle_exception(
            //     'noroadmapdata',
            //     'local_th_customstappapi',
            //     '',
            //     null,
            //     'No active roadmap data found for this user.'
            // );
            return [];
        }

        // 4. Parse and normalize roadmap entries (flat structure)
        $entries = [];
        $order = 0;

        foreach ($records as $record) {
            if (empty($record->all_remote_course)) {
                continue;
            }

            $data = json_decode($record->all_remote_course, true);
            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $entry) {
                if (empty($entry['course'])) {
                    continue;
                }

                $entries[] = [
                    'shortname' => $entry['course'],
                    'stage'     => isset($entry['sequence_stage'])
                        ? (int)$entry['sequence_stage']
                        : PHP_INT_MAX,
                    'slot'      => isset($entry['sequence_slot'])
                        ? (int)$entry['sequence_slot']
                        : PHP_INT_MAX,
                    'order'     => $order++, // Fallback ordering when stage/slot are equal
                ];
            }
        }

        if (empty($entries)) {
            // throw new moodle_exception(
            //     'invalidroadmapcontent',
            //     'local_th_customstappapi',
            //     '',
            //     null,
            //     'Roadmap data exists but contains no valid course entries.'
            // );
            return [];
        }

        // 5. Sort roadmap: stage → slot → insertion order
        usort($entries, function ($a, $b) {
            if ($a['stage'] !== $b['stage']) {
                return $a['stage'] <=> $b['stage'];
            }
            if ($a['slot'] !== $b['slot']) {
                return $a['slot'] <=> $b['slot'];
            }
            return $a['order'] <=> $b['order'];
        });

        // 6. Extract unique course shortnames while preserving order
        $shortnames = [];
        foreach ($entries as $entry) {
            if (!in_array($entry['shortname'], $shortnames, true)) {
                $shortnames[] = $entry['shortname'];
            }
        }

        if (empty($shortnames)) {
            // throw new moodle_exception(
            //     'noroadmapcourses',
            //     'local_th_customstappapi',
            //     '',
            //     null,
            //     'No valid course shortnames found after roadmap normalization.'
            // );
            return [];
        }

        // 7. Map course shortnames to course IDs
        list($insql, $params) = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);

        $courses = $DB->get_records_sql(
            "SELECT id, shortname
               FROM {course}
              WHERE shortname $insql",
            $params
        );

        if (empty($courses)) {
            // throw new moodle_exception(
            //     'coursenotfound',
            //     'local_th_customstappapi',
            //     '',
            //     null,
            //     'No matching Moodle courses found for roadmap shortnames.'
            // );
            return [];
        }

        // Re-index courses by shortname
        $shortnameToId = [];
        foreach ($courses as $course) {
            $shortnameToId[$course->shortname] = (int)$course->id;
        }

        // 8. Build ordered course ID list
        $courseids = [];
        foreach ($shortnames as $shortname) {
            if (isset($shortnameToId[$shortname])) {
                $courseids[] = $shortnameToId[$shortname];
            }
        }

        if (empty($courseids)) {
            // throw new moodle_exception(
            //     'emptycourseidresult',
            //     'local_th_customstappapi',
            //     '',
            //     null,
            //     'Roadmap resolved but no valid course IDs could be produced.'
            // );
            return [];
        }

        // 9. Save to cache
        // $cache->set($userid, $courseids);

        return $courseids;
    }

    /**
     * Get enrolled entry test course of a user (student role).
     *
     * @param int $userid
     * @return array
     */
    public static function get_assessment_courseids(int $userid, int $scope = self::ENTRY_AND_TRIAL): array {
        global $DB;

        if ($userid <= 0) {
            return [];
        }

        switch ($scope) {
            case self::ENTRY_ONLY:
                $courseids = entry_test_helper::get_entry_test_courses();
                break;

            case self::ENTRY_AND_TRIAL:
            default:
                $courseids = array_values(array_unique(array_merge(
                    entry_test_helper::get_entry_test_courses(),
                    entry_test_helper::get_trial_test_courses()
                )));
                break;
        }

        if (empty($courseids)) {
            return [];
        }

        // Get student role id safely
        static $studentroleid = null;
        if ($studentroleid === null) {
            $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        }

        if (!$studentroleid) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $params += [
            'userid' => $userid,
            'contextcourse' => CONTEXT_COURSE,
            'studentrole' => $studentroleid,
        ];

        return $DB->get_fieldset_sql("
            SELECT c.id
            FROM {course} c
            JOIN {enrol} e ON e.courseid = c.id
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            JOIN {context} ctx
                 ON ctx.instanceid = c.id
                AND ctx.contextlevel = :contextcourse
            JOIN {role_assignments} ra
                 ON ra.contextid = ctx.id
                AND ra.userid = ue.userid
            WHERE c.id $insql
              AND c.visible = 1
              AND ue.userid = :userid
              AND ue.status = 0
              AND e.status = 0
              AND ra.roleid = :studentrole
            ORDER BY ue.timecreated DESC
        ", $params);
    }

}

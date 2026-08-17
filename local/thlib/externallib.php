<?php

require_once "$CFG->libdir/externallib.php";
require_once "lib.php";
require_once $CFG->dirroot . "/course/externallib.php";

class local_thlib_external extends external_api {

    public static function loadcourses_parameters() {
        return new external_function_parameters(
            array(
                'makhoaarr' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'string makhoa', VALUE_OPTIONAL), 'aray makhoa'
                ),
                'maloparr' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'string malop', VALUE_OPTIONAL), 'aray malop'
                ),
                'useridarr' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'int, the id of usser', VALUE_OPTIONAL), 'aray userid'
                ),
                'time_from' => new external_value(PARAM_INT, 'int, time from'),
                'time_to' => new external_value(PARAM_INT, 'int, time to'),
            )
        );
    }

    public static function loadcourses_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id of the course'),
                    'coursefullname' => new external_value(PARAM_RAW, 'course full name of the course'),
                )
            )
        );
    }

    public static function loadcourses($makhoaarr = null, $maloparr = null, $useridarr = null, $time_from = null, $time_to = null) {
        global $DB;

        if (empty($makhoaarr) && empty($maloparr) && empty($useridarr)) {
            $courses = $DB->get_records('course', array('visible' => '1'),
                '', 'id,fullname,shortname,idnumber,category');

            $courses_fullname = array();

            $keyfrontcourse = 1;
            foreach ($courses as $crsid => $value) {
                if ($value->category == 0) {
                    $keyfrontcourse = $crsid;
                    continue;
                }

                $n = $value->fullname;

                if (isset($value->shortname) && trim($value->shortname) !== '') {
                    $n .= ',' . $value->shortname;
                }

                if (isset($value->idnumber) && trim($value->idnumber) !== '') {
                    $n .= ',' . $value->idnumber;
                }

                $obj = new stdClass();
                $obj->id = $crsid;
                $obj->coursefullname = $n;

                $courses_fullname[$crsid] = $obj;
            }

            return $courses_fullname;
        }

        $userid_arr = get_user_filtered_from_arrayof_makhoa_malop($makhoaarr, $maloparr, $useridarr);

        $courses_fullname = [];

        $sql_time = '';
        if ($time_from && $time_to) {
            $sql_time = ' AND ((ue.timestart > :timefrom1 and ue.timestart!=0) OR (ue.timestart = 0 AND ue.timecreated > :timefrom2))
			 	 	        AND ((ue.timestart < :timeend1 and ue.timestart!=0) OR (ue.timestart = 0 AND ue.timecreated < :timeend2)) ';
        }

        if (sizeof($userid_arr)) {

            list($insql, $params) = $DB->get_in_or_equal($userid_arr, SQL_PARAMS_NAMED, 'ctx');

            $sql = "SELECT c.id as id, c.fullname as coursefullname
				from {course}  c, {user_enrolments} ue, {enrol}  e
				where e.id = ue.enrolid and e.courseid = c.id
				and ue.userid $insql
				$sql_time
				group by c.id";

            // $params = array_merge($params, array('timefrom1' => $time_from, 'timefrom2' => $time_from, 'timeend1' => $time_to, 'timeend2' => $time_to));
            $records = $DB->get_records_sql($sql, $params);
            // print_object($records);
            return $records;
        }

        return $courses_fullname;
    }

    public static function loadcourses2($makhoaarr = null, $maloparr = null, $useridarr = null, $time_from = null, $time_to = null) {
        global $DB;

        if (empty($makhoaarr) && empty($maloparr) && empty($useridarr)) {
            $courses = $DB->get_records('course', array('visible' => '1'),
                '', 'id,fullname,shortname,idnumber,category');

            $courses_fullname = array();

            $keyfrontcourse = 1;
            foreach ($courses as $crsid => $value) {
                if ($value->category == 0) {
                    $keyfrontcourse = $crsid;
                    continue;
                }

                $n = $value->fullname;

                if (isset($value->shortname) && trim($value->shortname) !== '') {
                    $n .= ',' . $value->shortname;
                }

                if (isset($value->idnumber) && trim($value->idnumber) !== '') {
                    $n .= ',' . $value->idnumber;
                }

                $obj = new stdClass();
                $obj->id = $crsid;
                $obj->coursefullname = $n;

                $courses_fullname[$crsid] = $obj;
            }

            return $courses_fullname;
        }

        $userid_arr = get_user_filtered_from_arrayof_makhoa_malop($makhoaarr, $maloparr, $useridarr);

        // print_object($userid_arr);

        $courses_fullname = [];

        $sql_time = '';
        if ($time_from && $time_to) {
            $sql_time = ' AND ((ue.timestart > :timefrom1 and ue.timestart!=0) OR (ue.timestart = 0 AND ue.timecreated > :timefrom2))
			 	 	        AND ((ue.timestart < :timeend1 and ue.timestart!=0) OR (ue.timestart = 0 AND ue.timecreated < :timeend2)) ';
        }

        if (sizeof($userid_arr)) {
            list($insql, $params) = $DB->get_in_or_equal($userid_arr, SQL_PARAMS_NAMED, 'ctx');
            $sql = "SELECT user_course.* ,{grade_grades}.finalgrade
				from(
					select row_number() OVER (Order by a.userid) as id, a.*
					from (
						select {user}.id as userid , course.fullname, course.shortname, course.idnumber, course.id as courseid
						from
							{user}
							left join
							{user_enrolments} ue
							on {user}.id = ue.userid
							$sql_time
							left join {enrol}
							on {enrol}.id = ue.enrolid
							left join {course} course
							on course.id = {enrol}.courseid and course.visible = 1
							where {user}.id $insql
						) a
						group by userid, courseid
					) user_course
				left join {grade_items}
				on {grade_items}.courseid = user_course.courseid and {grade_items}.itemtype='course'
				left join {grade_grades}
				on {grade_grades}.itemid = {grade_items}.id and {grade_grades}.userid = user_course.userid";

            $params = array_merge($params, array('timefrom1' => $time_from, 'timefrom2' => $time_from, 'timeend1' => $time_to, 'timeend2' => $time_to));
            $records = $DB->get_records_sql($sql, $params);

            $rows = [];

            foreach ($records as $key => $value) {
                $userid = $value->userid;
                $courseid = $value->courseid;
                $coursefullname = $value->fullname;
                $courseshortname = $value->shortname;
                $courseidnumber = $value->idnumber;
                $finalgrade = $value->finalgrade;

                if (!array_key_exists($userid, $rows)) {
                    $rows[$userid] = array();
                }

                if ($courseid) {
                    $rows[$userid][$courseid] = $finalgrade;
                    // $courses_fullname[$courseid] = $coursefullname;
                    $obj = new stdClass();
                    $obj->id = $courseid;
                    $text = $coursefullname;
                    if ($courseshortname != null && $courseshortname != '') {
                        $text .= ', ' . $courseshortname;
                    }
                    if ($courseidnumber != null && $courseidnumber != '') {
                        $text .= ', ' . $courseidnumber;
                    }
                    $obj->coursefullname = $text;
                    if (!array_key_exists($courseid, $courses_fullname)) {
                        $courses_fullname[$courseid] = $obj;
                    }

                }
            }
        }

        return $courses_fullname;
    }

    //remain functions
    public static function loadsettings_parameters() {
        return new external_function_parameters(
            array(
                'itemid' => new external_value(PARAM_INT, 'The item id to operate on'),
            )
        );
    }

    public static function loadsettings_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'content' => new external_value(PARAM_RAW, 'settings content text'),
                )
            )
        );
    }

    public static function loadsettings($itemid) {
        global $DB;
        //$params = self::validate_parameters(self::getExample_parameters(), array());
        $params = self::validate_parameters(self::loadsettings_parameters(),
            array('itemid' => $itemid));

        $sql = 'SELECT content FROM {testtest} WHERE id = ?';
        $paramsDB = $params; //array($itemid);
        $db_result = $DB->get_records_sql($sql, $paramsDB);

        return $db_result;
    }

    public static function updatesettings_parameters() {
        return new external_function_parameters(

            array(
                'itemid' => new external_value(PARAM_INT, 'The item id to operate on'),
                'data2update' => new external_value(PARAM_TEXT, 'Update data'))
        );
    }

    public static function updatesettings_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'content' => new external_value(PARAM_RAW, 'settings content text'),
                )
            )
        );
    }

    public static function updatesettings($itemid, $data2update) {
        global $DB;
        //$params = self::validate_parameters(self::getExample_parameters(), array());
        $params = self::validate_parameters(self::updatesettings_parameters(),
            array('itemid' => $itemid, 'data2update' => $data2update));

        $newdata = new stdClass();
        $newdata->id = $itemid;
        $newdata->content = $data2update;
        if ($DB->record_exists('testtest', array('id' => $itemid))) {
            $DB->update_record('testtest', $newdata);
        }

        $sql = 'SELECT content FROM {testtest} WHERE id = ?';
        $paramsDB = array($itemid);
        $db_result = $DB->get_records_sql($sql, $paramsDB);

        return $db_result;
    }

    public static function get_user_phone2_parameters() {
        return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_INT, 'id user'),
            )
        );
    }

    public static function get_user_phone2_returns() {
        return new external_single_structure(
            array(
                'userid' => new external_value(PARAM_INT, 'id user'),
                'phone2' => new external_value(PARAM_RAW, 'phone2 user'),
            )
        );
    }

    public static function get_user_phone2($userid) {
        global $DB;
        //$params = self::validate_parameters(self::getExample_parameters(), array());
        $params = self::validate_parameters(self::get_user_phone2_parameters(),
            array('userid' => $userid));

        if ($record = $DB->get_record('user', array('id' => $userid), 'phone2')) {
            $phone2 = $record->phone2;
        } else {
            return ['userid' => $userid, 'phone2' => ''];
        }
        $phone2 = trim($phone2);
        return ['userid' => $userid, 'phone2' => $phone2];
    }

        public static function loadmalop_parameters() {
        return new external_function_parameters(
            array(
                'search' => new external_value(PARAM_RAW, 'query'),
            )
        );
    }

    public static function loadmalop_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id of the course'),
                    'malop' => new external_value(PARAM_RAW, 'course full name of the course'),
                )
            )
        );
    }

    public static function loadmalop($search) {

        global $DB;

        $config = get_config('local_thlib');
            $sortorder = "lastname,firstname";
            if ($config->sortorder == 1) {
                $sortorder = "firstname,lastname";
            }

        $shortname = trim($config->classcodeshortname);

        $shortnamearr = explode(",", $shortname);
        if (count($shortnamearr) > 0) {
            list($insql, $inparams) = $DB->get_in_or_equal($shortnamearr);
        } else {
            list($insql, $inparams) = $DB->get_in_or_equal(['']);
        }

        $sql = "SELECT distinct {user_info_data}.id,{user_info_data}.data as malop
                    from {user}
                    inner join {user_info_data}
                    on {user_info_data}.userid = {user}.id
                    inner join {user_info_field}
                    on {user_info_data}.fieldid = {user_info_field}.id
                    where {user_info_field}.shortname $insql AND {user_info_data}.data like "."'%".$search."%'"."
                    and {user_info_data}.data <> ''
                    group by {user_info_data}.data";

        return $DB->get_records_sql($sql, $inparams);

    }


    /**
     * Returns description of method parameters value
     *
     * @return external_description
     */
    public static function loadusers_parameters() {
        return new external_function_parameters(
            array(
                'search' => new external_value(PARAM_RAW, 'query'),
                'companyid' => new external_value(PARAM_INT, 'company id', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Get potential users.
     *
     * @param int $courseid Course id
     * @param int $enrolid Enrolment id
     * @param string $search The query
     * @param boolean $searchanywhere Match anywhere in the string
     * @param int $page Page number
     * @param int $perpage Max per page
     * @return array An array of users
     */
    public static function loadusers($search, $companyid = 0) {
        global $DB, $CFG;

        require_once($CFG->dirroot.'/user/lib.php');

        // Add some additional sensible conditions
        $tests = array("u.id <> :guestid", 'u.deleted = 0', 'u.confirmed = 1');
        $params = array('guestid' => $CFG->siteguest);
        
        // Add company filter if companyid provided
        $companyjoin = '';
        if ($companyid > 0) {
            $companyjoin = 'JOIN {company_users} cu ON cu.userid = u.id';
            $tests[] = 'cu.companyid = :companyid';
            $params['companyid'] = $companyid;
        }
        
        if (!empty($search)) {
            $conditions = get_extra_user_fields(context_system::instance());
            foreach (get_all_user_name_fields() as $field) {
                $conditions[] = 'u.'.$field;
            }
            $conditions[] = $DB->sql_fullname('u.firstname', 'u.lastname');
            $searchparam = '%' . $search . '%';
            $i = 0;
            foreach ($conditions as $key => $condition) {
                $conditions[$key] = $DB->sql_like($condition, ":con{$i}00", false);
                $params["con{$i}00"] = $searchparam;
                $i++;
            }
            $tests[] = '(' . implode(' OR ', $conditions) . ')';
        }
        $wherecondition = implode(' AND ', $tests);

        $sql = "SELECT u.id,u.username,CONCAT(u.firstname,' ',u.lastname) as fullname,u.email
                FROM {user} u
                $companyjoin
                WHERE $wherecondition";

        $results = $DB->get_records_sql($sql, $params);

        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function loadusers_returns() {
        global $CFG;
        require_once($CFG->dirroot . '/user/externallib.php');

        $userfields = array(
            'id'    => new external_value(core_user::get_property_type('id'), 'ID of the user'),
            'username'    => new external_value(core_user::get_property_type('username'), 'The username', VALUE_OPTIONAL),
            'fullname'    => new external_value(core_user::get_property_type('firstname'), 'The fullname of the user'),
            'email'    => new external_value(core_user::get_property_type('email'), 'The fullname of the user'),
        );
        return new external_multiple_structure(new external_single_structure($userfields));
    }

    public static function getcourses_parameters() {
        return new external_function_parameters(
            array(
                'makhoaarr' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'string makhoa', VALUE_OPTIONAL), 'aray makhoa'
                ),
                'maloparr' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'string malop', VALUE_OPTIONAL), 'aray malop'
                ),
                'useridarr' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'int, the id of usser', VALUE_OPTIONAL), 'aray userid'
                ),
                'time_from' => new external_value(PARAM_INT, 'int, time from'),
                'time_to' => new external_value(PARAM_INT, 'int, time to'),
                'search' => new external_value(PARAM_RAW, 'query'),
                'companyid' => new external_value(PARAM_INT, 'company id', VALUE_DEFAULT, 0),
            )
        );
    }

    public static function getcourses_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id of the course'),
                    'coursefullname' => new external_value(PARAM_RAW, 'course full name of the course'),
                )
            )
        );
    }

    public static function getcourses($makhoaarr = null, $maloparr = null, $useridarr = null, $time_from = null, $time_to = null, $search, $companyid = 0) {
        global $DB;

        if (empty($makhoaarr) && empty($maloparr) && empty($useridarr)) {
            $params = [];
            if ($companyid > 0) {
                $params['companyid'] = $companyid;
                $params['search1'] = '%' . $search . '%';
                $params['search2'] = '%' . $search . '%';
                $sql = "SELECT c.id, c.fullname, c.shortname, c.idnumber, c.category 
                        FROM {course} c
                        JOIN {company_course} cc ON cc.courseid = c.id
                        WHERE c.visible = 1
                            AND cc.companyid = :companyid
                            AND (c.fullname LIKE :search1 OR c.shortname LIKE :search2)";
                $courses = $DB->get_records_sql($sql, $params);
            } else {
                $sql = "SELECT id,fullname,shortname,idnumber,category FROM {course}
                        WHERE visible = 1 AND (fullname like '%".$search."%' OR shortname like '%".$search."%')";
                $courses = $DB->get_records_sql($sql);
            }
            // $courses = $DB->get_records('course', array('visible' => '1'),
            //  '', 'id,fullname,shortname,idnumber,category');

            $courses_fullname = array();

            $keyfrontcourse = 1;
            foreach ($courses as $crsid => $value) {
                if ($value->category == 0) {
                    $keyfrontcourse = $crsid;
                    continue;
                }

                $n = $value->fullname;

                if (isset($value->shortname) && trim($value->shortname) !== '') {
                    $n .= ',' . $value->shortname;
                }

                if (isset($value->idnumber) && trim($value->idnumber) !== '') {
                    $n .= ',' . $value->idnumber;
                }

                $obj = new stdClass();
                $obj->id = $crsid;
                $obj->coursefullname = $n;

                $courses_fullname[$crsid] = $obj;
            }

            return $courses_fullname;
        }

        $userid_arr = get_user_filtered_from_arrayof_makhoa_malop($makhoaarr, $maloparr, $useridarr);

        $courses_fullname = [];

        $sql_time = '';
        if ($time_from && $time_to) {
            $sql_time = ' AND ((ue.timestart > :timefrom1 and ue.timestart!=0) OR (ue.timestart = 0 AND ue.timecreated > :timefrom2))
                            AND ((ue.timestart < :timeend1 and ue.timestart!=0) OR (ue.timestart = 0 AND ue.timecreated < :timeend2)) ';
        }

        if (sizeof($userid_arr)) {

            list($insql, $params) = $DB->get_in_or_equal($userid_arr, SQL_PARAMS_NAMED, 'ctx');

            $sql = "SELECT c.id, CONCAT(c.fullname,',',c.shortname,',',c.idnumber) as coursefullname
                from {course}  c, {user_enrolments} ue, {enrol}  e
                where e.id = ue.enrolid and e.courseid = c.id
                and ue.userid $insql
                $sql_time and (c.fullname like "."'%".$search."%'"." OR c.shortname like "."'%".$search."%')"."
                group by c.id";

            $records = $DB->get_records_sql($sql, $params);
            return $records;
        }

        return $courses_fullname;
    }

    // ///////////////

    // public static function load_registeredcourses_by_userid_parameters() {

    //     return new external_function_parameters(
    //         array(
    //             new external_value(PARAM_INT, 'The id of user to fetch registerd courses'),
    //         )
    //     );
    // }

    // public static function load_registeredcourses_by_userid_returns() {

    //     $returnstructure = course_summary_exporter::get_read_structure();
    //     $returnstructure->keys["activated_link"] = new external_value(PARAM_URL, 'link to active this course');
    //     return new external_single_structure(
    //         array(
    //             'courses' => new external_multiple_structure($returnstructure, 'Course'),
    //             'nextoffset' => new external_value(PARAM_INT, 'Offset for the next request'),
    //         )
    //     );
    // }

    // public static function load_registeredcourses_by_userid($userid = null) {

    //     global $CFG, $PAGE, $DB, $USER;
    //     require_once $CFG->dirroot . '/course/lib.php';
    //     require_once $CFG->dirroot . '/user/profile/lib.php';

    //     self::validate_context(context_user::instance($USER->id));

    //     if (!$userid || $userid == 0) {
    //         $userid = $USER->id;
    //     }

    //     $config = get_config('block_th_activatecourses');
    //     $registercourse_shortname = $config->regisredcourseshortname;

    //     if (empty(trim($registercourse_shortname))) {
    //         return [
    //             'courses' => [],
    //             'nextoffset' => 0,
    //         ];
    //     }

    //     $filteredcourses = "";

    //     $userfielddatas = profile_get_user_fields_with_data($userid);
    //     foreach ($userfielddatas as $fd) {
    //         if ($fd->field->shortname == $registercourse_shortname) {
    //             $filteredcourses = $fd->field->data;
    //             break;
    //         }
    //     }

    //     $courseidarr = explode(",", $filteredcourses);

    //     $filteredcourses = [];
    //     if (count($courseidarr) == 0) {

    //     } else {
    //         list($insql, $params) = $DB->get_in_or_equal($courseidarr);
    //         $sql = "SELECT * from {course} where id $insql";

    //         $records = $DB->get_records_sql($sql, $params);
    //         foreach ($records as $record) {

    //             $courseid = $record->id;
    //             $context = context_course::instance($courseid);
    //             if (!is_enrolled($context, $userid, '', true)) {
    //                 $filteredcourses[] = $record;
    //             }
    //         }
    //     }

    //     $offset = 0;
    //     $processedcount = count($filteredcourses);

    //     $renderer = $PAGE->get_renderer('core');

    //     $formattedcourses = array_map(function ($course) use ($renderer) {
    //         context_helper::preload_from_record($course);
    //         $context = context_course::instance($course->id);
    //         $exporter = new course_summary_exporter($course, ['context' => $context]);
    //         return $exporter->export($renderer);
    //     }, $filteredcourses);

    //     print_object($formattedcourses);

    //     foreach ($formattedcourses as $key => $value) {
    //         $formattedcourses[$key]->activated_link = $CFG->wwwroot . "/blocks/th_selfenrol_registeredcourses/activate.php?id=$value->id";
    //     }

    //     return [
    //         'courses' => $formattedcourses,
    //         'nextoffset' => $offset + $processedcount,
    //     ];
    // }

}

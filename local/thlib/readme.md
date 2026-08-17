# Tài liệu các thư viện dùng chung của tất cả các plugin

1. **Tên plugin:** thlib
2. **Kiểu plugin:** local
3. **Project:** TNU,VMC,AOF
4. **Chức năng chung:** các thư viện dùng chung của tất cả các plugin
5. **Người phát triển:** minhpl@aum.edu.vn
6. **Người yêu cầu:** minhpl@aum.edu.vn
7. **Tham chiếu ERP:** TASK
8. **Mã nguồn:** https://github.com/thsambala/th/tree/master/local/thlib
# 1. Yêu cầu và Đầu vào, đầu ra:
1. Yêu cầu
- Viết các thư viện dùng chung của tất cả các plugin

# 2. Mô tả chi tiết/ hướng dẫn sử dụng/ hướng dẫn cài đặt
## 1. Các capability cần để truy cập, sử dụng tính năng và đoạn code định nghĩa capabilities
- Các capability: **local/thlib:seeallthings**
- Đoạn code định nghĩa capabilities:
```php
    'local/thlib:seeallthings' => array(
		'riskbitmask' => RISK_SPAM | RISK_XSS,
		'captype' => 'write',
		'contextlevel' => CONTEXT_BLOCK,
		'archetypes' => array(
			'manager' => CAP_ALLOW,
		),
	),
```
## 2. Hướng dẫn sử dụng
- Truy cập **Dashboard > Site administration > Plugins > Local plugins > TH thlib**

![image](https://user-images.githubusercontent.com/66956549/230703509-53eed05d-6d8a-4087-91c8-b6e646616663.png)

# 3. Phân tích thiết kế (database, functions nếu cần)

## Database:

1. Các bảng cần dùng và các câu truy vấn
- Bảng cần dùng: user, course, enrol, cohort, user_enrolments, role_assignments, context, qaapairs, qaa, user_info_data, user_info_field
- Các câu truy vấn đã dùng: 

```sql
	SELECT {course}.id ,{course}.fullname , {cohort}.name, {cohort}.id as cohortid
        from {enrol}
        inner join {course}
        on {enrol}.courseid = {course}.id
        inner join {cohort}
        on {cohort}.id = {enrol}.customint1
        where {enrol}.enrol='cohort' and {cohort}.id= ?
        order by {course}.startdate, {course}.fullname
```

```sql
	SELECT u.*
	FROM {user} u
	JOIN (SELECT DISTINCT eu1_u.*
		FROM {user} eu1_u
		JOIN {user_enrolments} ej1_ue
		ON ej1_ue.userid = eu1_u.id
		JOIN {enrol} ej1_e
		ON (ej1_e.id = ej1_ue.enrolid AND ej1_e.courseid = :courseid)
		JOIN {role_assignments}
		ON ({role_assignments}.userid = eu1_u.id AND {role_assignments}.roleid = 5)
		JOIN {context}
		ON {context}.instanceid = ej1_e.courseid AND {context}.id = {role_assignments}.contextid
		WHERE eu1_u.deleted = 0
		AND ((ej1_ue.timestart > :timefrom1 and ej1_ue.timestart!=0) OR (ej1_ue.timestart = 0 AND ej1_ue.timecreated > :timefrom2))
		AND ((ej1_ue.timestart < :timeend1 and ej1_ue.timestart!=0) OR (ej1_ue.timestart = 0 AND ej1_ue.timecreated < :timeend2))
		AND ej1_e.enrol ='cohort' and ej1_e.customint1 = :cohortid
		) je
	ON je.id = u.id
	WHERE u.deleted = 0
```

```sql
	SELECT {qaapairs}.*
	from {qaapairs}
	join {qaa}
	on {qaa}.id = {qaapairs}.qaaid
	join {course}
	on {course}.id = {qaa}.course
	where {course}.id = :courseid
	AND timecreatedquestion >= :time_from AND timecreatedquestion <= :time_to
```

```sql
	SELECT  us.id as userid, us.firstname,us.lastname, infodata.data
	from {user} us
	inner join {user_info_data} infodata
	on us.id = infodata.userid and us.deleted = 0 $wheresql_userstatus
	inner join {user_info_field}
	on {user_info_field}.shortname $insql
	and infodata.fieldid = {user_info_field}.id
	and infodata.data = '$makhoa'
	group by userid
	order by $sortorder
```

```sql
	SELECT  us.id as userid, us.firstname,us.lastname, infodata.data
	from {user} us
	inner join {user_info_data} infodata
	on us.id = infodata.userid and us.deleted = 0 $wheresql_userstatus
	inner join {user_info_field}
	on {user_info_field}.shortname $insql
	and infodata.fieldid = {user_info_field}.id
	and infodata.data = '$malop'
	group by userid
	order by $sortorder
```

```sql
	SELECT  us.id as userid, us.firstname,us.lastname, infodata.data
	from {user} us
	inner join {user_info_data} infodata
	on us.id = infodata.userid $wheresql_userstatus and us.deleted = 0
	inner join {user_info_field}
	on {user_info_field}.shortname $insql_mk
	and infodata.fieldid = {user_info_field}.id
	and infodata.data $insql_makhoa
	group by userid
	order by $sortorder
```

```sql
	SELECT  us.id as userid, us.firstname,us.lastname, infodata.data
	from {user} us
	inner join {user_info_data} infodata
	on us.id = infodata.userid $wheresql_userstatus and us.deleted = 0
	inner join {user_info_field}
	on {user_info_field}.shortname $insql_ml
	and infodata.fieldid = {user_info_field}.id
	and infodata.data $insql_malop
	group by userid
	order by $sortorder
```

```sql
select id from {user} where id $insql_users and suspended=1
```

```sql
select id from {user} where id $insql_users and suspended=0
```

2. Thêm Bảng: Không

3. Database Diagram: Không

## Method
1. Các Method: filter_userarr_by_userstatus, local_thlib_secondsToTime, local_thlib_secondsToTime, generate_download_excel, my_get_enrolled_users, get_courses_enrolled_by_cohort, get_userid_fullname, get_userid_form, get_allcourseid_form, get_all_cohort, get_userid_filtered_by_makhoa, get_userid_filtered_by_malop, get_user_filtered_from_arrayof_makhoa_malop, get_left_rows, html2text, 
get_string_params
2. Chi tiết các Method:

```php
/**
 * get_string_params function sql param
 *
 * @return array
 */
public static function get_string_params()
```

```php
/**
 * html2text function html to text
 *
 * @param [string] $text	html
 * @return string
 */
public static function html2text($text)
```
```php
/**
 * Gets the left rows.
 *
 * @param      <type>  $userid_arr  The userid_arr
 * @param      <type>  $user_arr    The user_arr
 *
 * @return     <type>  array of left_html_rows and left_export_rows.
 */
function get_left_rows($userid_arr, $user_arr, $config = null)
```
```php
/**
 * get_user_filtered_from_arrayof_makhoa_malop function lấy các học viên theo mã khóa, theo mã lớp hoặc cả 2
 *
 * @param [array] $makhoaarr danh sách mã khóa của học viên
 * @param [array] $maloparr	danh sách mã lớp của học viên
 * @param [array] $useridarr_op	danh sách các học viên
 * @param [int] $user_status trạng thái hoạt động của tài khoản
 * @return array 	danh sách các id của học viên
 */
function get_user_filtered_from_arrayof_makhoa_malop($makhoaarr = null, $maloparr = null, $useridarr_op = null, $user_status = thlib::USER_STATUS_ALL)
```
```php
/**
 * get_userid_filtered_by_malop function lấy các học viên thoản mãn mã lớp
 *
 * @param [string] $malop	mã lớp của học viên
 * @param [int] $user_status	trạng thái hoạt động của tài khoản
 * @return void
 */
function get_userid_filtered_by_malop($malop = null, $user_status = thlib::USER_STATUS_ALL)
```
```php
/**
 * [get_userid_filtered_by_makhoa lấy các học viên thoả mãn mã khóa]
 * @param  [string] $makhoa      [Mã khóa học của học viên]
 * @param  [int] $user_status [Trạng thái hoạt động của tài khoản]
 * @return [array]              [list userid]
 */
function get_userid_filtered_by_makhoa($makhoa = null, $user_status = thlib::USER_STATUS_ALL)
```
```php
/**
 * [get_all_cohort get all cohort]
 * @param  string $field     [field table cohort (id,name)]
 * @param  string $sortorder [sắp xếp dữ liệu (sql order by)]
 * @return [array]            [list cohort]
 */
function get_all_cohort($field = 'id,name', $sortorder = 'name ASC') 
```
```php
/**
 * get_allcourseid_form function create form select course autocomplete
 *
 * @param [stdClass] $mform stdClass moodleform
 * @return array list course
 */
function get_allcourseid_form($mform)
```

```php
/**
 * get_userid_form function create form select autocomplete
 *
 * @param [stdClass] $mform moodleform
 * @param [string] $sortorder an order to sort the results in (optional, a valid SQL ORDER BY parameter)
 * @param boolean $required select required
 * @return void
 */
function get_userid_form($mform, $sortorder = null, $required = false)
```

```php
/**
 * get_userid_fullname function lấy họ và tên của người dùng
 *
 * @param [int] $userid id user
 * @return string
 */
function get_userid_fullname($userid)
```

```php
	/**
	 * filter_userarr_by_userstatus function lấy các user thỏa mãn yêu cầu
	 *
	 * @param [array] $userid_arr (mảng các userid)
	 * @param [string] $user_status mặc định là USER_STATUS_ALL(0), USER_STATUS_ACTIVE(1), USER_STATUS_SUPPENDED(2))
	 * @return array
	 */
	public static function filter_userarr_by_userstatus($userid_arr, $user_status = thlib::USER_STATUS_ALL) 
```

```php
/**
 * local_thlib_secondsToTime function chuyển số giây thành số ngày, giờ, phút, giây
 *
 * @param [int] $seconds
 * @return string
 */
function local_thlib_secondsToTime($seconds)
```

```php
/**
 * get_course_qaaps function lấy các câu hỏi Qaa trong khóa học theo khoảng thời gian
 *
 * @param [int] $courseid
 * @param [int] $time_from
 * @param [int] $time_to
 * @return void
 */
function get_course_qaaps($courseid, $time_from, $time_to) 
```

```php
/**
 * generate_download_excel function tạo tệp excel tải xuống
 *
 * @param [string] $downloadname tên tệp tải xuống
 * @param [array] $rows dữ liệu cần tạo tệp excel
 * @param [string] $sheetname tên sheet
 * @return void
 */
function generate_download_excel($downloadname, $rows, $sheetname)
```

```php
/**
 * my_get_enrolled_users function lấy các học viên trong khóa học theo 
 *
 * @param [int] $courseid id course
 * @param [int] $cohortid id cohort
 * @param [int] $timefrom time from
 * @param [int] $timeend time end
 * @param [string] $orderby an order to sort the results in (optional, a valid SQL ORDER BY parameter).
 * @param integer $limitfrom return a subset of records, starting at this point (optional, required if $limitnum is set).
 * @param integer $limitnum eturn a subset comprising this many records (optional, required if $limitfrom is set).
 * @return void
 */
function my_get_enrolled_users($courseid, $cohortid, $timefrom, $timeend, $orderby = null, $limitfrom = 0, $limitnum = 0)
```

```php
/**
 * get_courses_enrolled_by_cohort function lấy các khóa học sử dụng phương thức ghi danh cohort
 *
 * @param [int] $cohort_id cohort id
 * @return array
 */
function get_courses_enrolled_by_cohort($cohort_id)
```
# 4. mã nguồn (nếu cần hướng dẫn viết mã nguồn chi tiết, những thay đổi mã nguồn cần để viết tính năng này)

https://github.com/thsambala/th/tree/master/local/thlib

# 5. Triển khai (nếu cần)

# 6. Kiểm thử (nếu cần)
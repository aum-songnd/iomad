<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     local_thlib
 * @category    string
 * @copyright   2020 phamleminh1812@gmail.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'TH thlib';
$string['thlib:view'] = 'view permission';
$string['thlib:seeallthings'] = 'see all things permission';
$string['no_'] = 'No.';
// $string['viewlink'] = 'View user cohorts';
// $string['content'] = 'click the link below to view user cohorts';
// $string['editpage'] = 'User Cohorts';
// $string['title'] = 'User Cohorts';
// $string['heading'] = 'User Cohorts';
// $string['textfields'] = 'Filter';
// $string['submmit'] = 'Submit';
$string['no_selection'] = 'No Selection';
$string['search_user'] = 'User';
$string['greaterthan'] = 'greater than';
$string['lessthan'] = 'less than';
$string['equalto'] = 'equal to';

//Form string
$string['submmit'] = 'Submit';
$string['radiomakhoa'] = "Cohort Code";
$string['radiomalop'] = "Class Code";
$string['radiouser'] = "User";

$string['radio_active'] = "Active";
$string['radio_suppend'] = "suspended";
$string['radio_all'] = "All";

$string['radioteaching'] = "Teaching";
$string['radiocourse'] = "Course";

$string['searchoption'] = "Select Option";
$string['textfields'] = 'Filter';
$string['no_selection'] = 'No Selection';
$string['fromdate'] = 'From date';
$string['todate'] = 'To date';
$string['downloadcsv'] = 'Download in CSV format';
$string['downloadexcel'] = 'Download in Excel format';
$string['no_'] = 'No.';
$string['makhoa'] = 'Cohort Code';
$string['malop'] = 'Class Code';
$string['selectcourse'] = 'Course Code';
$string['fieldgrouprequired'] = 'You must supply value for at least one of these fields: "Mã Khóa", "Mã Lớp", "User"';

$string['course'] = 'Course';
$string['totalcourse'] = 'Total of questions';
$string['answered'] = 'Answered';
$string['answeredwithin24'] = 'Answered within 24';
$string['notanswered'] = 'Not Answered';

//settings
$string['enrollmentcourseshortname'] = 'Mã Khóa user profile field Short Name';
$string['classshortname'] = 'Mã Lớp user profile field Short Name';
$string['enrollmentcoursedesc'] = 'Mã Khóa user profile field Short Name, if mutiple custom fields provided, use comma as seperator.';
$string['classshortnamedesc'] = 'Mã Lớp user profile field Short Name, if mutiple custom fields provided, use comma as seperator.';
$string['sortby'] = 'Sort by';
$string['sortbydesc'] = 'Select field to sort';

$string['customfields1'] = 'Report user profile field ShortName 1';
$string['customfieldsdesc1'] = 'shortname of the user profile field to be display on report before username, if mutiple user profile field provided, use comma as seperator.(e.g: shortname or shortname1,shortname2)';

$string['customfields2'] = 'Report user profile field ShortName 2';
$string['customfieldsdesc2'] = 'shortname of the user profile field to be display on report after username, if mutiple user profile field provided, use comma as seperator.(e.g: shortname or shortname1,shortname2)';

$string['warningmakhoamamonhoc'] = 'Ma Khoa and CourseID must not be empty at the same time';
$string['warningmalopmamonhoc'] = 'Ma Lop and CourseID must not be empty at the same time';
$string['warningusermamonhoc'] = 'User and CourseID must not be empty at the same time';

$string['technical_hotline'] = 'Technical hotline';
$string['technical_hotlinedesc'] = 'Technical hotline, TNU: 0943846035, AOF: 0948293794';
$string['viewcourseinfo'] = 'View course information';
$string['viewcourseinfoshortname'] = 'View course information shortname';

$string['studentcodeshortname'] = 'Student code user profile field Short Name';
$string['studentcodeshortnamedesc'] = 'Student Code user profile field Short Name, if mutiple custom fields provided, use comma as seperator.';
$string['toomanymaloptoshow'] = 'Too many malop ({$a}) to show';
$string['toomanycoursetoshow'] = 'Too many course ({$a}) to show';
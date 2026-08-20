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
 *
 * @package   theme_lambda
 * @copyright 2023 redPIthemes
 *
 */

// $settings = null;

defined('MOODLE_INTERNAL') || die;

// Cần class readonly cho field mobilecssurl (xem local/mobilecssedit).
// File CSS thật sự chỉ được tạo/sửa/đổi tên qua local/mobilecssedit/managecss.php,
// nên field mobilecssurl ở đây chỉ hiển thị, không cho gõ tay để tránh lệch
// dữ liệu so với file trên đĩa.
require_once($CFG->dirroot . '/local/mobilecssedit/classes/admin_setting_readonlyurl.php');

$ADMIN->add('themes', new admin_category('theme_th_lambda_st', 'Theme-TH-Lambda-ST'));
    
$temp = new admin_settingpage('theme_th_lambda_st_colors',  get_string('settings_colors', 'theme_lambda'));

global $SESSION, $DB;

if (!empty($SESSION->currenteditingcompany)) {
    $companyid = $SESSION->currenteditingcompany;
    
    // Get company name for display
    $companytext = '';
    $company = $DB->get_record('company', array('id' => $companyid));
    if ($company && !empty($company->name)) {
        $companytext = ' - ' . $company->name;
    }

    // Create company-specific settings page
    $temp_company = new admin_settingpage('theme_th_lambda_st_company_colors',  get_string('settings_colors', 'theme_lambda') . $companytext);
    
    // Helper function to add company color setting
    if (!function_exists('th_lambda_st_add_company_color')) {
        function th_lambda_st_add_company_color($temp, $name, $title, $description, $default, $companyid, $companytext) {
            $company_name = $name . $companyid;
            $company_setting_name = 'theme_th_lambda_st/' . $company_name;
            $company_title = $title . $companytext;
            $setting = new admin_setting_configcolourpicker($company_setting_name, $company_title, $description, $default, null);
            $setting->set_updatedcallback('theme_th_lambda_st_company_color_settings_updated');
            $temp->add($setting);
        }
    }

    // Helper function to add a company-specific text/URL setting (e.g. custom mobile CSS URL).
    if (!function_exists('th_lambda_st_add_company_text')) {
        function th_lambda_st_add_company_text($temp, $name, $title, $description, $default, $companyid,
                $companytext, $paramtype = PARAM_RAW) {
            $company_name = $name . $companyid;
            $company_setting_name = 'theme_th_lambda_st/' . $company_name;
            $company_title = $title . $companytext;
            $setting = new admin_setting_configtext($company_setting_name, $company_title, $description, $default, $paramtype);
            $temp->add($setting);
        }
    }
    
    // Main theme color
    th_lambda_st_add_company_color($temp_company, 'maincolor',
        get_string('maincolor', 'theme_lambda'),
        get_string('maincolordesc', 'theme_lambda'),
        '#ffae00', $companyid, $companytext);
    
    // Main theme Hover color
    th_lambda_st_add_company_color($temp_company, 'mainhovercolor',
        get_string('mainhovercolor', 'theme_lambda'),
        get_string('mainhovercolordesc', 'theme_lambda'),
        '#efa300', $companyid, $companytext);
    
    // Link color
    th_lambda_st_add_company_color($temp_company, 'linkcolor',
        get_string('linkcolor', 'theme_lambda'),
        get_string('linkcolordesc', 'theme_lambda'),
        '#efa300', $companyid, $companytext);
    
    // Default Button color
    th_lambda_st_add_company_color($temp_company, 'def_buttoncolor',
        get_string('def_buttoncolor', 'theme_lambda'),
        get_string('def_buttoncolordesc', 'theme_lambda'),
        '#8ec63f', $companyid, $companytext);
    
    // Default Button Hover color
    th_lambda_st_add_company_color($temp_company, 'def_buttonhovercolor',
        get_string('def_buttonhovercolor', 'theme_lambda'),
        get_string('def_buttonhovercolordesc', 'theme_lambda'),
        '#77ae29', $companyid, $companytext);
    
    // Header color
    th_lambda_st_add_company_color($temp_company, 'headercolor',
        get_string('headercolor', 'theme_lambda'),
        get_string('headercolor_desc', 'theme_lambda'),
        '#ffffff', $companyid, $companytext);
    
    // Menu 1st Level color
    th_lambda_st_add_company_color($temp_company, 'menufirstlevelcolor',
        get_string('menufirstlevelcolor', 'theme_lambda'),
        get_string('menufirstlevelcolordesc', 'theme_lambda'),
        '#3A454b', $companyid, $companytext);
    
    // Menu 1st Level Links color
    th_lambda_st_add_company_color($temp_company, 'menufirstlevel_linkcolor',
        get_string('menufirstlevel_linkcolor', 'theme_lambda'),
        get_string('menufirstlevel_linkcolordesc', 'theme_lambda'),
        '#ffffff', $companyid, $companytext);
    
    // Menu 2nd Level color
    th_lambda_st_add_company_color($temp_company, 'menusecondlevelcolor',
        get_string('menusecondlevelcolor', 'theme_lambda'),
        get_string('menusecondlevelcolordesc', 'theme_lambda'),
        '#f4f4f4', $companyid, $companytext);
    
    // Menu 2nd Level Links color
    th_lambda_st_add_company_color($temp_company, 'menusecondlevel_linkcolor',
        get_string('menusecondlevel_linkcolor', 'theme_lambda'),
        get_string('menusecondlevel_linkcolordesc', 'theme_lambda'),
        '#444444', $companyid, $companytext);
    
    // Footer color
    th_lambda_st_add_company_color($temp_company, 'footercolor',
        get_string('footercolor', 'theme_lambda'),
        get_string('footercolordesc', 'theme_lambda'),
        '#323A45', $companyid, $companytext);
    
    // Footer Headings color
    th_lambda_st_add_company_color($temp_company, 'footerheadingcolor',
        get_string('footerheadingcolor', 'theme_lambda'),
        get_string('footerheadingcolordesc', 'theme_lambda'),
        '#f2f2f2', $companyid, $companytext);
    
    // Footer Text color
    th_lambda_st_add_company_color($temp_company, 'footertextcolor',
        get_string('footertextcolor', 'theme_lambda'),
        get_string('footertextcolordesc', 'theme_lambda'),
        '#bdc3c7', $companyid, $companytext);
    
    // Copyright color
    th_lambda_st_add_company_color($temp_company, 'copyrightcolor',
        get_string('copyrightcolor', 'theme_lambda'),
        get_string('copyrightcolordesc', 'theme_lambda'),
        '#292F38', $companyid, $companytext);
    
    // Copyright Text color
    th_lambda_st_add_company_color($temp_company, 'copyright_textcolor',
        get_string('copyright_textcolor', 'theme_lambda'),
        get_string('copyright_textcolordesc', 'theme_lambda'),
        '#bdc3c2', $companyid, $companytext);

    // Social icons color
    th_lambda_st_add_company_color($temp_company, 'socials_color',
        get_string('socials_color', 'theme_lambda'),
        get_string('socials_color_desc', 'theme_lambda'),
        '#a9a9a9', $companyid, $companytext);
    
    // Text color for mobile devices
    th_lambda_st_add_company_color($temp_company, 'text_mobile_color',
        get_string('text_mobile_color', 'theme_th_lambda_st'),
        get_string('text_mobile_color_desc', 'theme_th_lambda_st'),
        '', $companyid, $companytext);

    $mobilecssurl_setting = new local_mobilecssedit_admin_setting_readonlyurl(
        'theme_th_lambda_st/mobilecssurl' . $companyid,
        get_string('mobilecssurl', 'theme_th_lambda_st') . $companytext,
        get_string('mobilecssurl_desc', 'theme_th_lambda_st'),
        $companyid
    );
    $temp_company->add($mobilecssurl_setting);

    $ADMIN->add('theme_th_lambda_st', $temp_company);
}
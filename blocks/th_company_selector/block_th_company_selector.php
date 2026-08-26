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
 * Block IOMAD company selector
 *
 * @package   block_th_company_selector
 * @copyright 2021 Derick Turner
 * @author    Derick Turner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . "/local/iomad/lib/company.php");

/**
 * Default block class.
 */
class block_th_company_selector extends block_base {

    /**
     * Class initialisation function
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('title', 'block_th_company_selector');
    }

    /**
     * Function to check if the block has configuration.
     *
     * @return boolean
     */
    public function has_config() {
        return false;
    }

    /**
     * Function to check if we display the block header.
     *
     * @return void
     */
    public function hide_header() {
        return false;
    }

    /**
     * Get the block content.
     *
     * @return void
     */
    public function get_content() {
        global $USER, $CFG, $DB, $OUTPUT, $PAGE, $SESSION;

        $requestedcompany = optional_param('company', null, PARAM_INT);
        if (!empty($requestedcompany) && (empty($SESSION->currenteditingcompany)
            || (int) $SESSION->currenteditingcompany !== (int) $requestedcompany)) {
            require_sesskey();

            if ($companyrec = $DB->get_record('company', ['id' => $requestedcompany])) {
                if (!empty($SESSION->theme) && $companyrec->theme != $SESSION->theme) {
                    try {
                        $themeconfig = theme_config::load($companyrec->theme);
                        if ($themeconfig->name === $companyrec->theme) {
                            $SESSION->theme = $companyrec->theme;
                        } else {
                            unset($SESSION->theme);
                        }
                        unset($themeconfig);
                    } catch (Exception $e) {
                        debugging('Failed to set the theme from the company hostname setting.', DEBUG_DEVELOPER, $e->getTrace());
                    }
                }
            }

            $SESSION->currenteditingcompany = $requestedcompany;
            $returnurl = new moodle_url($PAGE->url);
            $returnurl->remove_params(['company', 'sesskey']);
            redirect($returnurl);
        }

        // Only display if you have the correct capability.
        if (!iomad::has_capability('block/th_company_selector:view', context_system::instance())) {
            return;
        }

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = (object) [];
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($this->instance)) {
            return $this->content;
        }

        if (!isloggedin()) {
            $this->content->text = get_string('pleaselogin', 'block_th_company_selector');
            return $this->content;
        }

        // Check users session and profile settings to get the current editing company.
        if (!empty($SESSION->currenteditingcompany)) {
            $selectedcompany = $SESSION->currenteditingcompany;
        } else if (!empty($USER->profile->company)) {
            $usercompany = company::by_userid($USER->id);
            $selectedcompany = $usercompany->id;
        } else {
            $selectedcompany = "";
        }

        // Get the company name if set.
        if (!empty($selectedcompany)) {
            $companyname = company::get_companyname_byid($selectedcompany);
        } else {
            $companyname = "";
        }

        // Get a list of companies.
        $companylist = $this->get_companies_select();
        $selecturl = new moodle_url($PAGE->url, ['sesskey' => sesskey()]);
        $selecturl->remove_params('company');
        $select = new single_select($selecturl,
                                                   'company',
                                                   $companylist,
                                                   $selectedcompany);
        $select->label = get_string('selectacompany', 'block_th_company_selector');
        $select->formid = 'choosecompany';
        $fwselectoutput = html_writer::tag('div', $OUTPUT->render($select), ['id' => 'th_company_selector']);
        $this->content->text = $OUTPUT->container_start('companyselect');
        if (!empty($SESSION->currenteditingcompany)) {
            $this->content->text .= '<p>' . get_string('currentcompanyname', 'block_th_company_selector', $companyname) .
                                    '</p>';
        } else {
            $this->content->text .= '<p>' . get_string('nocurrentcompany', 'block_th_company_selector') .
                                    '</p>';
        }
        $this->content->text .= $fwselectoutput;
        $this->content->text .= $OUTPUT->container_end();

        return $this->content;
    }

    public function get_companies_select($showsuspended=false) {
        global $CFG, $DB, $USER;

        // Is this an admin, or a normal user?
        if (iomad::has_capability('block/th_company_selector:view', context_system::instance())) {
            $sqlparams = [];
            $sqlwhere = "";
            if (!empty($CFG->iomad_show_company_structure)) {
                $sqlparams['parentid'] = 0;
                $sqlwhere .= " AND parentid = :parentid ";
            }
            if (!$showsuspended) {
                $sqlparams['suspended'] = 0;
                $sqlwhere .= " AND suspended = :suspended ";
            }
            $companies = $DB->get_records_sql_menu("SELECT id, CASE WHEN suspended=0 THEN name ELSE concat(name, ' (S)') END AS name FROM {company}
                                                    WHERE 1 = 1
                                                    $sqlwhere
                                                    ORDER BY name",
                                                    $sqlparams);
        } else {
            if ($showsuspended) {
                $suspendedsql = '';
            } else {
                $suspendedsql = "AND cu.suspended = 0";
            }
            // Show the hierarchy if required.
            if (!empty($CFG->iomad_show_company_structure)) {
                $companies = $DB->get_records_sql_menu("SELECT DISTINCT c.id, CASE WHEN c.suspended=0 THEN c.name ELSE concat(c.name, ' (S)') END AS name
                                                        FROM {company} c
                                                        JOIN {company_users} cu ON (c.id = cu.companyid)
                                                        WHERE cu.userid = :userid
                                                        $suspendedsql
                                                        ORDER BY name",
                                                        ['userid' => $USER->id,
                                                         'userid2' => $USER->id,
                                                         'suspended' => $showsuspended]);
            } else {
                $companies = $DB->get_records_sql_menu("SELECT DISTINCT c.id, CASE WHEN c.suspended=0 THEN c.name ELSE concat(c.name, ' (S)') END AS name
                                                        FROM {company} c
                                                        JOIN {company_users} cu ON (c.id = cu.companyid)
                                                        WHERE cu.userid = :userid
                                                        $suspendedsql
                                                        ORDER BY name",
                                                        ['userid' => $USER->id,
                                                         'userid2' => $USER->id,
                                                         'suspended' => $showsuspended]);
            }
        }
        // Show the hierarchy if required.
        if (!empty($CFG->iomad_show_company_structure)) {
            $companyselect = array();
            foreach ($companies as $id => $companyname) {
                $companyselect[$id] = $companyname;
                $allchildren = self::get_formatted_child_companies_select($id);
                $companyselect = $companyselect + $allchildren;
            }
            return $companyselect;
        } else {
            return $companies;
        }
    }

    private static function get_formatted_child_companies_select($companyid, &$companyarray = [], $prepend = "") {
        global $DB;

       if ($children = $DB->get_records('company', ['parentid' => $companyid ], 'name', 'id,name,parentid')) {
           $prepend = "--" . $prepend;
           foreach ($children as $child) {
               $companyarray[$child->id] = $prepend . format_string($child->name);
               self::get_formatted_child_companies_select($child->id, $companyarray, $prepend);
           }
        }
        return $companyarray;
    }
}


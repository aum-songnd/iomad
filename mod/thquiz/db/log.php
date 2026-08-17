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
 * Definition of log events for the thquiz module.
 *
 * @package    mod_thquiz
 * @category   log
 * @copyright  2010 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module'=>'thquiz', 'action'=>'add', 'mtable'=>'thquiz', 'field'=>'name'),
    array('module'=>'thquiz', 'action'=>'update', 'mtable'=>'thquiz', 'field'=>'name'),
    array('module'=>'thquiz', 'action'=>'view', 'mtable'=>'thquiz', 'field'=>'name'),
    array('module'=>'thquiz', 'action'=>'report', 'mtable'=>'thquiz', 'field'=>'name'),
    array('module'=>'thquiz', 'action'=>'attempt', 'mtable'=>'thquiz', 'field'=>'name'),
    array('module'=>'thquiz', 'action'=>'submit', 'mtable'=>'thquiz', 'field'=>'name'),
    array('module'=>'thquiz', 'action'=>'review', 'mtable'=>'thquiz', 'field'=>'name'),
    array('module'=>'thquiz', 'action'=>'editquestions', 'mtable'=>'thquiz', 'field'=>'name'),
    array('module'=>'thquiz', 'action'=>'preview', 'mtable'=>'thquiz', 'field'=>'name'),
    array('module'=>'thquiz', 'action'=>'start attempt', 'mtable'=>'thquiz', 'field'=>'name'),
    array('module'=>'thquiz', 'action'=>'close attempt', 'mtable'=>'thquiz', 'field'=>'name'),
    array('module'=>'thquiz', 'action'=>'continue attempt', 'mtable'=>'thquiz', 'field'=>'name'),
    array('module'=>'thquiz', 'action'=>'edit override', 'mtable'=>'thquiz', 'field'=>'name'),
    array('module'=>'thquiz', 'action'=>'delete override', 'mtable'=>'thquiz', 'field'=>'name'),
    array('module'=>'thquiz', 'action'=>'view summary', 'mtable'=>'thquiz', 'field'=>'name'),
);
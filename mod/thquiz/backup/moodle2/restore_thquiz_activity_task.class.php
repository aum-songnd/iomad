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
 * @package    mod_thquiz
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/thquiz/backup/moodle2/restore_thquiz_stepslib.php');


/**
 * thquiz restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 *
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_thquiz_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Thquiz only has one structure step.
        $this->add_step(new restore_thquiz_activity_structure_step('thquiz_structure', 'thquiz.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('thquiz', array('intro'), 'thquiz');
        $contents[] = new restore_decode_content('thquiz_feedback',
                array('feedbacktext'), 'thquiz_feedback');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('THQUIZVIEWBYID',
                '/mod/thquiz/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('THQUIZVIEWBYQ',
                '/mod/thquiz/view.php?q=$1', 'thquiz');
        $rules[] = new restore_decode_rule('THQUIZINDEX',
                '/mod/thquiz/index.php?id=$1', 'course');

        return $rules;

    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * thquiz logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    public static function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('thquiz', 'add',
                'view.php?id={course_module}', '{thquiz}');
        $rules[] = new restore_log_rule('thquiz', 'update',
                'view.php?id={course_module}', '{thquiz}');
        $rules[] = new restore_log_rule('thquiz', 'view',
                'view.php?id={course_module}', '{thquiz}');
        $rules[] = new restore_log_rule('thquiz', 'preview',
                'view.php?id={course_module}', '{thquiz}');
        $rules[] = new restore_log_rule('thquiz', 'report',
                'report.php?id={course_module}', '{thquiz}');
        $rules[] = new restore_log_rule('thquiz', 'editquestions',
                'view.php?id={course_module}', '{thquiz}');
        $rules[] = new restore_log_rule('thquiz', 'delete attempt',
                'report.php?id={course_module}', '[oldattempt]');
        $rules[] = new restore_log_rule('thquiz', 'edit override',
                'overrideedit.php?id={thquiz_override}', '{thquiz}');
        $rules[] = new restore_log_rule('thquiz', 'delete override',
                'overrides.php.php?cmid={course_module}', '{thquiz}');
        $rules[] = new restore_log_rule('thquiz', 'addcategory',
                'view.php?id={course_module}', '{question_category}');
        $rules[] = new restore_log_rule('thquiz', 'view summary',
                'summary.php?attempt={thquiz_attempt}', '{thquiz}');
        $rules[] = new restore_log_rule('thquiz', 'manualgrade',
                'comment.php?attempt={thquiz_attempt}&question={question}', '{thquiz}');
        $rules[] = new restore_log_rule('thquiz', 'manualgrading',
                'report.php?mode=grading&q={thquiz}', '{thquiz}');
        // All the ones calling to review.php have two rules to handle both old and new urls
        // in any case they are always converted to new urls on restore.
        // TODO: In Moodle 2.x (x >= 5) kill the old rules.
        // Note we are using the 'thquiz_attempt' mapping because that is the
        // one containing the thquiz_attempt->ids old an new for thquiz-attempt.
        $rules[] = new restore_log_rule('thquiz', 'attempt',
                'review.php?id={course_module}&attempt={thquiz_attempt}', '{thquiz}',
                null, null, 'review.php?attempt={thquiz_attempt}');
        $rules[] = new restore_log_rule('thquiz', 'attempt',
                'review.php?attempt={thquiz_attempt}', '{thquiz}',
                null, null, 'review.php?attempt={thquiz_attempt}');
        // Old an new for thquiz-submit.
        $rules[] = new restore_log_rule('thquiz', 'submit',
                'review.php?id={course_module}&attempt={thquiz_attempt}', '{thquiz}',
                null, null, 'review.php?attempt={thquiz_attempt}');
        $rules[] = new restore_log_rule('thquiz', 'submit',
                'review.php?attempt={thquiz_attempt}', '{thquiz}');
        // Old an new for thquiz-review.
        $rules[] = new restore_log_rule('thquiz', 'review',
                'review.php?id={course_module}&attempt={thquiz_attempt}', '{thquiz}',
                null, null, 'review.php?attempt={thquiz_attempt}');
        $rules[] = new restore_log_rule('thquiz', 'review',
                'review.php?attempt={thquiz_attempt}', '{thquiz}');
        // Old an new for thquiz-start attemp.
        $rules[] = new restore_log_rule('thquiz', 'start attempt',
                'review.php?id={course_module}&attempt={thquiz_attempt}', '{thquiz}',
                null, null, 'review.php?attempt={thquiz_attempt}');
        $rules[] = new restore_log_rule('thquiz', 'start attempt',
                'review.php?attempt={thquiz_attempt}', '{thquiz}');
        // Old an new for thquiz-close attemp.
        $rules[] = new restore_log_rule('thquiz', 'close attempt',
                'review.php?id={course_module}&attempt={thquiz_attempt}', '{thquiz}',
                null, null, 'review.php?attempt={thquiz_attempt}');
        $rules[] = new restore_log_rule('thquiz', 'close attempt',
                'review.php?attempt={thquiz_attempt}', '{thquiz}');
        // Old an new for thquiz-continue attempt.
        $rules[] = new restore_log_rule('thquiz', 'continue attempt',
                'review.php?id={course_module}&attempt={thquiz_attempt}', '{thquiz}',
                null, null, 'review.php?attempt={thquiz_attempt}');
        $rules[] = new restore_log_rule('thquiz', 'continue attempt',
                'review.php?attempt={thquiz_attempt}', '{thquiz}');
        // Old an new for thquiz-continue attemp.
        $rules[] = new restore_log_rule('thquiz', 'continue attemp',
                'review.php?id={course_module}&attempt={thquiz_attempt}', '{thquiz}',
                null, 'continue attempt', 'review.php?attempt={thquiz_attempt}');
        $rules[] = new restore_log_rule('thquiz', 'continue attemp',
                'review.php?attempt={thquiz_attempt}', '{thquiz}',
                null, 'continue attempt');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    public static function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('thquiz', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}

<?php
namespace local_th_examtype\external;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_module;

class get_quiz_by_cmid extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    public static function execute(int $cmid): array {
        global $DB;

        ['cmid' => $cmid] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid]
        );

        $cm = get_coursemodule_from_id('quiz', $cmid);
        if (!$cm) {
            return [
                'name'      => '',
                'exam_type' => '',
                'message'   => 'cmid is not a quiz activity',
            ];
        }

        $context = context_module::instance($cmid);
        self::validate_context($context);

        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        return [
            'name'      => $quiz->name,
            'exam_type' => self::get_exam_type($DB, $cmid),
            'message'   => '',
        ];
    }

    private static function get_exam_type(\moodle_database $DB, int $cmid): string {
        $sql = "SELECT cd.value
                  FROM {customfield_data} cd
                  JOIN {customfield_field} cf    ON cf.id  = cd.fieldid
                  JOIN {customfield_category} cc ON cc.id  = cf.categoryid
                 WHERE cc.component  = 'local_modcustomfields'
                   AND cf.shortname  = 'exam_type'
                   AND cd.instanceid = :cmid";

        $value = $DB->get_field_sql($sql, ['cmid' => $cmid]);
        if ($value === false || $value === '') {
            return '';
        }

        $options = self::get_exam_type_options($DB);
        $index   = (int) $value - 1;
        return isset($options[$index]) ? $options[$index] : '';
    }

    private static function get_exam_type_options(\moodle_database $DB): array {
        $sql = "SELECT cf.configdata
                  FROM {customfield_field} cf
                  JOIN {customfield_category} cc ON cc.id = cf.categoryid
                 WHERE cc.component = 'local_modcustomfields'
                   AND cf.shortname = 'exam_type'";

        $configdata = $DB->get_field_sql($sql);
        if (!$configdata) {
            return [];
        }

        $config = json_decode($configdata, true);
        if (empty($config['options'])) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode("\n", $config['options'])),
            fn($v) => $v !== ''
        ));
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'name'      => new external_value(PARAM_TEXT, 'Quiz name'),
            'exam_type' => new external_value(PARAM_TEXT, 'Value of exam_type custom field'),
            'message'   => new external_value(PARAM_TEXT, 'Error message if any'),
        ]);
    }
}
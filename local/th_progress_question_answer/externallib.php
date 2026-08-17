<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_th_progress_question_answer\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');
if (file_exists($CFG->dirroot . '/mod/quiz/attemptlib.php')) {
    require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');
}
require_once($CFG->dirroot . '/mod/quiz/classes/external.php');
require_once($CFG->libdir . '/externallib.php');


use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

// =============================================================================
// Shared trait – dùng chung cho cả 2 API
// =============================================================================

trait th_attempt_state_trait {

    // -------------------------------------------------------------------------
    // Constants – answer state
    // -------------------------------------------------------------------------

    private static function state_not_answered():       string { return 'not_answered'; }
    private static function state_partially_answered(): string { return 'partially_answered'; }
    private static function state_answered():           string { return 'answered'; }

    // -------------------------------------------------------------------------
    // Constants – mark state
    // -------------------------------------------------------------------------

    private static function mark_not_graded():        string { return 'not_graded'; }
    private static function mark_wrong():             string { return 'wrong'; }
    private static function mark_partially_correct(): string { return 'partially_correct'; }
    private static function mark_correct():           string { return 'correct'; }

    // -------------------------------------------------------------------------
    // children structure – dùng chung trong execute_returns() của cả 2 class
    // -------------------------------------------------------------------------

    private static function children_structure(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'key'   => new external_value(PARAM_TEXT, 'Sub-question key. Câu thường = "0", cluster = key của câu con'),
                'type'  => new external_value(PARAM_TEXT, 'Question type name'),
                'state' => new external_value(
                    PARAM_TEXT,
                    'Answer state: not_answered | partially_answered | answered',
                    VALUE_OPTIONAL,
                    ''
                ),
                'mark'  => new external_value(
                    PARAM_TEXT,
                    'Mark state: not_graded | wrong | partially_correct | correct',
                    VALUE_OPTIONAL,
                    ''
                ),
            ]),
            'Per-item states. Chỉ có với câu cluster (thvstepcluster).',
            VALUE_OPTIONAL
        );
    }

    // -------------------------------------------------------------------------
    // Validate options parameter
    // -------------------------------------------------------------------------

    private static function validate_options(int $options): void {
        if (!in_array($options, [1, 2, 3], true)) {
            throw new \invalid_parameter_exception('options must be 1, 2 or 3.');
        }
    }

    // -------------------------------------------------------------------------
    // Clone logic từ qtype_essay_question::check_input_word_count() (private)
    // Giữ nguyên 100% logic gốc, chỉ nhận tham số thay vì đọc từ $this
    // -------------------------------------------------------------------------

    private static function check_essay_word_count(
        string $responsestring,
        bool $responserequired,
        ?int $maxwordlimit,
        ?int $minwordlimit
    ): ?string {
        if (!$responserequired) {
            return null;
        }
        if (!$minwordlimit && !$maxwordlimit) {
            // This question does not care about the word count.
            return null;
        }

        // Count the number of words in the response string.
        $count = count_words($responsestring);

        if ($maxwordlimit && $count > $maxwordlimit) {
            return get_string('maxwordlimitboundary', 'qtype_essay',
                ['limit' => $maxwordlimit, 'count' => $count]);
        } else if ($count < $minwordlimit) {
            return get_string('minwordlimitboundary', 'qtype_essay',
                ['limit' => $minwordlimit, 'count' => $count]);
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // map_qa_state_to_answerstate
    // -------------------------------------------------------------------------

    private static function map_qa_state_to_answerstate(\question_attempt $qa): string {
        $state = $qa->get_state();

        if (!$state || $state == \question_state::$todo) {
            return self::state_not_answered();
        }
        if ($state == \question_state::$complete || $state->is_finished()) {
            return self::state_answered();
        }
        return self::state_partially_answered();
    }

    // -------------------------------------------------------------------------
    // Build children array từ cluster detail state
    // -------------------------------------------------------------------------

    private static function build_children_from_cluster(\question_attempt $qa, int $options): array {
        $detailstate = self::get_cluster_detail_state($qa, $options);
        $children    = [];

        foreach ($detailstate as $key => $item) {
            $child = ['key' => (string)$key, 'type' => $item['type']];
            if (array_key_exists('state', $item)) {
                $child['state'] = $item['state'];
            }
            if (array_key_exists('mark', $item)) {
                $child['mark'] = $item['mark'];
            }
            $children[] = $child;
        }

        return $children;
    }

    // -------------------------------------------------------------------------
    // get_cluster_detail_state – dùng prefix "answer{key}" trong qt_data
    // -------------------------------------------------------------------------

    private static function get_cluster_detail_state(\question_attempt $qa, int $options): array {
        $questionoriginal = $qa->get_question();
        $items            = $questionoriginal->question_items_instance ?? [];
        $response         = $qa->get_last_qt_data();
        $result           = [];

        foreach ($items as $key => $question) {

            if($question === null) {
                continue;
            }

            $type        = $question->get_type_name();
            $answerstate = self::state_not_answered();
            $markstate   = self::mark_not_graded();

            switch ($type) {

                case 'description':
                    $answerstate = '';
                    $markstate   = '';
                    break;

                case 'truefalse':
                    $value    = $qa->get_last_qt_var("answer{$key}", '');
                    $answered = !($value === '' || $value === null);

                    $answerstate = self::map_answered_state($answered);

                    if ($answered) {
                        $fraction = 0.0;
                        if ($value === '1' || $value === 1 || $value === true || $value === 'true') {
                            $fraction = (bool)$question->rightanswer ? 1.0 : 0.0;
                        } else if ($value === '0' || $value === 0 || $value === false || $value === 'false') {
                            $fraction = !(bool)$question->rightanswer ? 1.0 : 0.0;
                        }
                        $markstate = self::map_fraction_to_markstate($fraction);
                    } else {
                        $markstate = '';
                    }
                    break;

                case 'shortanswer':
                    $value    = trim((string)$qa->get_last_qt_var("answer{$key}", ''));
                    $answered = ($value !== '');

                    $answerstate = self::map_answered_state($answered);

                    if ($answered) {
                        $answer    = $question->get_matching_answer(['answer' => $value]);
                        $fraction  = $answer ? (float)$answer->fraction : 0.0;
                        $markstate = self::map_fraction_to_markstate($fraction);
                    } else {
                        $markstate = '';
                    }
                    break;

                case 'essay':
                    $step        = $qa->get_last_step_with_qt_var("answer{$key}");
                    $currenttext = '';

                    if ($step && $step->has_qt_var("answer{$key}")) {
                        $currenttext = (string)$step->get_qt_var("answer{$key}");
                    }

                    $hasresponse = trim(strip_tags($currenttext)) !== '';

                    if (!$hasresponse) {
                        $answerstate = self::state_not_answered();
                        $markstate   = '';
                    } else {
                        $validationerror = self::check_essay_word_count(
                            $currenttext,
                            (bool)$question->responserequired,
                            $question->maxwordlimit ?? null,
                            $question->minwordlimit ?? null
                        );
                        $answerstate = !empty($validationerror)
                            ? self::state_partially_answered()
                            : self::state_answered();
                        $markstate = self::mark_not_graded();
                    }
                    break;

                case 'recordrtc':
                    $variablename = "answer{$key}";
                    $hasresponse  = false;
                    $step         = $qa->get_last_step_with_qt_var($variablename);
                    $draftitemid  = '';

                    if ($step && $step->has_qt_var($variablename)) {
                        $draftitemid = (string)$step->get_qt_var($variablename);
                    }

                    $candidatecontexts = [];
                    if (!empty($qa->get_question()->contextid)) {
                        $candidatecontexts[] = (int)$qa->get_question()->contextid;
                    }
                    if (!empty($question->contextid)
                        && !in_array((int)$question->contextid, $candidatecontexts, true)
                    ) {
                        $candidatecontexts[] = (int)$question->contextid;
                    }

                    foreach ($candidatecontexts as $ctxid) {
                        $files = $qa->get_last_qt_files($variablename, $ctxid);
                        foreach ($files as $file) {
                            if ($file && !$file->is_directory() && $file->get_filesize() > 0) {
                                $hasresponse = true;
                                break 2;
                            }
                        }
                    }

                    if (!$hasresponse && trim((string)$qa->get_response_summary()) !== '') {
                        $hasresponse = true;
                    }
                    if (!$hasresponse && $draftitemid !== '' && $draftitemid !== '0') {
                        $hasresponse = true;
                    }

                    $answerstate = self::map_answered_state($hasresponse);
                    $markstate   = $hasresponse ? self::mark_not_graded() : '';
                    break;

                case 'multichoice':
                    $ismultiple     = $question instanceof \qtype_multichoice_multi_question;
                    $correctraw     = $question->get_correct_response();
                    $correctindexes = [];
                    $userindexes    = [];

                    if (!empty($correctraw)) {
                        if ($ismultiple) {
                            foreach ($correctraw as $name => $value) {
                                if ($value && preg_match('/^choice(\d+)$/', $name, $m)) {
                                    $correctindexes[] = (int)$m[1];
                                }
                            }
                        } else {
                            $correctindexes[] = (int)($correctraw['answer'] ?? -1);
                        }
                    }

                    if ($ismultiple) {
                        foreach ($question->get_order($qa) as $idx => $ansid) {
                            $value = $qa->get_last_qt_var("answer{$key}_choice{$idx}", 0);
                            if (!empty($value)) {
                                $userindexes[] = $idx;
                            }
                        }

                        if (empty($userindexes)) {
                            $answerstate = self::state_not_answered();
                            $markstate   = '';
                        } else {
                            $answerstate = self::map_answer_progress_state(count($userindexes), count($correctindexes));
                            $fraction    = 0.0;

                            if (method_exists($question, 'grade_response')) {
                                $graderesponse = [];
                                foreach ($userindexes as $idx) {
                                    $graderesponse['choice' . $idx] = 1;
                                }
                                list($fraction) = $question->grade_response($graderesponse);
                                $fraction = (float)$fraction;
                            }
                            $markstate = self::map_fraction_to_markstate($fraction);
                        }
                    } else {
                        $userresponse = $qa->get_last_qt_var("answer{$key}", '');

                        if ($userresponse === '' || $userresponse === null || (int)$userresponse < 0) {
                            $answerstate = self::state_not_answered();
                            $markstate   = '';
                        } else {
                            $answerstate = self::state_answered();
                            $fraction    = ((int)$userresponse === (int)($correctraw['answer'] ?? -999)) ? 1.0 : 0.0;
                            $markstate   = self::map_fraction_to_markstate($fraction);
                        }
                    }
                    break;

                case 'match':
                    $stemorder = $question->get_stem_order();
                    $answered  = 0;
                    $correct   = 0;
                    $i         = 1;
                    $total     = count($stemorder);

                    foreach ($stemorder as $stemid) {
                        $choice = $response["answer{$key}_{$i}"] ?? 0;
                        if (!empty($choice)) {
                            $answered++;
                        }
                        if ($choice && (string)$choice === (string)$question->get_right_choice_for($stemid)) {
                            $correct++;
                        }
                        $i++;
                    }

                    if ($answered === 0) {
                        $answerstate = self::state_not_answered();
                        $markstate   = '';
                    } else {
                        $answerstate = self::map_answer_progress_state($answered, $total);
                        $fraction    = $total > 0 ? ($correct / $total) : 0.0;
                        $markstate   = self::map_fraction_to_markstate($fraction);
                    }
                    break;

                case 'gapselect':
                case 'ddwtos':
                case 'thddwtos':
                    $totalplaces    = count($question->places);
                    $answeredplaces = 0;
                    $correctplaces  = 0;

                    foreach ($question->places as $place => $group) {
                        $fieldname = "answer{$key}_" . $question->field($place);

                        if (!array_key_exists($fieldname, $response)) {
                            continue;
                        }

                        $value = $response[$fieldname];

                        if ($value !== '' && $value !== null && $value !== '0' && $value !== 0) {
                            $answeredplaces++;
                        }
                        if ((string)$value === (string)$question->get_right_choice_for($place)) {
                            $correctplaces++;
                        }
                    }

                    if ($answeredplaces === 0) {
                        $answerstate = self::state_not_answered();
                        $markstate   = '';
                    } else {
                        $answerstate = self::map_answer_progress_state($answeredplaces, $totalplaces);
                        $fraction    = $totalplaces > 0 ? ($correctplaces / $totalplaces) : 0.0;
                        $markstate   = self::map_fraction_to_markstate($fraction);
                    }
                    break;

                case 'multianswer':
                    $fractionmax   = 0.0;
                    $usermark      = 0.0;
                    $answeredcount = 0;
                    $totalcount    = 0;

                    if (!empty($question->subquestions)) {
                        foreach ($question->subquestions as $i => $subq) {
                            if (empty($subq) || !is_object($subq)) {
                                continue;
                            }
                            $totalcount++;
                            $fractionmax += (float)$subq->defaultmark;

                            $substep = new \question_attempt_step_subquestion_adapter(
                                null, 'sub' . $i . $key . '_'
                            );
                            $subresp = $substep->filter_array($response);

                            if ($subq->is_gradable_response($subresp)) {
                                $answeredcount++;
                                list($subfraction) = $subq->grade_response($subresp);
                                $usermark += ((float)$subfraction * (float)$subq->defaultmark);
                            }
                        }
                    }

                    if ($answeredcount === 0) {
                        $answerstate = self::state_not_answered();
                        $markstate   = '';
                    } else {
                        $answerstate = self::map_answer_progress_state($answeredcount, $totalcount);
                        $fraction    = ($fractionmax > 0) ? ($usermark / $fractionmax) : 0.0;
                        $markstate   = self::map_fraction_to_markstate($fraction);
                    }
                    break;

                default:
                    $value    = $qa->get_last_qt_var("answer{$key}", '');
                    $answered = !($value === '' || $value === null);

                    $answerstate = self::map_answered_state($answered);

                    if ($answered && method_exists($question, 'grade_response')) {
                        list($fraction) = $question->grade_response(['answer' => $value]);
                        $markstate = self::map_fraction_to_markstate($fraction);
                    } else {
                        $markstate = $answered ? self::mark_not_graded() : '';
                    }
                    break;
            }

            $result[$key] = self::build_result_item($type, $answerstate, $markstate, $options);
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function build_result_item(string $type, string $answerstate, string $markstate, int $options): array {
        if ($options === 1) {
            return ['type' => $type, 'state' => $answerstate];
        }
        if ($options === 2) {
            return ['type' => $type, 'mark' => $markstate];
        }
        return ['type' => $type, 'state' => $answerstate, 'mark' => $markstate];
    }

    private static function map_fraction_to_markstate($fraction): string {
        if ($fraction === null) {
            return self::mark_not_graded();
        }
        $fraction = (float)$fraction;
        if ($fraction >= 1.0) {
            return self::mark_correct();
        }
        if ($fraction > 0.0) {
            return self::mark_partially_correct();
        }
        return self::mark_wrong();
    }

    private static function map_answer_progress_state(int $answeredcount, int $totalcount): string {
        if ($answeredcount <= 0) {
            return self::state_not_answered();
        }
        if ($totalcount > 0 && $answeredcount < $totalcount) {
            return self::state_partially_answered();
        }
        return self::state_answered();
    }

    private static function map_answered_state(bool $answered): string {
        return $answered ? self::state_answered() : self::state_not_answered();
    }
}

// =============================================================================
// API 1: get_attempt_summary
// =============================================================================

/**
 * Mở rộng get_attempt_summary với answerstate cho từng slot
 * và children (per-item state) cho câu cluster (thvstepcluster).
 *
 * @package local_th_progress_question_answer
 */
class get_attempt_summary extends external_api {
    use th_attempt_state_trait;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
            'preflightdata' => new external_multiple_structure(
                new external_single_structure([
                    'name'  => new external_value(PARAM_ALPHANUMEXT, 'Data name'),
                    'value' => new external_value(PARAM_RAW, 'Data value'),
                ]),
                'Preflight required data (e.g. quiz password)',
                VALUE_DEFAULT,
                []
            ),
            'options' => new external_value(
                PARAM_INT,
                'What to return for children: 1=answer state only, 2=mark state only, 3=both (default)',
                VALUE_DEFAULT,
                3
            ),
        ]);
    }

    public static function execute_returns(): external_single_structure {
        $base = \mod_quiz_external::get_attempt_summary_returns();

        $question_structure = $base->keys['questions']->content ?? null;
        $question_keys = ($question_structure instanceof external_single_structure)
            ? $question_structure->keys
            : [];

        return new external_single_structure([
            'questions' => new external_multiple_structure(
                new external_single_structure(array_merge(
                    $question_keys,
                    [
                        'answerstate' => new external_value(
                            PARAM_TEXT,
                            'Overall answer state: not_answered | partially_answered | answered',
                            VALUE_OPTIONAL,
                            ''
                        ),
                        'children' => self::children_structure(),
                    ]
                )),
                'List of questions with additional answer state and children for cluster'
            ),
            'warnings' => $base->keys['warnings'] ?? new external_multiple_structure(
                new external_single_structure([])
            ),
        ]);
    }

    public static function execute(int $attemptid, array $preflightdata = [], int $options = 3): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid'     => $attemptid,
            'preflightdata' => $preflightdata,
            'options'       => $options,
        ]);

        self::validate_options($params['options']);

        $summary = \mod_quiz_external::get_attempt_summary(
            $params['attemptid'],
            $params['preflightdata']
        );

        if (empty($summary['questions']) || !is_array($summary['questions'])) {
            if (!isset($summary['warnings'])) {
                $summary['warnings'] = [];
            }
            return $summary;
        }

        $questionsbyslot = [];
        foreach ($summary['questions'] as $idx => $q) {
            if (isset($q['slot'])) {
                $questionsbyslot[(int)$q['slot']] = $idx;
            }
        }

        $quizattempt = \quiz_attempt::create($params['attemptid']);

        foreach ($quizattempt->get_slots() as $slot) {
            if (!isset($questionsbyslot[$slot])) {
                continue;
            }

            $qa       = $quizattempt->get_question_attempt($slot);
            $question = $qa->get_question();
            $idx      = $questionsbyslot[$slot];

            $summary['questions'][$idx]['answerstate'] = self::map_qa_state_to_answerstate($qa);

            if ($question->get_type_name() === 'thvstepcluster') {
                $summary['questions'][$idx]['children'] = self::build_children_from_cluster($qa, $params['options']);
            }
        }

        return $summary;
    }
}

// =============================================================================
// API 2: get_attempt_review
// =============================================================================

/**
 * Mở rộng get_attempt_review, bổ sung children
 * (per-item state) cho câu cluster (thvstepcluster).
 *
 * @package local_th_progress_question_answer
 */
class get_attempt_review extends external_api {
    use th_attempt_state_trait;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Quiz attempt ID'),
            'page' => new external_value(
                PARAM_INT,
                'Page number (-1 for all questions, 0-based)',
                VALUE_DEFAULT,
                -1
            ),
            'options' => new external_value(
                PARAM_INT,
                'What to return for children: 1=answer state only, 2=mark state only, 3=both (default)',
                VALUE_DEFAULT,
                3
            ),
        ]);
    }

    public static function execute_returns(): external_single_structure {
        $base = \mod_quiz_external::get_attempt_review_returns();
        return new external_single_structure([
            'grade'          => $base->keys['grade'],
            'attempt'        => $base->keys['attempt'],
            'additionaldata' => $base->keys['additionaldata'],
            'warnings'       => $base->keys['warnings'],
            'questions' => new external_multiple_structure(
                new external_single_structure(array_merge(
                    $base->keys['questions']->content->keys,
                    [
                        'children' => self::children_structure(),
                    ]
                )),
                'Questions with their states'
            ),
        ]);
    }

    public static function execute(int $attemptid, int $page = -1, int $options = 3): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
            'page'      => $page,
            'options'   => $options,
        ]);

        $attemptid = $params['attemptid'];
        $page      = $params['page'];
        $options   = $params['options'];

        self::validate_options($options);

        $review = \mod_quiz_external::get_attempt_review($attemptid, $page);

        if (empty($review['questions']) || !is_array($review['questions'])) {
            return $review;
        }

        $questionsbyslot = [];
        foreach ($review['questions'] as $idx => $q) {
            if (isset($q['slot'])) {
                $questionsbyslot[(int)$q['slot']] = $idx;
            }
        }

        $quizattempt = \quiz_attempt::create($attemptid);

        foreach ($quizattempt->get_slots() as $slot) {
            if (!isset($questionsbyslot[$slot])) {
                continue;
            }

            $qa       = $quizattempt->get_question_attempt($slot);
            $question = $qa->get_question();

            if ($question->get_type_name() === 'thvstepcluster') {
                $idx = $questionsbyslot[$slot];
                $review['questions'][$idx]['children'] = self::build_children_from_cluster($qa, $options);
            }
        }

        return $review;
    }
}
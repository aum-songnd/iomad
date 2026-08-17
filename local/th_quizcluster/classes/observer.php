<?php
namespace local_th_quizcluster;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for local_th_quizcluster.
 */
class observer {

    /* =========================
     * DB/schema helpers (cache)
     * ========================= */
    protected static ?bool $has_qcat_path = null;
    protected static ?bool $has_qbe_schema = null;

    // Cache.
    protected static array $subtree_cache = [];  // [rootcatid => [catids...]]
    protected static array $taglist_cache = [];  // [sig => [tags...]]
    protected static array $question_cat_cache = []; // [questionid => categoryid]

    /**
     * Detect column question_categories.path.
     */
    protected static function question_categories_has_path(): bool {
        global $DB;

        if (self::$has_qcat_path !== null) {
            return self::$has_qcat_path;
        }

        try {
            $cols = $DB->get_columns('question_categories');
            self::$has_qcat_path = isset($cols['path']);
        } catch (\Throwable $e) {
            self::$has_qcat_path = false;
        }

        return self::$has_qcat_path;
    }

    /**
     * Detect Moodle 4+ schema (question_versions + question_bank_entries).
     */
    protected static function has_question_bank_schema(): bool {
        global $DB;

        if (self::$has_qbe_schema !== null) {
            return self::$has_qbe_schema;
        }

        try {
            $man = $DB->get_manager();
            self::$has_qbe_schema = $man->table_exists('question_bank_entries') && $man->table_exists('question_versions');
        } catch (\Throwable $e) {
            self::$has_qbe_schema = false;
        }

        return self::$has_qbe_schema;
    }

    /**
     * Moodle 4+: condition to keep ONLY latest READY version per question bank entry.
     * This function only returns an SQL snippet + params. It assumes the query JOINed question_versions as $aliasqv.
     */
    protected static function sql_latest_ready_version_condition(string $aliasqv = 'qv', string $prefix = 'lr_'): array {
        if (!self::has_question_bank_schema()) {
            return ['', []];
        }

        $pready1 = $prefix . 'ready1';
        $pready2 = $prefix . 'ready2';

        $sql = " AND {$aliasqv}.status = :{$pready1}
                 AND {$aliasqv}.version = (
                     SELECT MAX(qv2.version)
                       FROM {question_versions} qv2
                      WHERE qv2.questionbankentryid = {$aliasqv}.questionbankentryid
                        AND qv2.status = :{$pready2}
                 )";

        return [$sql, [
            $pready1 => 'ready',
            $pready2 => 'ready',
        ]];
    }

    /**
     * Get subtree category ids of $rootcatid.
     * - If qc.path exists: use LIKE prefix%
     * - Else: BFS by parent
     * Has loop protection.
     */
    protected static function get_category_subtree_ids(int $rootcatid): array {
        global $DB;

        if ($rootcatid <= 0) {
            return [];
        }
        if (isset(self::$subtree_cache[$rootcatid])) {
            return self::$subtree_cache[$rootcatid];
        }

        $ids = [];

        if (self::question_categories_has_path()) {
            $rootpath = (string)$DB->get_field('question_categories', 'path', ['id' => $rootcatid], IGNORE_MISSING);
            $ids[$rootcatid] = true;

            if ($rootpath !== '') {
                $prefix = $rootpath;
                if (substr($prefix, -1) !== '/') {
                    $prefix .= '/';
                }
                $sql = "SELECT id
                          FROM {question_categories}
                         WHERE path LIKE :pfx";
                $rows = $DB->get_fieldset_sql($sql, ['pfx' => $prefix . '%']);
                foreach ($rows as $cid) {
                    $ids[(int)$cid] = true;
                }
            }
        } else {
            $queue = [$rootcatid];
            $guard = 0;

            while (!empty($queue)) {
                $cid = (int)array_shift($queue);
                if ($cid <= 0 || isset($ids[$cid])) {
                    continue;
                }

                $ids[$cid] = true;

                $guard++;
                if ($guard > 10000) {
                    // Safety guard for bad DB / loops.
                    break;
                }

                $children = $DB->get_fieldset_select('question_categories', 'id', 'parent = :p', ['p' => $cid]);
                if (!empty($children)) {
                    foreach ($children as $ch) {
                        $queue[] = (int)$ch;
                    }
                }
            }
        }

        $out = array_keys($ids);
        sort($out);
        self::$subtree_cache[$rootcatid] = $out;

        return $out;
    }

    /**
     * JOIN question -> category for both schemas.
     * Returns: [joinsSQL, catFieldSQL]
     * - Moodle 4+: qv + qbe + qc (qc.id = qbe.questioncategoryid)
     * - Old: qc.id = q.category
     */
    protected static function sql_from_question_to_category(string $aliasq = 'q', string $aliasqc = 'qc'): array {
        if (self::has_question_bank_schema()) {
            $joins = "JOIN {question_versions} qv ON qv.questionid = {$aliasq}.id
                      JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                      JOIN {question_categories} {$aliasqc} ON {$aliasqc}.id = qbe.questioncategoryid";
            return [$joins, "{$aliasqc}.id"];
        }

        $joins = "JOIN {question_categories} {$aliasqc} ON {$aliasqc}.id = {$aliasq}.category";
        return [$joins, "{$aliasqc}.id"];
    }

    /**
     * Get categoryid of a question (cached).
     */
    protected static function get_question_category_id(int $questionid): int {
        global $DB;

        if ($questionid <= 0) {
            return 0;
        }
        if (isset(self::$question_cat_cache[$questionid])) {
            return (int)self::$question_cat_cache[$questionid];
        }

        $catid = 0;

        if (self::has_question_bank_schema()) {
            $sql = "SELECT qbe.questioncategoryid
                      FROM {question_versions} qv
                      JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                     WHERE qv.questionid = :qid";
            $catid = (int)$DB->get_field_sql($sql, ['qid' => $questionid]);
        } else {
            $catid = (int)$DB->get_field('question', 'category', ['id' => $questionid], IGNORE_MISSING);
        }

        self::$question_cat_cache[$questionid] = $catid;
        return $catid;
    }

    /**
     * Tính target maxmark theo đúng nghiệp vụ:
     * - description = 0
     * - các câu còn lại lấy theo question.defaultmark
     * - nếu defaultmark <= 0 thì fallback 1.0
     */
    protected static function get_targetmax_for_question(object $q): float {
        if ($q->qtype === 'description') {
            return 0.0;
        }

        $defaultmark = isset($q->defaultmark) ? (float)$q->defaultmark : 0.0;
        if ($defaultmark > 0) {
            return $defaultmark;
        }

        return 1.0;
    }

    /**
     * Lưu snapshot maxmark theo từng slot của từng attempt.
     * - manualslotmarks=1: giữ maxmark hiện tại của attempt
     * - manualslotmarks=0: lưu theo question.defaultmark thực tế của attempt đó
     */
    protected static function snapshot_attempt_slot_marks(int $attemptid, int $quizid, int $qubaid, bool $manual): void {
        global $DB;

        $qas = $DB->get_records(
            'question_attempts',
            ['questionusageid' => $qubaid],
            '',
            'id, slot, questionid, maxmark'
        );
        if (!$qas) {
            return;
        }

        $questionids = array_values(array_unique(array_map(fn($qa) => (int)$qa->questionid, $qas)));
        $questions = [];
        if (!empty($questionids)) {
            $questions = $DB->get_records_list('question', 'id', $questionids, '', 'id, qtype, defaultmark');
        }

        $now = time();

        foreach ($qas as $qa) {
            $qid = (int)$qa->questionid;
            $slot = (int)$qa->slot;

            if ($manual) {
                $targetmax = (float)$qa->maxmark;
            } else {
                if (empty($questions[$qid])) {
                    continue;
                }
                $targetmax = self::get_targetmax_for_question($questions[$qid]);

                if ((float)$qa->maxmark !== $targetmax) {
                    $DB->set_field('question_attempts', 'maxmark', $targetmax, ['id' => $qa->id]);
                }
            }

            $existing = $DB->get_record(
                'local_th_qc_attemptmk',
                ['attemptid' => $attemptid, 'slot' => $slot],
                '*',
                IGNORE_MISSING
            );

            if ($existing) {
                $existing->quizid = $quizid;
                $existing->questionusageid = $qubaid;
                $existing->questionid = $qid;
                $existing->maxmark = $targetmax;
                $existing->timemodified = $now;
                $DB->update_record('local_th_qc_attemptmk', $existing);
            } else {
                $rec = (object)[
                    'attemptid' => $attemptid,
                    'quizid' => $quizid,
                    'questionusageid' => $qubaid,
                    'slot' => $slot,
                    'questionid' => $qid,
                    'maxmark' => $targetmax,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                $DB->insert_record('local_th_qc_attemptmk', $rec);
            }
        }
    }

    /**
     * Lấy snapshot maxmark theo slot.
     */
    protected static function get_snapshot_marks_by_slot(int $attemptid): array {
        global $DB;

        $rows = $DB->get_records(
            'local_th_qc_attemptmk',
            ['attemptid' => $attemptid],
            '',
            'id, slot, maxmark'
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r->slot] = (float)$r->maxmark;
        }

        ksort($out);
        return $out;
    }

    /**
     * Áp snapshot maxmark của attempt về lại question_attempts khi submit.
     */
    protected static function apply_snapshot_marks_to_attempt(int $attemptid, int $qubaid): void {
        global $DB;

        $snapshots = self::get_snapshot_marks_by_slot($attemptid);
        if (!$snapshots) {
            return;
        }

        $qas = $DB->get_records(
            'question_attempts',
            ['questionusageid' => $qubaid],
            '',
            'id, slot, maxmark'
        );
        if (!$qas) {
            return;
        }

        foreach ($qas as $qa) {
            $slot = (int)$qa->slot;
            if (!array_key_exists($slot, $snapshots)) {
                continue;
            }

            $targetmax = (float)$snapshots[$slot];
            if ((float)$qa->maxmark !== $targetmax) {
                $DB->set_field('question_attempts', 'maxmark', $targetmax, ['id' => $qa->id]);
            }
        }
    }

    /**
     * Tính bundle điểm theo snapshot:
     * - rawmark: Σ(fraction * snapshot_maxmark)
     * - attemptmax: tổng snapshot maxmark
     * - globalsumgrades: quy đổi rawmark sang thang raw global của quiz.sumgrades
     * - displaygrade: quy đổi ra thang quiz.grade
     */
    protected static function calculate_attempt_snapshot_bundle(
        int $attemptid,
        int $qubaid,
        float $quizsumgrades,
        float $quizgrade
    ): array {
        global $CFG;

        $snapshots = self::get_snapshot_marks_by_slot($attemptid);
        if (empty($snapshots)) {
            return [
                'rawmark' => 0.0,
                'attemptmax' => 0.0,
                'globalsumgrades' => 0.0,
                'displaygrade' => 0.0,
            ];
        }

        require_once($CFG->dirroot . '/question/engine/lib.php');
        $quba = \question_engine::load_questions_usage_by_activity($qubaid);

        $rawmark = 0.0;
        $attemptmax = 0.0;

        foreach ($quba->get_slots() as $slot) {
            $slot = (int)$slot;
            if (!array_key_exists($slot, $snapshots)) {
                continue;
            }

            $slotmax = (float)$snapshots[$slot];
            $attemptmax += $slotmax;

            $qa = $quba->get_question_attempt($slot);
            $fraction = $qa->get_fraction();
            if ($fraction === null) {
                $fraction = 0.0;
            }

            $rawmark += ((float)$fraction * $slotmax);
        }

        $globalsumgrades = 0.0;
        $displaygrade = 0.0;

        if ($attemptmax > 0) {
            $ratio = $rawmark / $attemptmax;
            $globalsumgrades = $ratio * $quizsumgrades;
            $displaygrade = $ratio * $quizgrade;
        }

        return [
            'rawmark' => (float)$rawmark,
            'attemptmax' => (float)$attemptmax,
            'globalsumgrades' => (float)$globalsumgrades,
            'displaygrade' => (float)$displaygrade,
        ];
    }

    /**
     * Đồng bộ quiz_grades + gradebook theo logic core Moodle.
     */
    protected static function sync_core_quiz_grade_records(object $quiz, int $userid): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        quiz_save_best_grade($quiz, $userid);
    }

    /* =========================
     * Main entry
     * ========================= */

    public static function quiz_attempt_started(\core\event\base $event): bool {
        global $DB;

        if (($event->component ?? '') !== 'mod_quiz') {
            return true;
        }

        $attemptid = (int)$event->objectid;
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'id, quiz, uniqueid', IGNORE_MISSING);
        if (!$attempt) {
            return true;
        }

        $quizid = (int)$attempt->quiz;
        $qubaid = (int)$attempt->uniqueid;

        $cfg = $DB->get_record(
            'local_th_quizcluster_cfg',
            ['quizid' => $quizid],
            'manualslotmarks, enabletagrandom',
            IGNORE_MISSING
        );

        $manual = $cfg && !empty($cfg->manualslotmarks);
        $enabletagrandom = $cfg && !empty($cfg->enabletagrandom);

        // manualslotmarks CHỈ ảnh hưởng điểm, KHÔNG ảnh hưởng tag random.

        $qas = $DB->get_records(
            'question_attempts',
            ['questionusageid' => $qubaid],
            '',
            'id, slot, questionid, maxmark, behaviour'
        );
        if (!$qas) {
            return true;
        }

        $questionids = array_unique(array_map(fn($qa) => (int)$qa->questionid, $qas));
        $questions = $DB->get_records_list('question', 'id', $questionids, '', 'id, qtype, defaultmark');

        $slots = $DB->get_records('quiz_slots', ['quizid' => $quizid], '', 'id, slot, maxmark');
        $slotsbyslot = [];
        foreach ($slots as $s) {
            $slotsbyslot[$s->slot] = $s;
        }

        $taggedqas = [];

        $tx = $DB->start_delegated_transaction();
        try {
            foreach ($qas as $qa) {
                $qid = (int)$qa->questionid;
                if (empty($questions[$qid])) {
                    continue;
                }

                $q = $questions[$qid];
                $slot = $slotsbyslot[$qa->slot] ?? null;

                /* ===== Sync điểm (CHỈ khi không manual) ===== */
                if (!$manual) {
                    $targetmax = self::get_targetmax_for_question($q);

                    // Chỉ update question_attempts của attempt hiện tại.
                    if ((float)$qa->maxmark !== $targetmax) {
                        $DB->set_field('question_attempts', 'maxmark', $targetmax, ['id' => $qa->id]);
                    }

                    // KHÔNG update quiz_slots ở đây.
                    // quiz_slots là dữ liệu dùng chung của quiz, nếu sửa ở đây sẽ làm attempt khác bị ảnh hưởng.
                }

                /* ===== Collect tag slots (KHÔNG phụ thuộc manual) ===== */
                if ($enabletagrandom && $q->qtype !== 'description') {
                    $catid = self::get_question_category_id($qid);
                    $taggedqas[] = (object)[
                        'qaid'          => $qa->id,
                        'slot'          => $qa->slot,
                        'slotid'        => $slot ? $slot->id : 0,
                        'questionid'    => $qid,
                        'qtype'         => $q->qtype,
                        'allowedcatids' => $catid ? self::get_category_subtree_ids($catid) : [],
                    ];
                }
            }

            // KHÔNG update quiz.sumgrades ở đây.
            // sumgrades của quiz là dữ liệu global, không được phép đổi theo từng attempt random.

            if ($enabletagrandom && count($taggedqas) > 1) {
                self::enforce_same_tag_for_tagged_questions_strict(
                    $taggedqas,
                    $quizid,
                    $qubaid
                );
            }

            // Sau khi random xong, lưu snapshot trọng số theo attempt.
            self::snapshot_attempt_slot_marks($attemptid, $quizid, $qubaid, $manual);

            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }

        return true;
    }

    /**
     * Khi submit bài:
     * - dùng snapshot của chính attempt
     * - lưu quiz_attempts.sumgrades theo THANG RAW GLOBAL của quiz
     * - đồng bộ quiz_grades + gradebook
     */
    public static function quiz_attempt_submitted(\core\event\base $event): bool {
        global $DB;
    
        if (($event->component ?? '') !== 'mod_quiz') {
            return true;
        }
    
        $attemptid = (int)$event->objectid;
        $attempt = $DB->get_record(
            'quiz_attempts',
            ['id' => $attemptid],
            'id, quiz, uniqueid, userid',
            IGNORE_MISSING
        );
        if (!$attempt) {
            return true;
        }
    
        // PHẢI lấy full record, không được lấy rút gọn.
        $quiz = $DB->get_record(
            'quiz',
            ['id' => $attempt->quiz],
            '*',
            IGNORE_MISSING
        );
        if (!$quiz) {
            return true;
        }
    
        $qubaid = (int)$attempt->uniqueid;
    
        $tx = $DB->start_delegated_transaction();
        try {
            // 1) Ép lại maxmark từng câu theo snapshot của chính attempt.
            self::apply_snapshot_marks_to_attempt($attemptid, $qubaid);
    
            // 2) Tính điểm theo snapshot.
            $bundle = self::calculate_attempt_snapshot_bundle(
                $attemptid,
                $qubaid,
                (float)$quiz->sumgrades,
                (float)$quiz->grade
            );
    
            // 3) Lưu sumgrades theo thang raw global của quiz.
            $DB->set_field(
                'quiz_attempts',
                'sumgrades',
                (float)$bundle['globalsumgrades'],
                ['id' => $attemptid]
            );
    
            // 4) Đồng bộ quiz_grades + gradebook theo logic core.
            self::sync_core_quiz_grade_records($quiz, (int)$attempt->userid);
    
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
    
        return true;
    }

    /**
     * STRICT enforce:
     * - Each slot can ONLY pick questions within its own allowed categories (category subtree of current question).
     * - If any slot has no tags OR no common tag OR cannot pick enough questions => STOP (no swaps).
     * - Includes ALL qtypes (including essay), BUT replacement must keep SAME qtype as the slot to avoid behaviour/grading mismatch.
     */
    protected static function enforce_same_tag_for_tagged_questions_strict(array $taggedqas, int $quizid, int $qubaid): void {
        usort($taggedqas, function($a, $b) {
            return ((int)$a->slot) <=> ((int)$b->slot);
        });

        if (count($taggedqas) < 2) {
            return;
        }

        // 1) Find intersection of tags across all pools.
        $common = null;

        foreach ($taggedqas as $info) {
            $allowed = array_values(array_unique(array_filter(array_map('intval', (array)($info->allowedcatids ?? [])))));
            if (empty($allowed)) {
                return;
            }

            $possible = self::get_tag_names_in_allowed_categories_cached($allowed);

            if (empty($possible)) {
                return;
            }

            if ($common === null) {
                $common = $possible;
            } else {
                $common = array_values(array_intersect($common, $possible));
            }

            if (empty($common)) {
                return;
            }
        }

        if (empty($common)) {
            return;
        }

        // 2) Choose a tag from the intersection.
        $tagname = $common[array_rand($common)];

        // 3) Build a swap plan first. If any slot cannot be satisfied => STOP (no swap at all).
        $used = [];
        foreach ($taggedqas as $info) {
            $used[(int)$info->questionid] = true;
        }

        $plan = [];

        foreach ($taggedqas as $info) {
            $qid   = (int)$info->questionid;
            $qtype = (string)($info->qtype ?? '');

            // If already has chosen tag => keep.
            $tags = self::get_tags_for_question($qid);
            if (in_array($tagname, $tags, true)) {
                continue;
            }

            $allowed = array_values(array_unique(array_filter(array_map('intval', (array)($info->allowedcatids ?? [])))));
            $excludeids = array_keys($used);

            $newqid = self::pick_question_with_tag_in_allowed_categories(
                $tagname,
                $allowed,
                $excludeids,
                $qid,
                $qtype // keep same qtype per slot
            );

            if (empty($newqid) || (int)$newqid === $qid) {
                return;
            }

            $plan[] = [(object)$info, (int)$newqid];
            $used[(int)$newqid] = true;
        }

        // 4) Apply plan.
        foreach ($plan as $item) {
            [$info, $newqid] = $item;

            self::swap_question_and_restart_slot(
                $qubaid,
                (int)$info->qaid,
                (int)$info->slot,
                (int)$newqid,
                $quizid,
                (int)($info->slotid ?? 0)
            );
        }
    }

    /**
     * Cache tag list by allowed categories signature.
     */
    protected static function get_tag_names_in_allowed_categories_cached(array $allowedcatids): array {
        $allowedcatids = array_values(array_unique(array_filter(array_map('intval', $allowedcatids))));
        sort($allowedcatids);
        $sig = implode(',', $allowedcatids);

        if (isset(self::$taglist_cache[$sig])) {
            return self::$taglist_cache[$sig];
        }

        self::$taglist_cache[$sig] = self::get_tag_names_in_categories($allowedcatids);
        return self::$taglist_cache[$sig];
    }

    /**
     * Get all tag names appearing in given category ids only (STRICT).
     * - No parent climbing
     * - Moodle 4+: latest READY only
     */
    protected static function get_tag_names_in_categories(array $catids): array {
        global $DB;

        $catids = array_values(array_unique(array_filter(array_map('intval', $catids))));
        if (empty($catids)) {
            return [];
        }

        list($catsql, $catparams) = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'c');

        [$joins, $catfield] = self::sql_from_question_to_category('q', 'qc');
        [$latestSql, $latestParams] = self::sql_latest_ready_version_condition('qv', 'tg_');

        $sql = "SELECT DISTINCT t.name
                  FROM {question} q
                  $joins
                  JOIN {tag_instance} ti
                    ON ti.itemid = q.id
                   AND ti.component = 'core_question'
                   AND ti.itemtype = 'question'
                  JOIN {tag} t ON t.id = ti.tagid
                 WHERE $catfield $catsql
                   AND q.qtype <> 'random'
                   AND q.qtype <> 'description'
                   $latestSql";

        $params = array_merge($catparams, $latestParams);
        $rows = $DB->get_records_sql($sql, $params);
        if (!$rows) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = $r->name;
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    /**
     * Pick a question within allowed categories having tag.
     * - Must keep same qtype as slot (if provided)
     * - Moodle 4+: latest READY only
     */
    protected static function pick_question_with_tag_in_allowed_categories(
        string $tagname,
        array $allowedcatids,
        array $excludeids = [],
        int $currentqid = 0,
        string $requiredqtype = ''
    ): ?int {
        global $DB;

        $allowedcatids = array_values(array_unique(array_filter(array_map('intval', $allowedcatids))));
        if (empty($allowedcatids) || $tagname === '') {
            return null;
        }

        list($catsql, $catparams) = $DB->get_in_or_equal($allowedcatids, SQL_PARAMS_NAMED, 'c');

        [$joins, $catfield] = self::sql_from_question_to_category('q', 'qc');
        [$latestSql, $latestParams] = self::sql_latest_ready_version_condition('qv', 'pk_');

        $sql = "SELECT DISTINCT q.id
                  FROM {question} q
                  $joins
                  JOIN {tag_instance} ti
                    ON ti.itemid = q.id
                   AND ti.component = 'core_question'
                   AND ti.itemtype = 'question'
                  JOIN {tag} t ON t.id = ti.tagid
                 WHERE $catfield $catsql
                   AND t.name = :tagname
                   AND q.qtype <> 'random'
                   AND q.qtype <> 'description'
                   $latestSql";

        $params = array_merge($catparams, ['tagname' => $tagname], $latestParams);

        if ($currentqid > 0) {
            $sql .= " AND q.id <> :currentqid";
            $params['currentqid'] = (int)$currentqid;
        }

        if ($requiredqtype !== '') {
            $sql .= " AND q.qtype = :rqtype";
            $params['rqtype'] = $requiredqtype;
        }

        $excludeids = array_values(array_unique(array_filter(array_map('intval', $excludeids))));
        if (!empty($excludeids)) {
            list($exsql, $exparams) = $DB->get_in_or_equal($excludeids, SQL_PARAMS_NAMED, 'ex', false);
            $sql .= " AND q.id $exsql";
            $params = array_merge($params, $exparams);
        }

        $dbfamily = $DB->get_dbfamily();
        if ($dbfamily === 'mariadb' || $dbfamily === 'mysql') {
            $sql .= " ORDER BY RAND()";
        } else if ($dbfamily === 'postgres') {
            $sql .= " ORDER BY RANDOM()";
        }
        $sql .= " LIMIT 1";

        $newid = $DB->get_field_sql($sql, $params);
        return $newid ? (int)$newid : null;
    }

    /**
     * Swap question + reset steps/step_data + restart slot.
     * Also sets safe behaviour:
     *  - description => informationitem
     *  - essay/recordrtc => manualgraded
     */
    protected static function swap_question_and_restart_slot(
        int $qubaid,
        int $qaid,
        int $slot,
        int $newqid,
        int $quizid,
        int $slotid
    ): void {
        global $DB, $CFG;

        $newq = $DB->get_record('question', ['id' => $newqid], 'id, qtype, defaultmark', IGNORE_MISSING);
        if (!$newq) {
            return;
        }

        // Never swap into a random question placeholder.
        if ($newq->qtype === 'random') {
            return;
        }

        // 1) Update questionid.
        $DB->set_field('question_attempts', 'questionid', $newqid, ['id' => $qaid]);

        // 2) Behaviour before restarting.
        $currentbehaviour = (string)($DB->get_field('question_attempts', 'behaviour', ['id' => $qaid], IGNORE_MISSING) ?: 'deferredfeedback');
        $desiredbehaviour = self::desired_behaviour_for_qtype((string)$newq->qtype, $currentbehaviour);
        if (!empty($desiredbehaviour) && $desiredbehaviour !== $currentbehaviour) {
            $DB->set_field('question_attempts', 'behaviour', $desiredbehaviour, ['id' => $qaid]);
            $currentbehaviour = $desiredbehaviour;
        }

        // 3) Giữ nguyên logic cũ, không update global quiz_slots tại đây.
        if ($newq->qtype === 'description') {
            $targetmax = 0.0;
        } else if ($newq->qtype === 'thvstepcluster') {
            $targetmax = (float)$newq->defaultmark;
        } else {
            $targetmax = 1.0;
        }

        // $DB->set_field('question_attempts', 'maxmark', $targetmax, ['id' => $qaid]);
        // $DB->set_field('quiz_slots', 'maxmark', ...);

        // 4) Delete old steps.
        $stepids = $DB->get_fieldset_select('question_attempt_steps', 'id', 'questionattemptid = :qaid', ['qaid' => $qaid]);
        if (!empty($stepids)) {
            list($insql, $params) = $DB->get_in_or_equal($stepids, SQL_PARAMS_NAMED, 's');
            $DB->delete_records_select('question_attempt_step_data', "attemptstepid $insql", $params);
        }
        $DB->delete_records('question_attempt_steps', ['questionattemptid' => $qaid]);

        // 5) Restart question.
        try {
            require_once($CFG->dirroot . '/question/engine/lib.php');

            $quba = \question_engine::load_questions_usage_by_activity($qubaid);

            // start_question(slot, variant, timenow)
            $quba->start_question($slot, null, time());

            \question_engine::save_questions_usage_by_activity($quba);

            // Safety: ensure behaviour.
            $afterbehaviour = (string)($DB->get_field('question_attempts', 'behaviour', ['id' => $qaid], IGNORE_MISSING) ?: '');
            if (!empty($desiredbehaviour) && $afterbehaviour !== $desiredbehaviour) {
                $DB->set_field('question_attempts', 'behaviour', $desiredbehaviour, ['id' => $qaid]);
            }
        } catch (\Throwable $e) {
            debugging('[local_th_quizcluster] swap_question_and_restart_slot error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Safe behaviour mapping by qtype.
     */
    protected static function desired_behaviour_for_qtype(string $qtype, string $fallback): string {
        if ($qtype === 'description') {
            return 'informationitem';
        }
        if ($qtype === 'essay' || $qtype === 'recordrtc') {
            return 'manualgraded';
        }
        return $fallback ?: 'deferredfeedback';
    }

    /**
     * Get tag names of a question.
     */
    protected static function get_tags_for_question(int $questionid): array {
        $map = self::get_tags_map_for_questions([$questionid]);
        return array_values($map[$questionid] ?? []);
    }

    /**
     * Batch load tags: [questionid => [tagname...]].
     */
    protected static function get_tags_map_for_questions(array $questionids): array {
        global $DB;

        $questionids = array_values(array_unique(array_filter(array_map('intval', $questionids))));
        if (empty($questionids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'q');

        $sql = "SELECT ti.itemid AS questionid, t.name
                  FROM {tag_instance} ti
                  JOIN {tag} t ON t.id = ti.tagid
                 WHERE ti.component = 'core_question'
                   AND ti.itemtype = 'question'
                   AND ti.itemid $insql
              ORDER BY ti.itemid, t.name";

        $rs = $DB->get_recordset_sql($sql, $params);

        $map = [];
        foreach ($rs as $r) {
            $qid = (int)$r->questionid;
            if (!isset($map[$qid])) {
                $map[$qid] = [];
            }
            $map[$qid][] = $r->name;
        }
        $rs->close();

        return $map;
    }
}
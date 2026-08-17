<?php

require_once __DIR__ . '/../../../config.php';
require_once $CFG->dirroot . '/question/behaviour/deferredfeedback/behaviour.php';

class qbehaviour1_deferredfeedback extends qbehaviour_deferredfeedback {

    public function render(question_display_options $options, $number,
            core_question_renderer $qoutput, qtype_renderer $qtoutput) {
        $behaviouroutput = $this->get_renderer($qoutput->get_page());
        $options = clone($options);
        $this->adjust_display_options($options);
        
        return $qoutput->question($this->qa, $behaviouroutput, $qtoutput, $options, $number);
    }

    public function get_renderer(moodle_page $page) {
        return $page->get_renderer('qbehaviour_deferredfeedback');
    }

}
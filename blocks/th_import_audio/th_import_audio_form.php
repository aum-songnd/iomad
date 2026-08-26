<?php

require_once($CFG->dirroot . '/lib/formslib.php');
require_once("lib.php");

class th_import_audio_form extends moodleform {

    function definition() {
        global $DB;
        $mform = $this->_form;
        $mform->addElement('header', 'displayinfo', get_string('filter'));
        // Lấy companyid từ customdata (được truyền từ view.php)
        $companyid = $this->_customdata['companyid'] ?? 0;

        if (empty($companyid) || $companyid < 1) {
         $listcourses = ['' => ''];
        } else {
          $listcourses = th_import_audio_list_courses($companyid);
        }

        $options = array(                                                                                                           
            'multiple' => false,                                                  
            'noselectionstring' => get_string('no_selection', 'block_th_import_audio'),                                                              
        ); 
        $element = $mform->addElement(
            'autocomplete', 
            'course_id', 
            get_string('select_course', 'block_th_import_audio'), 
            $listcourses, 
            $options
        );
        $mform->addRule('course_id', null, 'required', null, 'client');
        
        $link = "<a href='example.zip'>example.zip</a>";
        $mform->addElement(
            'static', 
            'example', 
            get_string('sample_zip_file', 'block_th_import_audio'), 
            $link
        );

        $mform->addElement('filepicker', 'newfile', get_string('file'), null,
            array(
                'accepted_types' => array('.zip'),
                'areamaxbytes' => 100000000,
                'maxfiles' => 1,
            )
        );

        $mform->addRule('newfile', null, 'required', null, 'client');

        $this->add_action_buttons(true,  get_string('submit'));

        $mform->addElement(
            'static', 
            'note', 
            '<strong>' . get_string('note', 'block_th_import_audio') . '</strong>', 
            '<strong>' . get_string('audio_filename_note', 'block_th_import_audio') . '</strong>'
        );
    }
    
    function validation($data, $files) {
        if($data['course_id'] == 0){
            return array('course_id' => get_string('no_course_selected', 'block_th_import_audio'));
        }
    }
}

class confirm_form extends moodleform {

    protected function definition() {
        global $SESSION;

        $th_import_audio_key = $this->_customdata['th_import_audio_key'];

        $mform = $this->_form;

        $mform->addElement('hidden', 'key');
        $mform->setType('key', PARAM_RAW);
        $mform->setDefault('key', $th_import_audio_key);

        $showbutton = true;
        $check_import = null;
        if (isset($SESSION->block_th_import_audio) && array_key_exists($th_import_audio_key, $SESSION->block_th_import_audio)) {
            $check_import = $SESSION->block_th_import_audio[$th_import_audio_key];
            if (isset($check_import->valid_import_found) && empty($check_import->valid_import_found)) {
                $showbutton = false;
            }
        }

        if ($showbutton) {
            $buttonstring = get_string('submit');
            $this->add_action_buttons(true, $buttonstring);
        }
    }
}
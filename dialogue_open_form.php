<?php

/**
 * This page builds a Dialogue Open form when called from view.php or dialogues.php
 * 
 * This class extends moodleform overriding the definition() method only
 * @package dialogue
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/formslib.php');

class mod_dialogue_open_form extends moodleform {

    function definition() {

        global $CFG, $COURSE, $DB;

        $mform    =& $this->_form;
        
        $context = $this->_customdata['context'];
        $maxbytes = $COURSE->maxbytes; // Could also use $CFG->maxbytes if you are not coding within a course context
        $editoroptions = array('subdirs'=>false, 'maxfiles'=>1, 'maxbytes'=>$maxbytes, 'trusttext'=>true, 'context'=>$context,'accepted_types'=>'image');
        $attachmentoptions = array('subdirs'=>false, 'maxfiles'=>1, 'maxbytes'=>$maxbytes);

        $names = (array) $this->_customdata['names'];
        $names = array('' => get_string('select').'...') + $names;

        
        $mform->addElement('header', 'general', '');//fill in the data depending on page params
        $mform->addElement('select', 'recipientid', get_string('openadialoguewith', 'dialogue'), $names);
        $mform->addRule('recipientid', get_string('required'), 'required', null, 'client');
        
        $mform->addElement('text', 'subject', get_string('subject', 'dialogue'), 'size="48"');
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');

        $mform->addElement('editor', 'firstentry', get_string('typefirstentry', 'dialogue'), null, $editoroptions);
        $mform->setType('firstentry', PARAM_CLEANHTML);
        $mform->addRule('firstentry', get_string('required'), 'required', null, 'client');

        $mform->addElement('filemanager', 'attachment', get_string('attachment', 'dialogue'), null, $attachmentoptions);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        
        $mform->addElement('hidden', 'entryid');
        $mform->setType('entryid', PARAM_INT);
        
        $mform->addElement('hidden', 'conversationid');
        $mform->setType('conversationid', PARAM_INT);
        
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_TEXT);
        
        $this->add_action_buttons(true, get_string('opendialogue','dialogue'));
    }
}
?>
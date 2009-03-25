<?php  // $Id: dialogue_open_form.php,v 1.1.2.2 2009/03/25 21:23:32 deeknow Exp $

require_once($CFG->libdir.'/formslib.php');

class mod_dialogue_open_form extends moodleform {

    function definition() {

        global $CFG, $COURSE;
        
        $this->set_upload_manager(new upload_manager('attachment', true, false, $COURSE, false, 0, true, true));
        
        $mform =& $this->_form;
        
        
        $names = (array)$this->_customdata['names'];
        $names = array('' => get_string('select').'...')+$names;
        
        $mform->addElement('header', 'general', '');//fill in the data depending on page params
        $mform->addElement('select', 'recipientid', get_string("openadialoguewith", "dialogue"), $names);
        $mform->addRule('recipientid', get_string('required'), 'required', null, 'client');
        
        $mform->addElement('text', 'subject', get_string("subject", "dialogue"), 'size="48"');
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');
        
        $mform->addElement('htmleditor', 'firstentry',  get_string("typefirstentry", "dialogue"), array('cols'=>80, 'rows'=>20));
        $mform->setType('firstentry', PARAM_CLEANHTML);
        $mform->addRule('firstentry', get_string('required'), 'required', null, 'client');
        $mform->setHelpButton('firstentry', array('reading', 'writing', 'questions', 'richtext'), false, 'editorhelpbutton');
        
        $mform->addElement('file', 'attachment', get_string('attachment', 'dialogue'));
        
        
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        
        $mform->addElement('hidden', 'entryid');
        $mform->setType('entryid', PARAM_INT);
        
        $mform->addElement('hidden', 'conversationid');
        $mform->setType('conversationid', PARAM_INT);
        
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_TEXT);
        
        $this->add_action_buttons(true, get_string("opendialogue","dialogue"));
    }
}
?>
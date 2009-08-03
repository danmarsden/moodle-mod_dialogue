<?php  // $Id: dialogue_reply_form.php,v 1.1.2.3 2009/08/03 03:06:31 deeknow Exp $

/**
 * This page builds a Dialogue Reply form when called from view.php or dialogues.php
 * 
 * This class extends moodleform overriding the definition() method only
 * @package dialogue
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require_once($CFG->libdir.'/formslib.php');

class mod_dialogue_reply_form extends moodleform {

    function definition() {

        global $CFG, $COURSE;
        
        $this->set_upload_manager(new upload_manager('attachment', true, false, $COURSE, false, 0, true, true));
        
        $mform =& $this->_form;
        $conversationid = $this->_customdata['conversationid'];
        $currentattachment = isset($this->_customdata['currentattachment']) ? $this->_customdata['currentattachment'] : false;
      
        $mform->addElement('htmleditor', "reply$conversationid",  get_string('typefollowup', 'dialogue'), array('cols'=>80, 'rows'=>20));
        $mform->setType("reply$conversationid", PARAM_CLEANHTML);
        $mform->addRule("reply$conversationid", get_string('required'), 'required', null, 'client');
        $mform->setHelpButton("reply$conversationid", array('reading', 'writing', 'questions', 'richtext'), false, 'editorhelpbutton');
        
        if ($currentattachment) {
            $mform->addElement('static', 'attachmentname', get_string('currentattachment', 'dialogue'), $currentattachment);
            $mform->addElement('checkbox', 'deleteattachment', get_string('deleteattachment', 'dialogue'));
        }
       
        
        $mform->addElement('file', 'attachment', get_string('attachment', 'dialogue'));
        
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_TEXT);
        
        $mform->addElement('hidden', 'pane');
        $mform->setType('pane', PARAM_INT);
        
        $mform->addElement('hidden', 'entryid');
        $mform->setType('entryid', PARAM_INT);
        
        $this->add_action_buttons(true, get_string('addmynewentry', 'dialogue'));
    }
}
?>
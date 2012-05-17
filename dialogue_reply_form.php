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
 * This page builds a Dialogue Reply form when called from view.php or dialogues.php
 * 
 * This class extends moodleform overriding the definition() method only
 * @package dialogue
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/formslib.php');

class mod_dialogue_reply_form extends moodleform {

    function definition() {

        global $CFG, $COURSE, $DB;

        $mform    =& $this->_form;
        

        $conversationid = $this->_customdata['conversationid'];
        $context        = $this->_customdata['context'];

        $maxbytes = $COURSE->maxbytes; // Could also use $CFG->maxbytes if you are not coding within a course context
        $editoroptions = array('subdirs'=>false, 'maxfiles'=>-1, 'maxbytes'=>$maxbytes, 'trusttext'=>true, 'context'=>$context,'accepted_types'=>'image');
        $attachmentoptions = array('subdirs'=>false, 'maxfiles'=>1, 'maxbytes'=>$maxbytes);

        $mform->addElement('editor', "reply$conversationid", get_string('typefollowup', 'dialogue'), null, $editoroptions);
        $mform->setType("reply$conversationid", PARAM_CLEANHTML);
        $mform->addRule("reply$conversationid", get_string('required'), 'required', null, 'client');

        $mform->addElement('filemanager', 'attachment', get_string('attachment', 'dialogue'), null, $attachmentoptions);
        
        
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

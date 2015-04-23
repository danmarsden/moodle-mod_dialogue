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

namespace mod_dialogue\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/dialogue/formlib.php');

class message_form extends \moodleform {
    public $readonly = false;

    protected function definition() {
        global $PAGE;

        $mform    = $this->_form;
        $cm       = $PAGE->cm;
        $context  = $PAGE->context;


        if (!isset($this->_customdata['actions'])) {
            $actions =  array('send', 'save', 'cancel', 'trash');
        } else {
            $actions = $this->_customdata['actions'];
        }


        $mform->addElement('editor', 'body', get_string('message', 'dialogue'), null, self::editor_options());
        $mform->setType('body', PARAM_RAW);


        if (!get_config('dialogue', 'maxattachments') or !empty($PAGE->activityrecord->maxattachments))  {  //  0 = No attachments at all
            $mform->addElement('filemanager', 'attachments[itemid]', get_string('attachments', 'dialogue'), null, self::attachment_options());
        }

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ACTION);
        $mform->setDefault('action', 'edit');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'dialogueid');
        $mform->setType('dialogueid', PARAM_INT);

        $mform->addElement('hidden', 'conversationid');
        $mform->setType('conversationid', PARAM_INT);

        $mform->addElement('hidden', 'messageid');
        $mform->setType('messageid', PARAM_INT);


        $mform->addElement('header', 'actionssection', get_string('actions', 'dialogue'));

        $actionbuttongroup = array();
        if (in_array('send', $actions)) {
            $actionbuttongroup[] =& $mform->createElement('submit', 'send', get_string('send', 'dialogue'), array('class'=>'send-button'));
        }
        if (in_array('save', $actions)) {
            $actionbuttongroup[] =& $mform->createElement('submit', 'save', get_string('save', 'dialogue'), array('class'=>'savedraft-button'));
        }
        if (in_array('cancel', $actions)) {
            $actionbuttongroup[] =& $mform->createElement('submit', 'cancel', get_string('cancel'), array('class'=>'cancel-button'));
        }
        if (in_array('trash', $actions)) {
            $actionbuttongroup[] =& $mform->createElement('submit', 'trash', get_string('trash', 'dialogue'), array('class'=>'trashdraft-button pull-right'));
        }
        $mform->addGroup($actionbuttongroup, 'actionbuttongroup', '', ' ', false);

        $mform->setExpanded('actionssection', true);


    }

    /**
     * Intercept the display of form so can format errors as notifications
     *
     * @global type $OUTPUT
     */
    public function display() {
        global $OUTPUT;

        if ($this->_form->_errors) {
            foreach($this->_form->_errors as $error) {
                echo $OUTPUT->notification($error, 'notifyproblem');
            }
            unset($this->_form->_errors);
        }

        parent::display();
    }
    /**
     * Returns the options array to use in dialogue text editor
     *
     * @return array
     */
    public static function editor_options() {
        global $CFG, $COURSE, $PAGE;

        $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);
        return array(
            'collapsed' => true,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $maxbytes,
            'trusttext'=> true,
            'accepted_types' => '*',
            'return_types'=> FILE_INTERNAL | FILE_EXTERNAL
        );
    }

    /**
     * Returns the options array to use in filemanager for dialogue attachments
     *
     * @return array
     */
    public static function attachment_options() {
        global $CFG, $COURSE, $PAGE;
        $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes, $PAGE->activityrecord->maxbytes);
        return array(
            'subdirs' => 0,
            'maxbytes' => $maxbytes,
            'maxfiles' => $PAGE->activityrecord->maxattachments,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL
        );
    }

    /**
     *
     * @param type $data
     * @param type $files
     * @return type
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['body']['text'])) {
            $errors['body'] = get_string('erroremptymessage', 'dialogue');
        }

        return $errors;
    }

    /**
     *
     * @return null
     */
    public function get_submit_action() {
        $submitactions = array('send', 'save', 'cancel', 'trash');

        foreach($submitactions as $submitaction) {
            if (optional_param($submitaction, false, PARAM_BOOL)) {
                return $submitaction;
            }
        }
        return null;
    }

    public function definition_after_data() {
        $data = $this->_customdata['data'];

        $this->set_data(array('id'=>$data['messageid']));
        $this->set_data(array('cmid'=>$data['cmid']));
        $this->set_data(array('dialogueid'=>$data['dialogueid']));
        $this->set_data(array('conversationid'=>$data['conversationid']));
        $this->set_data(array('messageid'=>$data['messageid']));
        $this->set_data(array('body'=>$data['body']));
        $this->set_data(array('attachments[itemid]'=>$data['attachments']['itemid']));

    }
}
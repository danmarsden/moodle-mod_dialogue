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

defined('MOODLE_INTERNAL') || die();

/**
 * This page builds a ?????? TODO
 *
 * This class extends moodleform overriding the definition() method only
 * @package dialogue
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

// load repository lib, will load filelib and formslib
require_once($CFG->dirroot . '/repository/lib.php');

class mod_dialogue_message_form extends moodleform {

    protected function definition() {
        global $PAGE;

        $mform    = $this->_form;
        $cm       = $PAGE->cm;
        $context  = $PAGE->context;

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

        $mform->addElement('hidden', 'dialogueid');
        $mform->setType('dialogueid', PARAM_INT);

        $mform->addElement('hidden', 'conversationid');
        $mform->setType('conversationid', PARAM_INT);

        $mform->addElement('hidden', 'messageid');
        $mform->setType('messageid', PARAM_INT);


        $mform->addElement('header', 'actionssection', get_string('actions', 'dialogue'));

        $actionbuttongroup = array();
        $actionbuttongroup[] =& $mform->createElement('submit', 'send', get_string('send', 'dialogue'), array('class'=>'send-button'));
        $actionbuttongroup[] =& $mform->createElement('submit', 'save', get_string('savedraft', 'dialogue'), array('class'=>'savedraft-button'));
        $actionbuttongroup[] =& $mform->createElement('submit', 'cancel', get_string('cancel'), array('class'=>'cancel-button'));

        $actionbuttongroup[] =& $mform->createElement('submit', 'trash', get_string('trashdraft', 'dialogue'), array('class'=>'trashdraft-button pull-right'));
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
     * Helper method, because removeElement can't handle groups and there no
     * method to do this, how suckful!
     *
     * @param string $elementname
     * @param string $groupname
     */
    public function remove_from_group($elementname, $groupname) {
        $group = $this->_form->getElement($groupname);
        foreach ($group->_elements as $key => $element) {
            if ($element->_attributes['name'] == $elementname) {
                unset($group->_elements[$key]);
            }
        }
    }

    /**
     * Helper method
     * @param type $name
     * @param type $options
     * @param type $selected
     * @return type
     */
    public function update_selectgroup($name, $options, $selected=array()) {
        $mform   = $this->_form;
        $element = $mform->getElement($name);
        $element->_optGroups = array(); //reset the optgroup array()
        return $element->loadArrayOptGroups($options, $selected);
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

}
class mod_dialogue_reply_form extends mod_dialogue_message_form {
    protected function definition() {
        $mform    = $this->_form;
        $mform->addElement('header', 'messagesection', get_string('reply', 'dialogue'));
        $mform->setExpanded('messagesection', true);
        parent::definition();
    }
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}
/**
 *
 */
class mod_dialogue_conversation_form extends mod_dialogue_message_form {
    protected function definition() {
        global $PAGE, $OUTPUT;

        $mform    = $this->_form;
        $cm       = $PAGE->cm;
        $context  = $PAGE->context;

        //$mform->disable_form_change_checker();

        $mform->addElement('header', 'openwithsection', get_string('openwith', 'dialogue'));

        /** autocomplete javascript **/
        $html = '';
        $html .= html_writer::start_tag('div', array('class'=>'fitem fitem_ftext'));
        $html .= html_writer::start_tag('div', array( 'class'=>'fitemtitle'));
        $html .= html_writer::tag('label', get_string('people', 'dialogue'), array('for'=>'people_autocomplete_input'));
        $html .= html_writer::end_tag('div');
        $html .= html_writer::start_tag('div', array('class'=>'felement ftext'));
        $html .= html_writer::start_tag('div', array('id'=>'participant_autocomplete_field', 'class' => 'js-control yui3-aclist-field'));
        $html .= html_writer::tag('input', '', array('id'=>'participant_autocomplete_input', 'class' => 'input-xxlarge', 'placeholder' => get_string('searchpotentials', 'dialogue')));
        $html .= html_writer::tag('span', '', array('class'=>'drop-down-arrow'));
        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('div');
        // add to form
        $mform->addElement('html', $html);
        /** non javascript **/
        $mform->addElement('html', '<div class="nonjs-control">'); // non-js wrapper
        $mform->addElement('text', 'p_query'); //'Person search'
        $mform->setType('p_query', PARAM_RAW);
        $psearchbuttongroup = array();
        $psearchbuttongroup[] = $mform->createElement('submit', 'p_search', get_string('search'));
        $mform->registerNoSubmitButton('p_search');
        $psearchbuttongroup[] = $mform->createElement('submit', 'p_clear', get_string('clear'));
        $mform->registerNoSubmitButton('p_clear');
        $mform->addGroup($psearchbuttongroup, 'psearchbuttongroup', '', ' ', false);
        $attributes = array();
        $attributes['size'] = 5;

        $mform->addElement('selectgroups', 'p_select', get_string('people', 'dialogue'),
                           array(),
                           $attributes);

        $mform->addElement('html', '</div>'); // end non-js wrapper

        // bulk open rule section
        if (has_capability('mod/dialogue:bulkopenrulecreate', $context)){
            $groups = array(); // use for form
            $groups[''] = get_string('select').'...';
            $groups['course-'.$PAGE->course->id] = get_string('allparticipants');
            if (has_capability('moodle/site:accessallgroups', $context)) {
                $allowedgroups = groups_get_all_groups($PAGE->course->id, 0);
            } else {
                $allowedgroups = groups_get_all_groups($PAGE->course->id, $USER->id);
            }
            foreach ($allowedgroups as $allowedgroup) {
                $groups['group-'.$allowedgroup->id] = $allowedgroup->name;
            }
            // make sure have groups, possible group mode but no groups yada yada
            if ($groups) {
                $mform->addElement('header', 'bulkopenrulessection', get_string('bulkopenrule', 'dialogue'));
                $notify = $OUTPUT->notification(get_string('bulkopenrulenotifymessage', 'dialogue'), 'notifymessage');
                $mform->addElement('html', $notify);
                $mform->addElement('select','groupinformation', get_string('group'), $groups);
                $mform->addElement('checkbox', 'includefuturemembers', get_string('includefuturemembers', 'dialogue'));
                $mform->disabledIf('includefuturemembers', 'groupinformation', 'eq', '');
                $mform->addElement('date_selector', 'cutoffdate', get_string('cutoffdate', 'dialogue'));
                $mform->setDefault('cutoffdate', time() + 3600 * 24 * 7);
                $mform->disabledIf('cutoffdate', 'includefuturemembers', 'notchecked');
            }
        }

        $mform->addElement('header', 'messagesection', get_string('message', 'dialogue'));

        $mform->addElement('text', 'subject', get_string('subject', 'dialogue'), array('class'=>'input-xxlarge'));//array('size'=>'80%')array('placeholder'=>'Include a subject...')
        $mform->setType('subject', PARAM_TEXT);

        $mform->setExpanded('messagesection', true);

        parent::definition();
    }

    /**
     *
     * @return boolean
     */
    public function definition_after_data(){
        global $PAGE;
        $mform   = $this->_form;

        $q = optional_param('p_query', '', PARAM_TEXT);
        if (!empty($q)) {
            $conversation = new dialogue_conversation(new dialogue($PAGE->cm, $PAGE->course, $PAGE->activityrecord));
            $results = $conversation->person_search($q);
            if (empty($results)) {
                $people = array(get_string('nomatchingpeople', 'dialogue', $q)=>array(''));
            } else {
                $options = array();
                foreach($results as $person) {
                    $options[$person->id] = fullname($person);
                }
                $people = array(get_string('matchingpeople', 'dialogue', count($options))=>$options);
                if ($mform->getElement('p_select')->getMultiple()) {
                    $selected = optional_param_array('p_select', array(), PARAM_INT);
                } else {
                    $selected = optional_param('p_select', array(), PARAM_INT);
                }
                $this->update_selectgroup('p_select', $people, $selected);
                
            }
        }
        // Clear out query string and selectgroup form data
        if (optional_param('p_clear', false, PARAM_BOOL)) {
            $mform   = $this->_form;
            $pquery = $mform->getElement('p_query');
            $pquery->setValue('');
            $this->update_selectgroup('p_select',
                                      array(get_string('usesearch','dialogue')=>array(''=>'')));

        }
        return true;
    }

    /**
     *
     * @param type $data
     * @param type $files
     * @return type
     */
    public function validation($data, $files) {

        $errors = parent::validation($data, $files);
        if (optional_param_array('p', array(), PARAM_INT)) {
            // js people search
            $people = optional_param_array('p', array(), PARAM_INT);
            if (isset($people['clear'])) {
                $data['people'] = array();
            } else {
                $data['people'] = $people;
            }
        } else if (optional_param('p_select', array(), PARAM_INT)) {
            // nonjs people search select
            $data['people'] = optional_param('p_select', array(), PARAM_INT);
        } else {
            $data['people'] = array();
        }
        if (empty($data['groupinformation'])) {
            if (empty($data['people'])) {
                $errors['participant_autocomplete_field'] = get_string('errornoparticipant', 'dialogue');
            }
        }
        if (empty($data['subject'])) {
            $errors['subject'] = get_string('erroremptysubject', 'dialogue');
        }
        if (!empty($data['includefuturemembers'])) {
            if ($data['cutoffdate'] < time()) {
                $errors['cutoffdate'] = get_string('errorcutoffdateinpast', 'dialogue');
            }
        }

        return $errors;
    }

    // Get everything we need
    public function get_submitted_data() {
        $mform   = $this->_form;
        $data = parent::get_submitted_data();

        if (!empty($data->groupinformation)) {
            $matches = array();
            $subject = $data->groupinformation;
            $pattern = '/(course|group)-(\d.*)/';
            preg_match($pattern, $subject, $matches);
            $bulkopenrule = array();
            $bulkopenrule['type'] = ($matches[1]) ? $matches[1] : '';
            $bulkopenrule['sourceid'] = ($matches[2]) ? $matches[2] : 0;
            if (!empty($data->includefuturemembers)) {
                $bulkopenrule['includefuturemembers'] = true;
                if ($data->cutoffdate) {
                    $bulkopenrule['cutoffdate'] = $data->cutoffdate;
                }
            }
            $data->bulkopenrule = $bulkopenrule;
        } else {
            //$data->bulkopenrule = false;
        }
        unset($data->cutoffdate);
        unset($data->includefuturemembers);
        unset($data->groupinformation);

        if (optional_param_array('p', array(), PARAM_INT)) {
            // js people search
            $people = optional_param_array('p', array(), PARAM_INT);
            if (isset($people['clear'])) {
                $data->people = array();
            } else {
                $data->people = $people;
            }
        } else if (optional_param('p_select', array(), PARAM_INT)) {
            // nonjs people search select
            $data->people = optional_param('p_select', array(), PARAM_INT);
        } else {
            $data->people = array();
        }
        
        return $data;
    }

}

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
 * This page builds a dialogue form.
 *
 * This class extends moodleform overriding the definition() method only
 * @package mod_dialogue
 * @copyright 2013 Troy Williams
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Class mod_dialogue_message_form
 * @package mod_dialogue
 */
class mod_dialogue_message_form extends moodleform {
    /**
     * Form definition.
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function definition() {
        global $PAGE;

        $mform    = $this->_form;
        $cm       = $PAGE->cm;
        $context  = $PAGE->context;

        $mform->addElement('editor', 'body', get_string('message', 'dialogue'), null, self::editor_options());
        $mform->setType('body', PARAM_RAW);

        // Maxattachments = 0 = No attachments at all.
        if (!get_config('dialogue', 'maxattachments') or !empty($PAGE->activityrecord->maxattachments)) {
            $mform->addElement('filemanager', 'attachments[itemid]',
                get_string('attachments', 'dialogue'), null, self::attachment_options());
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
        $actionbuttongroup[] =& $mform->createElement('submit', 'send',
            get_string('send', 'dialogue'), array('class' => 'send-button'));
        $actionbuttongroup[] =& $mform->createElement('submit', 'save',
            get_string('savedraft', 'dialogue'), array('class' => 'savedraft-button'));
        $actionbuttongroup[] =& $mform->createElement('submit', 'cancel',
            get_string('cancel'), array('class' => 'cancel-button'));

        $actionbuttongroup[] =& $mform->createElement('submit', 'trash',
            get_string('trashdraft', 'dialogue'), array('class' => 'trashdraft-button pull-right'));
        $mform->addGroup($actionbuttongroup, 'actionbuttongroup', '', ' ', false);

        $mform->setExpanded('actionssection', true);
    }

    /**
     * Intercept the display of form so can format errors as notifications
     */
    public function display() {
        global $OUTPUT;

        if ($this->_form->_errors) {
            foreach ($this->_form->_errors as $error) {
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
     * @param string $name
     * @param array $options
     * @param array $selected
     * @return array
     */
    public function update_selectgroup($name, $options, $selected=array()) {
        $mform   = $this->_form;
        $element = $mform->getElement($name);
        $element->_optGroups = array(); // Reset the optgroup array().
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
            'trusttext' => true,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL | FILE_EXTERNAL
        );
    }

    /**
     * Returns the options array to use in filemanager for dialogue attachments
     *
     * @return array
     */
    public static function attachment_options() {
        global $CFG, $COURSE, $PAGE;
        $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes,
            $COURSE->maxbytes, $PAGE->activityrecord->maxbytes);
        return array(
            'subdirs' => 0,
            'maxbytes' => $maxbytes,
            'maxfiles' => $PAGE->activityrecord->maxattachments,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL
        );
    }

    /**
     * Validation
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['body']['text'])) {
            $errors['body'] = get_string('erroremptymessage', 'dialogue');
        }

        return $errors;
    }

    /**
     * Get submit action
     * @return null
     */
    public function get_submit_action() {
        $submitactions = array('send', 'save', 'cancel', 'trash');
        foreach ($submitactions as $submitaction) {
            if (optional_param($submitaction, false, PARAM_BOOL)) {
                return $submitaction;
            }
        }
        return null;
    }

}

/**
 * Class mod_dialogue_reply_form
 */
class mod_dialogue_reply_form extends mod_dialogue_message_form {
    /**
     * Definition
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function definition() {
        $mform    = $this->_form;
        $mform->addElement('header', 'messagesection', get_string('reply', 'dialogue'));
        $mform->setExpanded('messagesection', true);
        parent::definition();
    }

    /**
     * Validation
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}

/**
 * Class mod_dialogue_conversation_form
 */
class mod_dialogue_conversation_form extends mod_dialogue_message_form {
    /**
     * Definition
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function definition() {
        global $PAGE, $OUTPUT, $COURSE;

        $mform    = $this->_form;
        $cm       = $PAGE->cm;
        $context  = $PAGE->context;

        $mform->addElement('header', 'openwithsection', get_string('openwith', 'dialogue'));

        $options = [
            'ajax' => 'mod_dialogue/form-user-selector',
            'multiple' => true,
            'courseid' => $COURSE->id,
            'valuehtmlcallback' => function($value) {
                global $OUTPUT;

                $userfieldsapi = \core_user\fields::for_name();
                $allusernames = $userfieldsapi->get_sql('', false, '', '', false)->selects;
                $fields = 'id, ' . $allusernames;
                $user = \core_user::get_user($value, $fields);
                $useroptiondata = [
                    'fullname' => fullname($user),
                ];
                return $OUTPUT->render_from_template('mod_dialogue/form-user-selector-suggestion', $useroptiondata);
            }
        ];
        $mform->addElement('autocomplete', 'useridsselected', get_string('users'), [], $options);

        // Bulk open rule section.
        if (has_capability('mod/dialogue:bulkopenrulecreate', $context)) {
            $groups = array(); // Use for form.
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
            // Make sure have groups, possible group mode but no groups yada yada.
            if ($groups) {
                $mform->addElement('header', 'bulkopenrulessection', get_string('bulkopenrule', 'dialogue'));
                $notify = $OUTPUT->notification(get_string('bulkopenrulenotifymessage', 'dialogue'), 'notifymessage');
                $mform->addElement('html', $notify);
                $mform->addElement('select', 'groupinformation', get_string('group'), $groups);
                $mform->addElement('checkbox', 'includefuturemembers', get_string('includefuturemembers', 'dialogue'));
                $mform->disabledIf('includefuturemembers', 'groupinformation', 'eq', '');
                $mform->addElement('date_selector', 'cutoffdate', get_string('cutoffdate', 'dialogue'));
                $mform->setDefault('cutoffdate', time() + 3600 * 24 * 7);
                $mform->disabledIf('cutoffdate', 'includefuturemembers', 'notchecked');
            }
        }

        $mform->addElement('header', 'messagesection', get_string('message', 'dialogue'));

        $mform->addElement('text', 'subject', get_string('subject', 'dialogue'), array('size' => '100%'));
        $mform->setType('subject', PARAM_TEXT);

        $mform->setExpanded('messagesection', true);

        parent::definition();
    }

    /**
     * Validation
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {

        $errors = parent::validation($data, $files);
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

    /**
     * Get submitted data.
     *
     * @return object|null
     * @throws coding_exception
     */
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
        }
        unset($data->cutoffdate);
        unset($data->includefuturemembers);
        unset($data->groupinformation);

        return $data;
    }
}

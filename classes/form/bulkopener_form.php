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

use mod_dialogue\dialogue;

defined('MOODLE_INTERNAL') || die();



class bulkopener_form extends message_form {
    protected function definition() {
        global $USER, $PAGE;

        $data = $this->_customdata['data'];

        if (empty($data['cmid'])) {
            throw new \moodle_exception('Course module identifier required!');
        }

        $mform = $this->_form;

        $mform->disable_form_change_checker();

        $mform->addElement('header', 'bulkopenrulessection', get_string('bulkopenrule', 'dialogue'));

        $groups = array(); // use for form
        $groups[''] = get_string('select').'...';
        $groups['course-'.$PAGE->course->id] = get_string('allparticipants');
        if (has_capability('moodle/site:accessallgroups', $PAGE->context)) {
            $allowedgroups = groups_get_all_groups($PAGE->course->id, 0);
        } else {
            $allowedgroups = groups_get_all_groups($PAGE->course->id, $USER->id);
        }
        foreach ($allowedgroups as $allowedgroup) {
            $groups['group-'.$allowedgroup->id] = $allowedgroup->name;
        }

        $mform->addElement('select','groupinformation', get_string('group'), $groups);
        $mform->addElement('checkbox', 'includefuturemembers', get_string('includefuturemembers', 'dialogue'));
        $mform->disabledIf('includefuturemembers', 'groupinformation', 'eq', '');
        $mform->addElement('date_selector', 'cutoffdate', get_string('cutoffdate', 'dialogue'));
        $mform->setDefault('cutoffdate', time() + 3600 * 24 * 7);
        $mform->disabledIf('cutoffdate', 'includefuturemembers', 'notchecked');


        if ($data['state'] == dialogue::STATE_BULK_AUTOMATED) {
            $mform->addElement('hidden', 'id');
            $mform->setType('id', PARAM_INT);
            $mform->addElement('header', 'actionssection', get_string('actions', 'dialogue'));
            $actionbuttongroup = array();
            $actionbuttongroup[] =& $mform->createElement('submit', 'save', get_string('save', 'dialogue'), array('class'=>'savedraft-button'));
            $mform->addGroup($actionbuttongroup, 'actionbuttongroup', '', ' ', false);
            $mform->setExpanded('actionssection', true);

        } else {
            $mform->addElement('header', 'messagesection', get_string('message', 'dialogue'));
            $mform->addElement('text', 'subject', get_string('subject', 'dialogue'), array('class'=>'input-xxlarge'));
            $mform->setType('subject', PARAM_TEXT);
            $mform->setExpanded('messagesection', true);
            parent::definition();
        }

    }
    public function definition_after_data() {
        global $CFG, $PAGE;


        parent::definition_after_data();

        $data = $this->_customdata['data'];

        $this->set_data(array('id' => $data['conversationid']));
        $this->set_data(array('subject' => $data['subject']));

        $groupinformation = $data['rule']['type'] . '-' . $data['rule']['sourceid'];
        $this->set_data(array('groupinformation' => $groupinformation));
        $this->set_data(array('includefuturemembers' => $data['rule']['includefuturemembers']));
        $this->set_data(array('cutoffdate' => $data['rule']['cutoffdate']));

    }

    public function get_submitted_data() {
        $data = parent::get_submitted_data();
        if (optional_param('groupinformation', null, PARAM_TEXT)) {
            $data->groupinformation = optional_param('groupinformation', null, PARAM_TEXT);
        }
        return $data;
    }
}
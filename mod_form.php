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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package   mod_dialogue
 * @copyright 2013
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once ($CFG->dirroot.'/mod/dialogue/locallib.php');
require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_dialogue_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $COURSE, $DB;

        $mform    = $this->_form;

        $pluginconfig = get_config('dialogue');

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('dialoguename', 'dialogue'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->add_intro_editor(true, get_string('dialogueintro', 'dialogue'));

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, $pluginconfig->maxbytes);

        $mform->addElement('select', 'maxbytes', get_string('maxattachmentsize', 'dialogue'), $choices);
        $mform->addHelpButton('maxbytes', 'maxattachmentsize', 'dialogue');
        $mform->setDefault('maxbytes', $pluginconfig->maxbytes);

        $choices = range(0, $pluginconfig->maxattachments);
        $choices[0] = get_string('uploadnotallowed');
        $mform->addElement('select', 'maxattachments', get_string('maxattachments', 'dialogue'), $choices);
        $mform->addHelpButton('maxattachments', 'maxattachments', 'dialogue');
        $mform->setDefault('maxattachments', $pluginconfig->maxattachments);

        $mform->addElement('checkbox', 'usecoursegroups', get_string('usecoursegroups', 'dialogue'));
        $mform->addHelpButton('usecoursegroups', 'usecoursegroups', 'dialogue');
        $mform->setDefault('usecoursegroups', 0);

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

     function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return false;
        }
        if (!isset($data->usecoursegroups)) {
            $data->usecoursegroups = 0;
        }
        return $data;
     }
}

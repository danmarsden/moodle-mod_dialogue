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

// load repository lib, will load filelib and formslib
require_once($CFG->dirroot . '/repository/lib.php');

class preference_form extends \moodleform {
    protected function definition() {
        $form = $this->_form;

        $form->addElement('checkbox', 'hideclosed', get_string('hideclosed', 'dialogue'));

        $sortedbyoptions = array();
        foreach (conversations_list::get_sort_options() as $option) {
            $sortedbyoptions[$option] = get_string($option, 'dialogue');
        }

        $form->addElement('select','sortedby', get_string('sortedby', 'dialogue'), $sortedbyoptions);

        $form->addElement('hidden', 'returnurl');
        $form->setType('returnurl', PARAM_URL);

        $this->add_action_buttons();
    }
}
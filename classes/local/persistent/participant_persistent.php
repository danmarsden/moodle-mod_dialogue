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

namespace mod_dialogue\local\persistent;

use core\persistent;

defined('MOODLE_INTERNAL') || die();

class participant_persistent extends persistent {
    
    /** Table name. */
    const TABLE = 'dialogue_participants';
    
    protected static function define_properties() {
        global $CFG, $COURSE;
        return [
            'dialogueid' => [
                'type' => PARAM_INT,
                'default' => 0,
                'description' => 'Foreign key reference to dialogue'
            ],
            'conversationid' => [
                'type' => PARAM_INT,
                'default' => 0,
                'description' => 'Foreign key reference to dialogue conversation'
            ],
            'userid' => [
                'type' => PARAM_INT,
                'default' => 0,
                'description' => 'Foreign key reference to participant user'
            ],
            'isprimaryrecipient' => [
                'type' => PARAM_INT,
                'default' => 0,
                'description' => 'Conversation was opened with this recipient'
            ]
        ];
    }
    
    public function get_participant() {
    
    }
}



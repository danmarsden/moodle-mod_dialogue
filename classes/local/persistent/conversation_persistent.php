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

class conversation_persistent extends persistent {
    
    /** Table name. */
    const TABLE = 'dialogue_conversations';

    protected static function define_properties() {
        global $CFG, $COURSE;
        return [
            'courseid' => [
                'type' => PARAM_INT,
                'default' => $COURSE->id,
                'description' => 'Foreign key reference to the course.'
            ],
            'dialogueid' => [
                'type' => PARAM_INT,
                'default' => 0,
                'description' => 'Foreign key reference to dialogue.'
            ],
            'openingmessageid' => [
                'type' => PARAM_INT,
                'default' => 0,
                'description' => 'Foreign key reference to opening message.'
            ],
            'subject' => [
                'type' => PARAM_RAW,
                'default' => '',
                'description' => 'Subject or topic of dialogue.'
            ],
            'isopen' => [
                'type' => PARAM_INT,
                'default' => 1
            ],
            'replycount' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'hasrules' => [
                'type' => PARAM_INT,
                'default' => 0
            ]
        ];
    }
    
    public function get_recipients() {
        $recipients = [];
        if ($this->raw_get('id') > 0) {
            $recipients = participant_persistent::get_records(
                ['conversationid' => $this->raw_get('id')]
            );
        }
        return $recipients;
    }
}

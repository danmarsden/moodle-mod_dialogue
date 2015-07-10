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

namespace mod_dialogue;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/dialogue/locallib.php'); // TODO - move or remove dialogue_get_user_details()

class participants implements \Iterator {
    protected $freshdata = false;
    protected $dbrecords = false;

    private $array = array();

    /** **/



    public function __construct(conversation $conversation) {
        $this->parent = $conversation;
        $this->check_and_load();
    }

    protected function check_and_load() {
        global $DB;

        if (is_null($this->parent->conversationid)) {
            return false;
        }
        $records = $DB->get_records('dialogue_participants', array('conversationid'=>$this->parent->conversationid));
        if ($records) {
            foreach ($records as $record) {
                $this->array[$record->userid] = dialogue_get_user_details($this->parent->dialogue, $record->userid);
            }
        }
        return reset($this->array);
    }
    public function add($userid) {
        if (empty($userid)) {
            return false;
        }

        if (!isset($this->array[$userid])) {
            $this->array[$userid] = dialogue_get_user_details($this->parent->dialogue, $userid);
            $this->freshdata = true;
        }
        return $this->array[$userid];
    }

    public function exists($userid) {
        if (isset($this->array[$userid])) {
            return true;
        }
        return false;
    }
    public function delete() {}
    public function export() {}
    public function save() {
        global $DB;
        if (is_null($this->parent->conversationid)) {
            throw new coding_exception("Conversation must exist before rule can be saved!");
        }

        if ($this->freshdata) {
            foreach ($this->array as $participant) {
                $record = new \stdClass();
                $record->dialogueid = $this->parent->dialogue->activityrecord->id;
                $record->conversationid = $this->parent->conversationid;
                $record->userid = $participant->id;
                $DB->insert_record('dialogue_participants', $record);
            }
        }
        return true;
    }

    public function rewind() {
        reset($this->array);
    }

    public function current() {
        return current($this->array);
    }

    public function key() {
        return key($this->array);
    }

    public function next() {
        return next($this->array);
    }

    public function valid() {
        return false !== current($this->array);
    }
}

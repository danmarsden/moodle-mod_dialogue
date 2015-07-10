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

/**
 * Library of extra functions for the dialogue module not part of the standard add-on module API set
 * but used by scripts in the mod/dialogue folder
 *
 * @package   mod_dialogue
 * @copyright 2013 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class rule {
    /** **/
    protected $dbrecord = false;
    /** **/
    public $type = null;
    /** **/
    public $sourceid = 0;
    /** **/
    public $includefuturemembers = false;
    /** **/
    public $cutoffdate = 0;

    public function __construct(conversation $conversation) {
        $this->parent = $conversation;
        $this->check_and_load();
    }
    protected function check_and_load() {
        global $DB;

        if (is_null($this->parent->conversationid)) {
            return false;
        }
        $record = $DB->get_record('dialogue_bulk_opener_rules', array('conversationid'=>$this->parent->conversationid));
        if ($record) {
            if ($this->parent->conversationid == $record->conversationid) {
                $this->type                     = $record->type;
                $this->sourceid                 = $record->sourceid;
                $this->includefuturemembers     = $record->includefuturemembers;
                $this->cutoffdate               = $record->cutoffdate;
                $this->dbrecord                 = $record;
            }
        }
        return $record;
    }
    public function delete() {}
    public function is_empty() {
        return (empty($this->type) || empty($this->sourceid));
    }
    public function exists() {
        if (isset($this->dbrecord->id)) {
            return true;
        }
        return false;
    }
    public function export() {
        $return                         = array();
        $return['type']                 = $this->type;
        $return['sourceid']             = $this->sourceid;
        $return['includefuturemembers'] = $this->includefuturemembers;
        $return['cutoffdate']           = $this->cutoffdate;
        return $return;
    }
    public function set(array $data) {
        $this->type                     = $data['type'];
        $this->sourceid                 = $data['sourceid'];
        if (isset($data['includefuturemembers'])) {
            $this->includefuturemembers = $data['includefuturemembers'];
            if (isset($data['cutoffdate'])) {
                $this->cutoffdate       = $data['cutoffdate'];
            }
        }
    }
    public function save() {
        global $DB;
        if (is_null($this->parent->conversationid)) {
            throw new coding_exception("Conversation must exist before rule can be saved!");
        }
        // Don't save to db if no type or sourceid.
        if (!$this->type || !$this->sourceid) {
            return false;
        }
        // Construct record for db insert/update.
        $record                 = (object) $this->export();
        $record->dialogueid     = $this->parent->dialogue->activityrecord->id;
        $record->conversationid = $this->parent->conversationid;
        if ($this->dbrecord) {
            $record->id = $this->dbrecord->id;
            $DB->update_record('dialogue_bulk_opener_rules', $record);
        } else {
            $record->id = $DB->insert_record('dialogue_bulk_opener_rules', $record);
            $this->dbrecord = $record;
        }
        return true;
    }
}

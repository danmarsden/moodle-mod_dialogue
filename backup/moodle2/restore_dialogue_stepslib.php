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
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards -
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_dialogue_activity_task
 */

/**
 * Structure step to restore one dialogue activity
 */
class restore_dialogue_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('dialogue', '/activity/dialogue');
        if ($userinfo) {     
            $paths[] = new restore_path_element('conversation', '/activity/dialogue/conversations/conversation');
            $paths[] = new restore_path_element('entry', '/activity/dialogue/conversations/conversation/entries/entry');
            $paths[] = new restore_path_element('read', '/activity/dialogue/conversations/conversation/read_entries/read_entry');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_dialogue($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('dialogue', $data);
        $this->apply_activity_instance($newitemid);
        
    }

    protected function process_conversation($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->dialogueid = $this->get_new_parentid('dialogue');
        
        $newitemid = $DB->insert_record('dialogue_conversations', $data);
        $this->set_mapping('dialogue_conversation', $oldid, $newitemid);
        
    }

    protected function process_entry($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->dialogueid = $this->get_new_parentid('dialogue');
        $data->conversationid = $this->get_mappingid('dialogue_conversation', $data->conversationid);

        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('dialogue_entries', $data);
        $this->set_mapping('entry', $oldid, $newitemid, true);
        
    }

    protected function process_read($data) {
        global $DB;

        $data = (object)$data;
        
        $data->conversationid = $this->get_mappingid('dialogue_conversation', $data->conversationid);
        $data->entryid = $this->get_mappingid('entry', $data->entryid);

        $newitemid = $DB->insert_record('dialogue_read', $data);
        
    }

    protected function after_execute() {
        // Add entry related files
        $this->add_related_files('mod_dialogue', 'intro', null);
        $this->add_related_files('mod_dialogue', 'entry', 'entry');
        $this->add_related_files('mod_dialogue', 'attachment', 'entry');
    }
}

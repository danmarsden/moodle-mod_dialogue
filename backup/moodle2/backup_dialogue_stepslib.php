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
 * @copyright 2010 -
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Define all the backup steps that will be used by the backup_dialogue_activity_task
 */
class backup_dialogue_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');


        // Define each element separated
        $dialogue = new backup_nested_element('dialogue', array('id'), array(
            'course', 'deleteafter', 'dialoguetype', 'multipleconversations',
            'maildefault', 'timemodified', 'name', 'intro', 'introformat', 'edittime'));
        
        $conversations = new backup_nested_element('conversations');
        $conversation = new backup_nested_element('conversation', array('id'), array(
            'dialogueid', 'userid', 'recipientid', 'lastid', 'lastrecipientid', 'timemodified',
            'closed', 'seenon', 'ctype', 'format', 'subject', 'groupid', 'grouping'));
        
        $entries = new backup_nested_element('entries');
        $entry = new backup_nested_element('entry', array('id'), array(
            'dialogueid', 'conversationid', 'userid', 'recipientid', 'timecreated',
            'timemodified', 'mailed', 'text', 'format', 'trust', 'attachment'));
        
        $readentries = new backup_nested_element('read_entries');
        $read = new backup_nested_element('read_entry', array('id'), array(
        	'entryid', 'userid', 'firstread', 'lastread', 'conversationid'));

        // Build the tree
        $dialogue->add_child($conversations);
        $conversations->add_child($conversation);
        $conversation->add_child($entries);
        $entries->add_child($entry);
        $conversation->add_child($readentries);
        $readentries->add_child($read);


        
        // Define sources
        $dialogue->set_source_table('dialogue', array('id' => backup::VAR_ACTIVITYID));
        // All these source definitions only happen if we are including user info
        if ($userinfo) {
            $conversation->set_source_table('dialogue_conversations', array('dialogueid' => backup::VAR_PARENTID));
            $entry->set_source_table('dialogue_entries', array('conversationid' => backup::VAR_PARENTID));
            $read->set_source_table('dialogue_read', array('conversationid' => backup::VAR_PARENTID));
        }

        // Define id annotations
        $conversation->annotate_ids('user', 'userid');
        $conversation->annotate_ids('user', 'recipientid');
        $entry->annotate_ids('user', 'userid');
        $entry->annotate_ids('user', 'recipientid');
        $read->annotate_ids('user', 'userid');
        
        // Define file annotations
        $dialogue->annotate_files('mod_dialogue', 'intro', null); // This file area hasn't itemid
        $entry->annotate_files('mod_dialogue', 'entry', 'id');
        $entry->annotate_files('mod_dialogue', 'attachment', 'id');
                
        // Return the root element, wrapped into standard activity structure
        return $this->prepare_activity_structure($dialogue);
    }
}

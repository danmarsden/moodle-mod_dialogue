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
        $dialogue = new backup_nested_element('dialogue', array('id'),
                                              array('course',
                                                    'name',
                                                    'intro',
                                                    'introformat',
                                                    'maxattachments',
                                                    'maxbytes',
                                                    'usecoursegroups',
                                                    'notifications',
                                                    'notificationcontent',
                                                    'multipleconversations',
                                                    'timemodified'));
  
        $conversations = new backup_nested_element('conversations');
        
        $conversation = new backup_nested_element('conversation', array('id'),
                                                  array('course',
                                                        'dialogueid',
                                                        'subject'));
        
        $participants = new backup_nested_element('participants');
        
        $participant = new backup_nested_element('participant', array('id'),
                                                  array('dialogueid',
                                                        'conversationid',
                                                        'userid'));

        $bulkopenerrules = new backup_nested_element('bulkopenerrules');

        $bulkopenerrule = new backup_nested_element('bulkopenerrule', array('id'),
                                                     array('dialogueid',
                                                           'conversationid',
                                                           'type',
                                                           'sourceid',
                                                           'includefuturemembers',
                                                           'cutoffdate',
                                                           'lastrun'));
        $messages = new backup_nested_element('messages');
        
        $message = new backup_nested_element('message', array('id'),
                                              array('dialogueid',
                                                    'conversationid',
                                                    'conversationindex',
                                                    'authorid',
                                                    'body',
                                                    'bodyformat',
                                                    'bodytrust',
                                                    'attachments',
                                                    'state',
                                                    'timecreated',
                                                    'timemodified'));
        
        $flags = new backup_nested_element('flags');

        $flag = new backup_nested_element('flag', array('id'),
                                           array('dialogueid',
                                                 'conversationid',
                                                 'messageid',
                                                 'userid',
                                                 'flag',
                                                 'timemodified'));

        // Build the tree
        $dialogue->add_child($conversations);
        $conversations->add_child($conversation);
        
        $conversation->add_child($participants);
        $participants->add_child($participant);
        
        $conversation->add_child($bulkopenerrules);
        $bulkopenerrules->add_child($bulkopenerrule);
        
        $conversation->add_child($messages);
        $messages->add_child($message);

        $conversation->add_child($flags);
        $flags->add_child($flag);
        
        // Define sources
        $dialogue->set_source_table('dialogue', array('id' => backup::VAR_ACTIVITYID));
        // All these source definitions only happen if we are including user info
        if ($userinfo) {
            $conversation->set_source_table('dialogue_conversations', array('dialogueid' => backup::VAR_PARENTID));
            $participant->set_source_table('dialogue_participants', array('conversationid' => backup::VAR_PARENTID));
            // Leaving out bulk open rule as unsure how to annotate as could be courseid or groupid
            //$bulkopenerrule->set_source_table('dialogue_bulk_opener_rules', array('conversationid' => backup::VAR_PARENTID));
            $message->set_source_table('dialogue_messages', array('conversationid' => backup::VAR_PARENTID));
            $flag->set_source_table('dialogue_flags', array('conversationid' => backup::VAR_PARENTID));
            
        }

        // Define id annotations
        $participant->annotate_ids('user', 'userid');
        $message->annotate_ids('user', 'authorid');
        $flag->annotate_ids('user', 'userid');
        
        // Define file annotations
        $dialogue->annotate_files('mod_dialogue', 'intro', null); // This file area hasn't itemid
        $message->annotate_files('mod_dialogue', 'message', 'id');
        $message->annotate_files('mod_dialogue', 'attachment', 'id');
                
        // Return the root element, wrapped into standard activity structure
        return $this->prepare_activity_structure($dialogue);
    }
}

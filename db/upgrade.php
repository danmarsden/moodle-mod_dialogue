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

function xmldb_dialogue_upgrade($oldversion=0) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    /**
     * Moodle v2.9.0 release upgrade line.
     */

    if ($oldversion < 2014120309) {
        // Get the conversation table.
        $table = new xmldb_table('dialogue_conversations');

        // New field owner.
        $field = new xmldb_field('owner', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // New field instigator.
        $field = new xmldb_field('instigator', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // New field recipient.
        $field = new xmldb_field('recipient', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // New field messagecount.
        $field = new xmldb_field('messagecount', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // ** Gather and update conversation record data ** //
        $rs = $DB->get_records('dialogue_conversations', array(), 'id DESC');
        $progessbar = new progress_bar('dialogueupgrade', 500, true); $numberdone = 0; $total = count($rs);
        foreach ($rs as $conversation) {
            $params = array('conversationid'=>$conversation->id);
            list($insql, $inparams) = $DB->get_in_or_equal(\mod_dialogue\dialogue::get_unread_states(), SQL_PARAMS_NAMED, 'un');
            $params = array_merge($params, $inparams);
            $messages = $DB->get_records_select('dialogue_messages', "conversationid = :conversationid AND state $insql",
                $params, 'conversationindex');
            $messagecount = count($messages);
            if (!empty($messagecount)) {
                $firstmessage = array_shift($messages);
                $owner = $firstmessage->authorid;
                $instigator = $firstmessage->authorid;
                // Search for recipient.
                if ($messagecount == 1) {
                    $recipient = $firstmessage->authorid;
                } else {
                    $recipient = null;
                    while (!empty($messages)) {
                        $nextmessage = array_shift($messages);
                        if ($firstmessage->authorid != $nextmessage->authorid) {
                            $recipient = $nextmessage->authorid;
                            break;
                        }
                    }
                    if (is_null($recipient)) {
                        $recipient = (int) $DB->get_field_select('dialogue_participants',
                            'userid', "conversationid = :conversationid AND userid != :userid",
                            array('conversationid'=>$conversation->id, 'userid'=>$firstmessage->authorid));
                    }
                }
                $conversation->owner = $owner;
                $conversation->instigator = $instigator;
                $conversation->recipient = $recipient;
                $conversation->messagecount = $messagecount;
                // Add new data to conversation record.
                $DB->update_record('dialogue_conversations', $conversation);

                $numberdone++;
                $progessbar->update($numberdone, $total, "Upgrading conversation data - {$numberdone}/{$total}");
            }

        }
        upgrade_mod_savepoint(true, 2014120309, 'dialogue');
    }

    if ($oldversion < 2014120320) {
        // Get the conversation table.
        $table = new xmldb_table('dialogue_conversations');
        // New field openrule.
        $field = new xmldb_field('openrule', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $sql = "SELECT dm.id AS messageid, dm.conversationid, dm.state, dr.cutoffdate, dr.lastrun
                  FROM {dialogue_messages} dm
                  JOIN {dialogue_bulk_opener_rules} dr ON dr.conversationid = dm.conversationid";
        $rs = $DB->get_records_sql($sql);
        foreach ($rs as $record) {
            $DB->set_field('dialogue_conversations', 'openrule', 1, array('id'=>$record->conversationid));
            if ($record->state == \mod_dialogue\dialogue::STATE_BULK_AUTOMATED) {
                $lastrun = $record->lastrun;
                $cutoffdate = $record->cutoffdate;
                $state = \mod_dialogue\dialogue::STATE_OPEN;
                if ((!$cutoffdate and $lastrun) or ($cutoffdate > time())) {
                    $state = \mod_dialogue\dialogue::STATE_CLOSED;
                }
                $DB->set_field('dialogue_messages', 'state', $state, array('id'=>$record->messageid));
            }
        }
        upgrade_mod_savepoint(true, 2014120320, 'dialogue');
    }

    if ($oldversion < 2014120321) {
        $table = new xmldb_table('dialogue');
        // New field opener limit field.
        $field = new xmldb_field('oneperperson', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2014120321, 'dialogue');
    }

    return true;
}

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

function xmldb_dialogue_upgrade($oldversion = 0) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    /**
     * Moodle v3.5.0 release upgrade line.
     */
    
    if ($oldversion < 2018051700) {
        
        // Dialogue table modifications.
        $table = new xmldb_table('dialogue');
        
        // Define field usermodified to be added to dialogue.
        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field usermodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field timecreated to be added to dialogue.
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field timecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // TODO - Update records.
    
        // Dialogue conversations table modifications.
        $table = new xmldb_table('dialogue_conversations');
    
        // Define field openingmessageid to be added to dialogue_conversations.
        $field = new xmldb_field('openingmessageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field openingmessageid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field isopen to be added to dialogue_conversations.
        $field = new xmldb_field('isopen', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        // Conditionally launch add field isopen.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field replycount to be added to dialogue_conversations.
        $field = new xmldb_field('replycount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field replycount.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field hasrules to be added to dialogue_conversations.
        $field = new xmldb_field('hasrules', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field hasrules.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field usermodified to be added to dialogue_conversations.
        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field usermodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field timecreated to be added to dialogue_conversations.
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field timecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    
        // TODO - Update records.
    
        // Dialogue messages table modifications.
        $table = new xmldb_table('dialogue_messages');
        
        // Rename field attachments on table dialogue_messages to hasattachments.
        $field = new xmldb_field('attachments', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        // Launch rename field hasattachments.
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'hasattachments');
        }
        // Define field isdraft to be added to dialogue_messages.
        $field = new xmldb_field('isdraft', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        // Conditionally launch add field isdraft.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field usermodified to be added to dialogue_messages.
        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field usermodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field timecreated to be added to dialogue_messages.
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field timecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    
        // TODO - Update records.
        // TODO - Remove state column.
    
        // Dialogue participants table modifications.
        $table = new xmldb_table('dialogue_participants');
        
        // Define field isprimaryrecipient to be added to dialogue_participants.
        $field = new xmldb_field('isprimaryrecipient', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field isprimaryrecipient.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field usermodified to be added to dialogue_participants.
        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field usermodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field timecreated to be added to dialogue_participants.
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field timecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field timemodified to be added to dialogue_participants.
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    
        // TODO - Update records.
        
        // Dialogue flags table modifications.
        $table = new xmldb_table('dialogue_flags');
        
        // Define field usermodified to be added to dialogue_flags.
        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'flag');
        // Conditionally launch add field usermodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Define field timecreated to be added to dialogue_flags.
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'usermodified');
        // Conditionally launch add field timecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    
        // TODO - Update records.
        
        // Savepoint reached.
        upgrade_mod_savepoint(true, 2018051700, 'dialogue');
    }
    
    return true;
}

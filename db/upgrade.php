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
     * Moodle v2.5.0 release upgrade line.
     */

    // Migration step 1 - rename old tables
    if ($oldversion < 2013050101) {
        // Archive off existing dialogue tables, so can upgrade dialogues selectively.
        $tables = array('dialogue','dialogue_conversations', 'dialogue_entries', 'dialogue_read');
        foreach($tables as $table) {
            $tablearchive = $table . '_old';
            if ($dbman->table_exists($table) and !$dbman->table_exists($tablearchive)) {
                $dbman->rename_table(new xmldb_table($table), $tablearchive);
            }
            // drop old indexes? $dbman->drop_index($xmldb_table, $xmldb_index)
        }
        echo $OUTPUT->notification('Renaming old dialogue module tables', 'notifysuccess');
        upgrade_mod_savepoint(true, 2013050101, 'dialogue');
    }
    // Migration step 2 - build new tables
    if ($oldversion < 2013050102) {
        require_once($CFG->dirroot . '/mod/dialogue/upgrade/upgradelib.php');
        dialogue_upgrade_prepare_new_tables();
        echo $OUTPUT->notification('Preparing new dialogue module tables', 'notifysuccess');
        upgrade_mod_savepoint(true, 2013050102, 'dialogue');
    }

    // Migration step 3 - copy old dialogue instances to new table
    if ($oldversion < 2013050103) {
        require_once($CFG->dirroot . '/mod/dialogue/upgrade/upgradelib.php');
        // copy old dialogue data to new table
        $rs = $DB->get_recordset('dialogue_old');
        if ($rs->valid()) {
            foreach ($rs as $olddialogue) {
                
                $dialogue                           = new stdClass();
                $dialogue->id                       = $olddialogue->id;
                $dialogue->course                   = $olddialogue->course;
                $dialogue->name                     = $olddialogue->name;
                $dialogue->intro                    = $olddialogue->intro;
                $dialogue->introformat              = $olddialogue->introformat;
                $dialogue->maxattachments           = 5;
                $dialogue->maxbytes                 = 10485760; // 10MB
                $dialogue->notifications            = 1;
                $dialogue->notificationcontent      = 0;
                $dialogue->multipleconversations    = $olddialogue->multipleconversations;
                $dialogue->timemodified             = $olddialogue->timemodified;
                
                $DB->insert_record_raw('dialogue', $dialogue);
            }
            // !Important reset sequence
            $dbman->reset_sequence(new xmldb_table('dialogue'));
        }
        $rs->close();
        echo $OUTPUT->notification('Old dialogue instance data copied to new table', 'notifysuccess');
        upgrade_mod_savepoint(true, 2013050103, 'dialogue');
    }
    
    // Migration step 4 - set upgrade flag
    if ($oldversion < 2013050104) {
        set_config('upgraderequired', 1, 'dialogue');
        echo $OUTPUT->notification('Set the upgrade required flag', 'notifysuccess');
        upgrade_mod_savepoint(true, 2013050104, 'dialogue');
    }
    return true;
}
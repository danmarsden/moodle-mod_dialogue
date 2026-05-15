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
 * Dialogue upgrade scripts.
 *
 * @package mod_dialogue
 * @copyright 2021 Dan Marsden
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Dialogue upgrade script.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_dialogue_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024120900) {
        // Define index dialogueid (not unique) to be added to dialogue_messages.
        $table = new xmldb_table('dialogue_messages');
        $index = new xmldb_index('dialogueid', XMLDB_INDEX_NOTUNIQUE, ['dialogueid']);

        // Conditionally launch add index userid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // savepoint reached.
        upgrade_mod_savepoint(true, 2024120900, 'dialogue');
    }

    if ($oldversion >= 2013101501 && $oldversion < 2026051500) {
        // Migrate the old property usecoursegroups to $cm->groupmode = SEPARATEGROUPS or NOGROUPS.
        require_once($CFG->dirroot . '/course/lib.php');

        $moduleid = $DB->get_field('modules', 'id', ['name' => 'dialogue']);

        if ($moduleid) {
            $sql = "SELECT cm.id, d.usecoursegroups
                      FROM {course_modules} cm
                      JOIN {dialogue} d ON d.id = cm.instance
                     WHERE cm.module = :moduleid";
            $recordset = $DB->get_recordset_sql($sql, ['moduleid' => $moduleid]);

            foreach ($recordset as $record) {
                $groupmode = $record->usecoursegroups ? SEPARATEGROUPS : NOGROUPS;
                set_coursemodule_groupmode($record->id, $groupmode);
            }

            $recordset->close();
        }

        upgrade_mod_savepoint(true, 2026051500, 'dialogue');
    }

    return true;
}

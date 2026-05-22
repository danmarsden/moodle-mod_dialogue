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
    global $DB;

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

    return true;
}

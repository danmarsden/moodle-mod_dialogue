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
 * Clean up orphan records.
 *
 * @package   mod_dialogue
 * @copyright 2013 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/mod/dialogue/lib.php');
require_once($CFG->dirroot.'/mod/dialogue/locallib.php');

// We may need a lot of memory here.
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
    array(
        'non-interactive'   => false,
        'help'              => false
    ),
    array(
        'h' => 'help'
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "Dialogue module: clean up orphaned messages

Please note you must execute this script with the same uid as apache!

Options:
--non-interactive     No interactive questions or confirmations
-h, --help            Print out this help

Example:
\$sudo -u apache /usr/bin/php mod/dialogue/cli/cleanuporphans.php
";

    echo $help;
    die;
}

$interactive = empty($options['non-interactive']);

if ($interactive) {
    $prompt = "Dialogue module: clean up orphaned messages? type y (means yes) or n (means no)";
    $input = cli_input($prompt, '', array('n', 'y'));
    if ($input == 'n') {
        mtrace('Bye bye');
        exit;
    }
}

// Start output log.
$starttime = microtime();
mtrace("Server Time: ".date('r')."\n");

$sql = "SELECT dm.*
         FROM {dialogue_messages} dm
         WHERE NOT EXISTS (SELECT dc.id
                          FROM {dialogue_conversations} dc
                          WHERE dc.id = dm.conversationid)
         ORDER BY dm.conversationid, dm.conversationindex";

$rs = $DB->get_recordset_sql($sql, array());
if ($rs->valid()) {
    // Get file storage.
    $fs = get_file_storage();

    foreach ($rs as $record) {
        $cm = get_coursemodule_from_instance('dialogue', $record->dialogueid);
        if (! $cm) {
            mtrace('Course module does not exist! Weird!');
            continue;
        }
        $context = context_module::instance($cm->id, MUST_EXIST);
        // Delete message and attachment files for message.
        $fs->delete_area_files($context->id, false, false, $record->id);
        // Delete message.
        $DB->delete_records('dialogue_messages', array('id' => $record->id));

        mtrace("Message#{$record->id} has been cleaned out.");
    }
}
$rs->close();

$predbqueries = null;
$predbqueries = $DB->perf_get_queries();
$pretime      = microtime(1);
if (isset($predbqueries)) {
    mtrace("... used " . ($DB->perf_get_queries() - $predbqueries) . " dbqueries");
    mtrace("... used " . (microtime(1) - $pretime) . " seconds");
}

gc_collect_cycles();
mtrace('Completed at ' . date('H:i:s') . '. Memory used ' . display_size(memory_get_usage()) . '.');
$difftime = microtime_diff($starttime, microtime());
mtrace("Execution took ".$difftime." seconds");

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

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php');         // cli only functions
require_once($CFG->libdir.'/cronlib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/mod/dialogue/lib.php');
require_once($CFG->dirroot.'/mod/dialogue/locallib.php');

// we may need a lot of memory here
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

// now get cli options
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
    $help =
"Command line Dialogue module cron.

Please note you must execute this script with the same uid as apache!

Options:
--non-interactive     No interactive questions or confirmations
-h, --help            Print out this help

Example:
\$sudo -u www-data /usr/bin/php mod/dialogue/cli/cron.php
"; //TODO: localize - to be translated later when everything is finished

    echo $help;
    die;
}

// Force a debugging mode regardless the settings in the site administration
//@error_reporting(1023);  // NOT FOR PRODUCTION SERVERS!
//@ini_set('display_errors', '1'); // NOT FOR PRODUCTION SERVERS!
//$CFG->debug = 38911;  // DEBUG_DEVELOPER // NOT FOR PRODUCTION SERVERS!
//$CFG->debugdisplay = true;   // NOT FOR PRODUCTION SERVERS!

$interactive = empty($options['non-interactive']);

if ($interactive) {
    $prompt = "Run Dialogue module cron? type y (means yes) or n (means no)";
    $input = cli_input($prompt, '', array('n', 'y'));
    if ($input == 'n') {
        mtrace('exited');
        exit;
    }
}

set_time_limit(0);
$starttime = microtime();

// Increase memory limit
raise_memory_limit(MEMORY_EXTRA);

// Emulate normal session - we use admin accoutn by default
cron_setup_user();

// Start output log
$timenow  = time();
mtrace("Server Time: ".date('r', $timenow)."\n\n");
mtrace("Processing Dialogue module cron ...", '');
cron_trace_time_and_memory();
$pre_dbqueries = null;
$pre_dbqueries = $DB->perf_get_queries();
$pre_time      = microtime(1);
// Run the cron
dialogue_cron();
if (isset($pre_dbqueries)) {
    mtrace("... used " . ($DB->perf_get_queries() - $pre_dbqueries) . " dbqueries");
    mtrace("... used " . (microtime(1) - $pre_time) . " seconds");
}
// Reset possible changes by modules to time_limit. MDL-11597
@set_time_limit(0);
mtrace("done.");

gc_collect_cycles();
mtrace('Cron completed at ' . date('H:i:s') . '. Memory used ' . display_size(memory_get_usage()) . '.');
$difftime = microtime_diff($starttime, microtime());
mtrace("Execution took ".$difftime." seconds");
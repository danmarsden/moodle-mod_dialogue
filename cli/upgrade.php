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
define('CACHE_DISABLE_ALL', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php');         // cli only functions
require_once($CFG->dirroot.'/mod/dialogue/upgrade/upgradelib.php');
require_once($CFG->dirroot.'/mod/dialogue/locallib.php');

// we may need a lot of memory here
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

// now get cli options
list($options, $unrecognized) = cli_get_params(
    array(
        'cmid'              => false,
        'courseid'          => false,
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
"Command line Dialogue upgrade.
If no options passed it will upgrade every dialogue on the system.

Please note you must execute this script with the same uid as apache!

Options:
--cmid                Upgrade dialogue with course module id
--courseid            Upgrade all dialogues in course with id
-h, --help            Print out this help

Example:
\$sudo -u www-data /usr/bin/php admin/cli/upgrade.php
"; //TODO: localize - to be translated later when everything is finished

    echo $help;
    die;
}

// Force a debugging mode regardless the settings in the site administration
@error_reporting(1023);  // NOT FOR PRODUCTION SERVERS!
@ini_set('display_errors', '1'); // NOT FOR PRODUCTION SERVERS!
$CFG->debug = 38911;  // DEBUG_DEVELOPER // NOT FOR PRODUCTION SERVERS!
$CFG->debugdisplay = true;   // NOT FOR PRODUCTION SERVERS!

$cmid         = $options['cmid'];
$courseid     = $options['courseid'];

if (dialogue_upgrade_is_complete()) {
    mtrace('Dialogues have been upgrade!');
    exit;
}

if ($cmid) {
    $cm = dialogue_upgrade_get_course_module_by_instance($cmid);
    if (!$cm) {
        exit("Dialogue course module not found!\n");
    }
    $prompt = "Upgrade dialogue '{$cm->dialoguename}', proceed? type y (means yes) or n (means no)";
    $input = cli_input($prompt, '', array('n', 'y'));
    if ($input == 'y') {
        $result = dialogue_upgrade_course_module($cm);
        if (!$result) {
            exit("Upgrade of dialogue'{$cm->dialoguename}' failed!\n");
        }
        // cleanup
        dialogue_upgrade_cleanup();
        mtrace("Upgrade of dialogue '{$cm->dialoguename}' was successful!");
    }
    exit;
}

if ($courseid) {
    $cms = dialogue_upgrade_get_course_modules_by_course($courseid);
    if (!$cms) {
        exit("No dialogue course modules found!\n");
    }
    $coursename = $DB->get_field('course', 'shortname', array('id' =>$courseid));
    $prompt = "Upgrade all dialogues in course '{$coursename}', proceed? type y (means yes) or n (means no)";
    $input = cli_input($prompt, '', array('n', 'y'));
    if ($input == 'y') {
        foreach ($cms as $cm) {
            $result = dialogue_upgrade_course_module($cm);
            if (!$result) {
                exit("Upgrade of dialogue'{$cm->dialoguename}' failed!\n");
            }
            mtrace("Upgrade of dialogue '{$cm->dialoguename}' was successful!");
        }
        // cleanup
        dialogue_upgrade_cleanup();
        mtrace("Upgrade all dialogues in course '{$coursename}' were successful!");
    }
    exit;
}
$prompt = "Upgrade ALL dialogues in SITE: '{$SITE->shortname}', proceed? type y (means yes) or n (means no)";
$input = cli_input($prompt, '', array('n', 'y'));
if ($input == 'y') {
    $starttime = microtime();
    $timenow  = time();
    mtrace("Started at: ".date('r', $timenow)."\n");

    $processed  = 0;
    $failure    = 0;
    $success    = 0;
    $limit      = 5000;
    $matches    = 0;
    $rs = dialogue_upgrade_get_list(0, $limit, $matches);
    while ($rs) {
        $processed++;
        $record = array_shift($rs);
        $cm = get_coursemodule_from_id('dialogue', $record->id);
        if (!$cm) {
            mtrace("#{$record->id} dialogue course module not found!");
            continue;
        }
        $result = dialogue_upgrade_course_module($cm);
        if (!$result) {
            mtrace("Upgrade of dialogue'{$record->dialoguename}' failed!");
            $failure++;
            continue;
        }
        mtrace("Upgrade of dialogue '{$record->dialoguename}' was successful!");
        $success++;
    }
    
    gc_collect_cycles();
    // cleanup
    dialogue_upgrade_cleanup();

    mtrace('Processed '. $processed . ' upgraded '. $success. ' failed ' . $failure);
    if ($matches > $limit) {
        mtrace('Found more than were processed, please run again :)');
    }
    mtrace('Completed at ' . date('H:i:s') . '. Memory used ' . display_size(memory_get_usage()) . '.');
    $difftime = microtime_diff($starttime, microtime());
    mtrace("Execution took ".$difftime." seconds");
    
}
exit;


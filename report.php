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
 * Searchable conversation report using core_reportbuilder.
 *
 * Renders a system report with filters for participant name, username,
 * subject, message content, attachments, status and date.
 *
 * @package   mod_dialogue
 * @copyright 2025 Andrew Rowatt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_reportbuilder\system_report_factory;
use mod_dialogue\reportbuilder\local\systemreports\messages as messages_report;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);

$cm             = get_coursemodule_from_id('dialogue', $id, 0, false, MUST_EXIST);
$activityrecord = $DB->get_record('dialogue', ['id' => $cm->instance], '*', MUST_EXIST);
$course         = $DB->get_record('course', ['id' => $activityrecord->course], '*', MUST_EXIST);
$context        = context_module::instance($cm->id);

require_login($course, false, $cm);
// Row-level filtering in the system report restricts non-privileged
// users to conversations they participate in.
require_capability('mod/dialogue:searchmessages', $context);

// Trigger course_module_viewed so dialogue access shows up in logs/reports,
// matching the behaviour of view.php and conversation.php. The 'action' subkey
// distinguishes searchreport hits from regular module views.
$event = \mod_dialogue\event\course_module_viewed::create([
    'objectid' => $activityrecord->id,
    'context'  => $context,
    'other'    => ['action' => 'searchreport'],
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('dialogue', $activityrecord);
$event->trigger();

$pageurl = new moodle_url('/mod/dialogue/report.php', ['id' => $cm->id]);

$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($activityrecord->name));
$PAGE->set_heading(format_string($course->fullname));

$dialogue = new \mod_dialogue\dialogue($cm, $course, $activityrecord);
$renderer = $PAGE->get_renderer('mod_dialogue');

$report = system_report_factory::create(
    messages_report::class,
    $context,
    'mod_dialogue',
    '',
    0,
    [
        'dialogueid' => (int) $activityrecord->id,
        'cmid'       => (int) $cm->id,
    ]
);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($activityrecord->name));

echo $renderer->tab_navigation($dialogue);

echo $report->output();

echo $OUTPUT->footer();

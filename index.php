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

require(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');

$id = required_param('id', PARAM_INT); // Course identifier.

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);

require_course_login($course);

$url = new moodle_url('/mod/dialogue/index.php', array('id' => $id));

// Strings.
$strdialogue        = get_string('modulename', 'dialogue');
$strdialogues       = get_string('modulenameplural', 'dialogue');
$strdescription     = get_string('description');
$strconversations   = get_string('conversations', 'dialogue');

$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title("$course->shortname: $strdialogues");
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strdialogues);

echo $OUTPUT->header();

// Get all the appropriate data.
if (!$dialogues = get_all_instances_in_course('dialogue', $course)) {
    notice(get_string('thereareno', 'moodle', $strdialogues), "$CFG->wwwroot/course/view.php?id=$course->id");
    die;
}

$table = new html_table();
$table->head  = array ($strdialogue, $strdescription, $strconversations);
$table->align = array ('left', 'left', 'center');
$modinfo = get_fast_modinfo($course);
foreach ($dialogues as $dialogue) {
    $cm = $modinfo->get_cm($dialogue->coursemodule);
    if (!$cm->uservisible ) {
        continue;
    }
    $attrs = array();
    if (!$cm->visible) {
        $attrs['class'] ='dimmed';
    }
    $dialoguename = format_string($dialogue->name, true);
    $dialogueintro = shorten_text(format_module_intro('dialogue', $dialogue, $dialogue->coursemodule), 300);
    $dialoguelink = html_writer::link(
        new moodle_url('/mod/dialogue/view.php', array('id'=>$dialogue->coursemodule)),
        $dialoguename,
        $attrs);
    $conversationcount = dialogue_get_conversations_count($cm);
    $row = array ($dialoguelink, $dialogueintro, $conversationcount);
    $table->data[] = $row;
}
echo html_writer::table($table);
echo $OUTPUT->footer();

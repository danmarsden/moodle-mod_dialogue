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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('lib.php');
require_once('locallib.php');

$id = required_param('id', PARAM_INT);

if (! $course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourseid');
}

require_course_login($course);

$url = new moodle_url('/mod/dialogue/index.php', array('id' => $id));

$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$coursecontext = context_course::instance($course->id);

\mod_dialogue\event\course_module_instance_list_viewed::create_from_course($course)->trigger();

$strdialogue        = get_string('modulename', 'dialogue');
$strdialogues       = get_string('modulenameplural', 'dialogue');
$strdescription     = get_string('description');
$strconversations   = get_string('conversations', 'dialogue');


$dialogues = $DB->get_records('dialogue', array('course' => $course->id));
$modinfo = get_fast_modinfo($course);
if (!isset($modinfo->instances['dialogue'])) {
    $modinfo->instances['dialogue'] = array();
}

/// Setup the page.
$PAGE->navbar->add($strdialogues);
$PAGE->set_title("$course->shortname: $strdialogues");
$PAGE->set_heading($course->fullname);

if (!$dialogues) {
    notice('There are no dialogues', "course/view.php?id=$course->id");
    die;
} else {
    $states = array(\mod_dialogue\dialogue::STATE_OPEN, mod_dialogue\dialogue::STATE_CLOSED);
    list($insql, $inparams) = $DB->get_in_or_equal($states, SQL_PARAMS_NAMED);
    $params = array('courseid' => $course->id) + $inparams;
    
    $sql = "SELECT dc.dialogueid, COUNT(dc.dialogueid) AS count
              FROM {dialogue_conversations} dc
              JOIN {dialogue_messages} dm ON dm.conversationid = dc.id
             WHERE dc.course = :courseid
               AND dm.conversationindex = 1
               AND dm.state $insql
           GROUP BY dc.dialogueid";

    $counts = $DB->get_records_sql($sql, $params);
    
    $table = new html_table();
    $table->head  = array ($strdialogue, $strdescription, $strconversations);
    $table->align = array ('left', 'left', 'center');

    foreach ($modinfo->instances['dialogue'] as $dialogueid => $cm) {
        if (!$cm->uservisible or !isset($dialogues[$dialogueid])) {
            continue;
        }
        
        if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
            continue;   // Shouldn't happen.
        }

        $dialogue = $dialogues[$dialogueid];
        $dialogue->conversationcount = dialogue_get_conversations_count($cm);
        
        $dialoguename = format_string($dialogue->name, true);
        $dialogueintro = shorten_text(format_module_intro('dialogue', $dialogue, $cm->id), 300);
        if ($cm->visible) {
            $style = '';
        } else {
            $style = 'class="dimmed"';
        }
        $dialoguelink = "<a href=\"view.php?id=$cm->id\" $style>".$dialoguename."</a>";
        $row = array ($dialoguelink, $dialogueintro, $dialogue->conversationcount);
        $table->data[] = $row;
    }

}
// Output page.
echo $OUTPUT->header();
echo $OUTPUT->heading($strdialogues);
echo html_writer::table($table);
echo $OUTPUT->footer();


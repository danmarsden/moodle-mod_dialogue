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

require_once(dirname(__FILE__).'/../../../config.php');
require_once($CFG->dirroot . '/mod/dialogue/locallib.php');

$id             = required_param('id', PARAM_INT);

$conversationrecord = $DB->get_record('dialogue_conversations', array('id' => $id), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('dialogue', $conversationrecord->dialogueid);
if (! $cm) {
    print_error('invalidcoursemodule');
}
$activityrecord = $DB->get_record('dialogue', array('id' => $cm->instance));
if (! $activityrecord) {
    print_error('invalidid', 'dialogue');
}
$course = $DB->get_record('course', array('id' => $activityrecord->course));
if (! $course) {
    print_error('coursemisconf');
}
$context = \context_module::instance($cm->id, MUST_EXIST);

require_login($course, false, $cm);

$pageurl = new moodle_url('/mod/dialogue/conversation/edit.php');
$pageurl->param('id', $cm->id);

//$PAGE->set_pagetype('mod-dialogue'); sets css class
$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);

$returnurl    = new moodle_url('/mod/dialogue/view.php', array('id' => $cm->id));
$draftsurl    = new moodle_url('/mod/dialogue/drafts.php', array('id' => $cm->id));

$dialogue = new \mod_dialogue\dialogue($cm, $course, $activityrecord);
$conversation = new \mod_dialogue\conversation($dialogue, $conversationrecord->id); // Existing conversation.


$isparticipant = $conversation->get_participants()->exists($USER->id);
if (!$isparticipant) {// or !has_capability('mod/dialogue:viewany', $context)
    redirect($returnurl, 'Oh! Ah, yes... I see that you know your judo well...', 5);
}

if (!($conversation->state == \mod_dialogue\dialogue::STATE_OPEN) or !($conversation->state == \mod_dialogue\dialogue::STATE_CLOSED)) {
    //redirect($returnurl, 'Oh! Ah, yes... I see that you know your judo well...', 5);
    //throw new moodle_exception('nopermission');
}



dialogue_actions_block();

echo $OUTPUT->header();
echo $OUTPUT->heading($activityrecord->name);
if (!empty($dialogue->activityrecord->intro)) {
    echo $OUTPUT->box(format_module_intro('dialogue', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
}
$renderer = $PAGE->get_renderer('mod_dialogue');
echo $renderer->render($conversation);
if ($conversation->replies()) {
    foreach ($conversation->replies() as $reply) {
        echo $renderer->render($reply);
    }
}

echo $OUTPUT->footer($course);
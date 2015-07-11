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

require_once(dirname(__FILE__).'/../../../../config.php');
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

$pageurl = new moodle_url('/mod/dialogue/conversation/bulkopener/edit.php');
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

//$action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true

$customdata = array('data'=>$conversation->prepare_form_data());
$form = new \mod_dialogue\form\bulkopener_form(null, $customdata, 'post', '', '');
if ($form->is_submitted()) {
    $action = $form->get_submit_action();
    switch ($action) {
        case 'cancel':
            redirect($returnurl);
        case 'send':
            if ($form->is_validated()){
                $data = $form->get_submitted_data();
                $conversation->load_form_data($data);
                $conversation->send();
                redirect($returnurl, get_string('conversationopened', 'dialogue'), 5);
            }
            break; // leave switch to display form page
        case 'save':
            $data = $form->get_submitted_data();
            $conversation->load_form_data($data);
            $conversation->save();
            redirect($draftsurl, get_string('changessaved'));
        case 'trash':
            $conversation->trash();
            redirect($draftsurl, get_string('draftconversationtrashed', 'dialogue'));
    }
}

dialogue_actions_block();
// Display form page
echo $OUTPUT->header();
echo $OUTPUT->heading($activityrecord->name);
if (!empty($dialogue->activityrecord->intro)) {
    echo $OUTPUT->box(format_module_intro('dialogue', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
}
$form->display();
echo $OUTPUT->footer($course);
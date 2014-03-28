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
 * View, create or edit a conversation in a dialogue. Also displays reply
 * form if open conversation.
 *
 * @package   mod_dialogue
 * @copyright 2013 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot . '/mod/dialogue/lib.php');
require_once($CFG->dirroot . '/mod/dialogue/locallib.php');
require_once($CFG->dirroot . '/mod/dialogue/formlib.php');

$id             = required_param('id', PARAM_INT);
$conversationid = optional_param('conversationid', null, PARAM_INT);
$action         = optional_param('action', 'view', PARAM_ALPHA);
$confirm        = optional_param('confirm', 0, PARAM_INT);

$cm = get_coursemodule_from_id('dialogue', $id);
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
$context = context_module::instance($cm->id, MUST_EXIST);

require_login($course, false, $cm);

$pageparams   = array('id' => $id, 'conversationid' => $conversationid, 'action' => $action);
$pageurl      = new moodle_url('/mod/dialogue/conversation.php', $pageparams);
$returnurl    = new moodle_url('/mod/dialogue/view.php', array('id' => $cm->id));
if (isset($SESSION->dialoguereturnurl)) {
    $returnurl = $SESSION->dialoguereturnurl;
}
$draftsurl    = new moodle_url('/mod/dialogue/drafts.php', array('id' => $cm->id));

$PAGE->set_pagetype('mod-dialogue-conversation');
$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);

$dialogue = new dialogue($cm, $course, $activityrecord);
$conversation = new dialogue_conversation($dialogue, $conversationid);

// form actions
if ($action == 'create' or $action == 'edit') {
    require_capability('mod/dialogue:open', $context);
    $form = $conversation->initialise_form();
    if ($form->is_submitted()) {
        $submitaction = $form->get_submit_action();
        switch ($submitaction) {
            case 'cancel':
                redirect($returnurl);
            case 'send':
                if ($form->is_validated()){
                    $conversation->save_form_data();
                    $conversation->send();
                    if ($conversation->state == dialogue::STATE_BULK_AUTOMATED) {
                        $sendmessage = get_string('conversationopenedcron', 'dialogue');
                    } else {
                        $sendmessage = get_string('conversationopened', 'dialogue');
                    }
                    redirect($returnurl, $sendmessage);
                }
                break; // leave switch to display form page
            case 'save':
                $conversation->save_form_data();
                redirect($draftsurl, get_string('changessaved'));
            case 'trash':
                $conversation->trash();
                redirect($draftsurl, get_string('draftconversationtrashed', 'dialogue'));
        }
    }

    // display form page
    echo $OUTPUT->header();
    echo $OUTPUT->heading($activityrecord->name);
    if (!empty($dialogue->activityrecord->intro)) {
        echo $OUTPUT->box(format_module_intro('dialogue', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
    }
    $form->display();
    echo $OUTPUT->footer($course);
    exit;
}

// close conversation
if ($action == 'close') {
    if (!empty($confirm) && confirm_sesskey()) {
        $conversation->close();
        redirect($returnurl, get_string('conversationclosed', 'dialogue',
                                        $conversation->subject));
    }
    echo $OUTPUT->header($activityrecord->name);
    $pageurl->param('confirm', $conversationid);
    $notification = $OUTPUT->notification(get_string('conversationcloseconfirm', 'dialogue', $conversation->subject), 'notifymessage');
    echo $OUTPUT->confirm($notification, $pageurl, $returnurl);
    echo $OUTPUT->footer();
    exit;
}

// delete conversation
if ($action == 'delete') {
    if (!empty($confirm) && confirm_sesskey()) {
        $conversation->delete();
        redirect($returnurl, get_string('conversationdeleted', 'dialogue',
                                        $conversation->subject));
    }
    echo $OUTPUT->header($activityrecord->name);
    $pageurl->param('confirm', $conversationid);
    $notification = $OUTPUT->notification(get_string('conversationdeleteconfirm', 'dialogue', $conversation->subject), 'notifyproblem');
    echo $OUTPUT->confirm($notification, $pageurl, $returnurl);
    echo $OUTPUT->footer();
    exit;
}

// ready for viewing, let's just make sure not a draft, possible url manipulation by user
if ($conversation->state == dialogue::STATE_DRAFT) {
    redirect($returnurl);
}

if ($conversation->state == dialogue::STATE_BULK_AUTOMATED) {
    if (!has_capability('mod/dialogue:bulkopenruleeditany', $context) and $conversation->author->id != $USER->id) {
        throw new moodle_exception('nopermission');
    }
}

if ($conversation->state == dialogue::STATE_OPEN or $conversation->state == dialogue::STATE_CLOSED) {
    if (!has_capability('mod/dialogue:viewany', $context) and !$conversation->is_participant()) {
        throw new moodle_exception('nopermission');
    }
}
// view conversation by default
$renderer = $PAGE->get_renderer('mod_dialogue');
echo $OUTPUT->header($activityrecord->name);
echo $renderer->render($conversation);
$conversation->mark_read();

// render replies
if ($conversation->replies()) {
    foreach ($conversation->replies() as $reply) {
        echo $renderer->render($reply);
        $reply->mark_read();
    }
}

// output reply form if meets criteria
$hasreplycapability = (has_capability('mod/dialogue:reply', $context) or
                       has_capability('mod/dialogue:replyany', $context));

// conversation is open and user can reply... then output reply form
if ($hasreplycapability and $conversation->state == dialogue::STATE_OPEN) {
    $reply = $conversation->reply();
    $form = $reply->initialise_form();
    $form->display();
}
echo $OUTPUT->footer($course);
$logurl = new moodle_url('conversation.php', array('id' =>  $cm->id, 'conversationid' => $conversation->conversationid));
add_to_log($course->id, 'dialogue', 'view conversation', $logurl->out(false), $conversation->subject, $cm->id);

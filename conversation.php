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
 * View, create, edit or reply to a conversation in a dialogue.
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

$cm = get_coursemodule_from_id('dialogue', $id, 0, false, MUST_EXIST);

$activityrecord = $DB->get_record('dialogue', ['id' => $cm->instance], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $activityrecord->course], '*', MUST_EXIST);
$context = \context_module::instance($cm->id, MUST_EXIST);

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
$PAGE->set_title(format_string($activityrecord->name));
$PAGE->set_heading(format_string($course->fullname));

$dialogue = new \mod_dialogue\dialogue($cm, $course, $activityrecord);
$conversation = new \mod_dialogue\conversation($dialogue, $conversationid);

if ($action == 'create' || $action == 'edit') {
    require_capability('mod/dialogue:open', $context);
    $form = $conversation->initialise_form();
    if ($form->is_submitted()) {
        $submitaction = $form->get_submit_action();
        switch ($submitaction) {
            case 'cancel':
                redirect($returnurl);
            case 'send':
                if ($form->is_validated()) {
                    $conversation->save_form_data();
                    $conversation->send();
                    if ($conversation->state == \mod_dialogue\dialogue::STATE_BULK_AUTOMATED) {
                        $sendmessage = get_string('conversationopenedcron', 'dialogue');
                    } else {
                        $sendmessage = get_string('conversationopened', 'dialogue');
                        // Trigger conversation created event.
                        $eventparams = array(
                            'context' => $context,
                            'objectid' => $conversation->conversationid
                        );
                        $event = \mod_dialogue\event\conversation_created::create($eventparams);
                        $event->trigger();
                    }
                    redirect($returnurl, $sendmessage);
                }
                break; // Leave switch to display form page.
            case 'save':
                $conversation->save_form_data();
                redirect($draftsurl, get_string('changessaved'));
            case 'trash':
                $conversation->trash();
                redirect($draftsurl, get_string('draftconversationtrashed', 'dialogue'));
        }
    }

    // Display form page.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($activityrecord->name));
    if (!empty($dialogue->activityrecord->intro)) {
        echo $OUTPUT->box(format_module_intro('dialogue', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
    }
    $form->display();
    echo $OUTPUT->footer($course);
    exit;
}

// Close conversation.
if ($action == 'close') {
    if (!empty($confirm) && confirm_sesskey()) {
        $conversation->close();
        // Trigger conversation closed event.
        $eventparams = array(
            'context' => $context,
            'objectid' => $conversation->conversationid
        );
        $event = \mod_dialogue\event\conversation_closed::create($eventparams);
        $event->trigger();
        redirect($returnurl, get_string('conversationclosed', 'dialogue',
                                        $conversation->subject));
    }
    echo $OUTPUT->header($activityrecord->name);
    $pageurl->param('confirm', $conversationid);
    $message = get_string('conversationcloseconfirm', 'dialogue', $conversation->subject);
    echo $OUTPUT->confirm($message, $pageurl, $returnurl);
    echo $OUTPUT->footer();
    exit;
}

// Delete conversation.
if ($action == 'delete') {
    if (!empty($confirm) && confirm_sesskey()) {
        $conversation->delete();
        // Trigger conversation created event.
        $eventparams = array(
            'context' => $context,
            'objectid' => $conversation->conversationid
        );
        $event = \mod_dialogue\event\conversation_deleted::create($eventparams);
        $event->trigger();
        // Redirect to the listing page we came from.
        redirect($returnurl, get_string('conversationdeleted', 'dialogue',
                                        $conversation->subject));
    }
    echo $OUTPUT->header($activityrecord->name);
    $pageurl->param('confirm', $conversationid);
    $message = get_string('conversationdeleteconfirm', 'dialogue', $conversation->subject);
    echo $OUTPUT->confirm($message, $pageurl, $returnurl);
    echo $OUTPUT->footer();
    exit;
}

// Ready for viewing, let's just make sure not a draft, possible url manipulation by user.
if ($conversation->state == \mod_dialogue\dialogue::STATE_DRAFT) {
    redirect($returnurl);
}

if ($conversation->state == \mod_dialogue\dialogue::STATE_BULK_AUTOMATED) {
    if (!has_capability('mod/dialogue:bulkopenruleeditany', $context) && $conversation->author->id != $USER->id) {
        throw new moodle_exception('nopermission');
    }
}

if ($conversation->state == \mod_dialogue\dialogue::STATE_OPEN || $conversation->state == \mod_dialogue\dialogue::STATE_CLOSED) {
    if (!has_capability('mod/dialogue:viewany', $context) && !$conversation->is_participant()) {
        throw new moodle_exception('nopermission');
    }
}
// View conversation by default.
$renderer = $PAGE->get_renderer('mod_dialogue');
echo $OUTPUT->header($activityrecord->name);
echo $renderer->render($conversation);
$conversation->mark_read();

// Render replies.
if ($conversation->replies()) {
    foreach ($conversation->replies() as $reply) {
        echo $renderer->render($reply);
        $reply->mark_read();
    }
}

// Output reply form if meets criteria.
$hasreplycapability = (has_capability('mod/dialogue:reply', $context) ||
                       has_capability('mod/dialogue:replyany', $context));

// Conversation is open and user can reply... then output reply form.
if ($hasreplycapability && $conversation->state == \mod_dialogue\dialogue::STATE_OPEN) {
    $reply = $conversation->reply();
    $form = $reply->initialise_form();
    $form->display();
}
echo $OUTPUT->footer($course);
// Trigger conversation viewed event.
$eventparams = array(
    'context' => $context,
    'objectid' => $conversation->conversationid
);
$event = \mod_dialogue\event\conversation_viewed::create($eventparams);
$event->trigger();

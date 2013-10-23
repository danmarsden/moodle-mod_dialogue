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
 *
 *
 * @package   mod_dialogue
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('lib.php');
require_once('locallib.php');
require_once('formlib.php');

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

$pageparams   = array('id'=>$id, 'conversationid'=>$conversationid,'action'=>$action);
$pageurl      = new moodle_url('/mod/dialogue/conversation.php', $pageparams);
$returnurl    = new moodle_url('/mod/dialogue/view.php', array('id'=>$cm->id));
/// setup page and form

$PAGE->set_pagetype('mod-dialogue-conversation');
$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);

$dialogue = new dialogue($cm, $course, $activityrecord);
$conversation = new dialogue_conversation($dialogue, $conversationid);

switch ($action) {
    case 'create':
    case 'edit':
        require_capability('mod/dialogue:open', $context);
        $form = $conversation->initialise_form();
        if ($form->is_submitted()) {
            $formaction = $form->get_submit_action();
            // handle submits
            switch ($formaction) {
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
                    break;
                case 'save':
                    $conversation->save_form_data();
                    redirect($returnurl, get_string('changessaved'));
                case 'delete':
                    echo $OUTPUT->header($activityrecord->name);
                    $pageurl->param('action', 'delete');
                    $pageurl->param('confirm', $conversationid);
                    echo $OUTPUT->confirm(get_string('conversationdeleteconfirm', 'dialogue', $conversation->subject),
                                          $pageurl, $returnurl);
                    echo $OUTPUT->footer();
                    exit;
            }

        }
        // if not validated display form with errors
        echo $OUTPUT->header();
        echo $OUTPUT->heading($activityrecord->name);
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == SEPARATEGROUPS or $groupmode == VISIBLEGROUPS) {
            echo $OUTPUT->notification(get_string('groupmodenotifymessage', 'dialogue'), 'notifymessage');
        }
        $groupsurl = clone($pageurl);
        $groupsurl->remove_params('page'); // clear page
        echo groups_print_activity_menu($cm, $groupsurl, true);
        echo html_writer::empty_tag('br');
        $form->display();
        echo $OUTPUT->footer($course);
        exit;
    case 'close':
        if (!empty($confirm) && confirm_sesskey()) {
            $conversation->close();
            redirect($returnurl, get_string('conversationclosed', 'dialogue',
                                            $conversation->subject));
        }
        echo $OUTPUT->header($activityrecord->name);
        $pageurl->param('confirm', $conversationid);
        echo $OUTPUT->confirm(get_string('conversationcloseconfirm', 'dialogue', $conversation->subject),
                              $pageurl, $returnurl);
        echo $OUTPUT->footer();
        exit;
    case 'delete':
        if (!empty($confirm) && confirm_sesskey()) {
            $conversation->delete();
            redirect($returnurl, get_string('conversationdeleted', 'dialogue',
                                            $conversation->subject));
        }
        // this shouldn't happen
        redirect($returnurl);
        echo $OUTPUT->header($activityrecord->name);
        $pageurl->param('action', 'delete');
        $pageurl->param('confirm', $conversationid);
        echo $OUTPUT->confirm(get_string('conversationdeleteconfirm', 'dialogue', $conversation->subject),
                              $pageurl, $returnurl);
        echo $OUTPUT->footer();
        exit;
    case 'view':
        cache_helper::purge_by_event('conversationviewed');

        $canview = ($conversation->is_author() or
                    $conversation->is_participant() or
                    has_capability('mod/dialogue:viewany', $context));

        $canreply = (($conversation->is_participant() and
                    has_capability('mod/dialogue:reply', $context)) or
                    has_capability('mod/dialogue:replyany', $context));


        if (!$canview or ($conversation->state == dialogue::STATE_DRAFT)) {
            //throw new moodle_exception('Fuck off loser!');
        }

        echo $OUTPUT->header($activityrecord->name);

        $modrenderer = $PAGE->get_renderer('mod_dialogue');
        echo $modrenderer->render($conversation);

        if ($canreply and ($conversation->state == dialogue::STATE_OPEN)) {
            $reply = $conversation->reply();
            $form = $reply->initialise_form();
            $form->display();
        }
        echo $OUTPUT->footer($course);
        $logurl = new moodle_url('conversation.php', array('id' =>  $cm->id, 'conversationid' => $conversation->conversationid));
        add_to_log($course->id, 'dialogue', 'view conversation', $logurl->out(false), $conversation->subject, $cm->id);
        break;
}
//print_object($SESSION);

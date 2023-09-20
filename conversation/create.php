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
 * Create conversation
 *
 * @package mod_dialogue
 * @copyright 2014 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__).'/../../../config.php');
require_once($CFG->dirroot . '/mod/dialogue/locallib.php');

$cmid           = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id('dialogue', $cmid, 0, false, MUST_EXIST);

$activityrecord = $DB->get_record('dialogue', array('id' => $cm->instance), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $activityrecord->course), '*', MUST_EXIST);
$context = \context_module::instance($cm->id, MUST_EXIST);

require_login($course, false, $cm);

$pageparams   = array('cmid' => $cm->id);
$pageurl      = new moodle_url('/mod/dialogue/conversation/create.php', $pageparams);
$returnurl    = new moodle_url('/mod/dialogue/view.php', array('id' => $cm->id));
$draftsurl    = new moodle_url('/mod/dialogue/drafts.php', array('id' => $cm->id));


$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);

require_capability('mod/dialogue:open', $context);

$dialogue = new \mod_dialogue\dialogue($cm, $course, $activityrecord);
$conversation = new \mod_dialogue\conversation($dialogue); // New conversation.

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
$form->display();
echo $OUTPUT->footer($course);

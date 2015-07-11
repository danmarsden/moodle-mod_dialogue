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

$cmid           = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('dialogue', $cmid);
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

$pageparams   = array('cmid' => $cm->id);
$pageurl      = new moodle_url('/mod/dialogue/conversation/create.php', $pageparams);
$returnurl    = new moodle_url('/mod/dialogue/view.php', array('id' => $cm->id));
$draftsurl    = new moodle_url('/mod/dialogue/drafts.php', array('id' => $cm->id));


$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);

dialogue_actions_block();

require_capability('mod/dialogue:open', $context);

$dialogue = new \mod_dialogue\dialogue($cm, $course, $activityrecord);
$conversation = new \mod_dialogue\conversation($dialogue); // New conversation.
$form = new \mod_dialogue\form\conversation_form(null, array('data'=>$conversation->prepare_form_data()));

if ($form->is_submitted()) {
    $action = $form->get_submit_action();
    switch ($action) {
        case 'cancel':
            redirect($returnurl);
        case 'send':
            if ($form->is_validated()){
                $data = $form->get_submitted_data();
                $conversation->load_form_data($data);
                $conversation->save();
                $conversation->send();
                // Trigger conversation created event
                $eventparams = array(
                    'context' => $context,
                    'objectid' => $conversation->conversationid
                );
                $event = \mod_dialogue\event\conversation_created::create($eventparams);
                $event->trigger();
                redirect($returnurl, get_string('conversationopened', 'dialogue'), 5);
            }
            break; // leave switch to display form page
        case 'save':
            $data = $form->get_submitted_data();
            $conversation->load_form_data($data);
            $conversation->save();
            redirect($draftsurl, get_string('changessaved'));
    }
}
// Display form page
echo $OUTPUT->header();
echo $OUTPUT->heading($activityrecord->name);
if (!empty($dialogue->activityrecord->intro)) {
    echo $OUTPUT->box(format_module_intro('dialogue', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
}
if (!empty($dialogue->activityrecord->oneperperson)) {
    echo $OUTPUT->notification('<i class="fa fa-info-circle"></i><span class="message">' .
                                get_string('oneperpersonisset', 'dialogue') .
                               '</span>', 'notifymessage');
}
$form->display();
echo $OUTPUT->footer($course);
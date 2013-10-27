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

$id         = required_param('id', PARAM_INT);
$roleid     = optional_param('roleid', 0, PARAM_INT);
$state      = optional_param('state', dialogue::STATE_OPEN, PARAM_ALPHA);
$page       = optional_param('page', 0, PARAM_INT);
$showall    = optional_param('showall', 0, PARAM_INT);
$sort       = optional_param('sort', 'latest', PARAM_ALPHANUM);


if ($id) {
    if (! $cm = get_coursemodule_from_id('dialogue', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $activityrecord = $DB->get_record('dialogue', array('id' => $cm->instance))) {
        print_error('invalidid', 'dialogue');
    }
    if (! $course = $DB->get_record('course', array('id' => $activityrecord->course))) {
        print_error('coursemisconf');
    }
} else {
    print_error('missingparameter');
}

$context = context_module::instance($cm->id);

require_login($course, false, $cm);

if (!isset($USER->preference['dialogue_viewrole'])) {
    $studentroles = $DB->get_records('role', array('archetype'=>'student'));
    $studentrole = reset($studentroles);
    set_user_preference('dialogue_viewrole', $studentrole->id);
}

if (empty($roleid)) {
    $roleid = $USER->preference['dialogue_viewrole'];
} else {
    set_user_preference('dialogue_viewrole', $roleid);
}

$pageparams = array('id' => $cm->id,
                    'roleid' => $roleid,
                    'state' => $state,
                    'page' => $page,
                    'showall' => $showall,
                    'sort' => $sort);

$pageurl    = new moodle_url('/mod/dialogue/viewbyrole.php', $pageparams);

$PAGE->set_pagetype('mod-dialogue-view-by-role');
$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($activityrecord->name));
$PAGE->set_heading(format_string($course->fullname));

dialogue_load_bootstrap_js();

$dialogue = new dialogue($cm, $course, $activityrecord);
$total = 0;
$rs = dialogue_get_conversation_listing_by_role($dialogue, $total);
$pagination = new paging_bar($total, $page, dialogue::PAGINATION_PAGE_SIZE, $pageurl);

$modrenderer = $PAGE->get_renderer('mod_dialogue');

$a = new stdClass();
$a->state = strtolower(get_string($state, 'dialogue'));
$a->show = get_string('everyones', 'dialogue');
$a->groupname = '';
$a->currentdisplay = count($rs);
$a->total = $total;


$html = '';
if (!$rs) {
    $html .= html_writer::start_div();
    $html .= html_writer::tag('h6', get_string('conversationlistdisplayheader', 'dialogue', $a));
    $html .= html_writer::end_div();
    $html .= $OUTPUT->notification(get_string('noconversationsfound', 'dialogue'), 'notifyproblem');
} else {
    $html .= html_writer::start_div('listing-meta');
    $html .= html_writer::tag('h6', get_string('conversationlistdisplayheader', 'dialogue', $a));
    $a         = new stdClass();
    $a->start  = ($page) ? $page * dialogue::PAGINATION_PAGE_SIZE : 1;
    $a->end    = $page * dialogue::PAGINATION_PAGE_SIZE + count($rs);
    $a->total  = $total;
    $html .= html_writer::tag('h6', new lang_string('listpaginationheader', 'dialogue', $a), array('class'=>'pull-right'));
    $html .= html_writer::end_div();
    $html .= html_writer::start_tag('table', array('class'=>'table table-hover table-condensed'));
    $html .= html_writer::start_tag('tbody');
    foreach($rs as $record) {
        $html .= html_writer::start_tag('tr', array('id'=>'item-'.$record->id));
        if ($record->state == dialogue::STATE_CLOSED) {
            $label = html_writer::tag('span', get_string('closed', 'dialogue'),
                                      array('class'=>'label label-important'));
            $html .= html_writer::tag('td', $label);
        }
       
        
        $author = dialogue_get_user_details($dialogue, $record->authorid);
        //$author = $dialogue->get_user_brief_details($record->authorid);
        $avatar = $OUTPUT->user_picture($author, array('class'=> 'userpicture img-rounded', 'size' => 48));
        $html .= html_writer::tag('td', $avatar);
        $html .= html_writer::tag('td', fullname($author));

        $subject = empty($record->subject) ? get_string('nosubject', 'dialogue') : $record->subject;
        $subject = html_writer::tag('strong', $subject);
        $shortenedbody = dialogue_shorten_html($record->body);
        $shortenedbody = html_writer::tag('span', $shortenedbody);
        $participantshtml = '';

        $participants = dialogue_get_conversation_participants($dialogue, $record->conversationid);
        foreach($participants as $participantid) {
            if ($author->id == $participantid) {
                continue;
            }
            $participant = dialogue_get_user_details($dialogue, $participantid);
            $picture = $OUTPUT->user_picture($participant, array('class'=>'userpicture img-rounded', 'size'=>16));
            $participanthtml = html_writer::tag('span', $picture.'&nbsp;'.fullname($participant), array('class' => 'participant'));
            $participantshtml .=  $participanthtml;
        }

        $html .= html_writer::tag('td', $subject.' - '.$shortenedbody.'<br/>'.$participantshtml);
        $date = (object) dialogue_getdate($record->timemodified);
        if ($date->today) {
            $timemodified = $date->time;
        } else if ($date->currentyear) {
            $timemodified = new lang_string('dateshortyear', 'dialogue', $date);
        } else {
            $timemodified = new lang_string('datefullyear', 'dialogue', $date);
        }
        $html .= html_writer::tag('td', $timemodified, array('title' => userdate($record->timemodified)));
        //$html .= html_writer::tag('td', dialogue_timeago($record->timemodified), array('title' => userdate($record->timemodified)));

        $viewurlparams = array('id' => $cm->id, 'conversationid' => $record->conversationid, 'action' => 'view');
        $viewlink = html_writer::link(new moodle_url('conversation.php', $viewurlparams),
                                      get_string('view'), array('class'=>'nonjs-control-show'));

        $html .= html_writer::tag('td', $viewlink);

        $html .= html_writer::end_tag('tr');
    }
    $html .= html_writer::end_tag('tbody');
    //$html .= html_writer::tag('caption', '@todo');
    $html .= html_writer::end_tag('table');
    $html .= $OUTPUT->render($pagination); // just going to use standard pagebar, to much work to bootstrap it.
}

echo $OUTPUT->header();

if (!empty($dialogue->activityrecord->intro)) {
    echo $OUTPUT->box(format_module_intro('dialogue', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
}

echo $modrenderer->tab_navigation();
echo $modrenderer->state_button_group();
echo $modrenderer->role_selector();

$options = array('latest',
                 'lastnameaz',
                 'lastnameza',
                 'firstnameaz',
                 'firstnameza');

echo $modrenderer->sort_by_dropdown($options);

echo $html;
echo $OUTPUT->footer($course);
$logurl = new moodle_url('view.php', array('id' =>  $cm->id));
add_to_log($course->id, 'dialogue', 'view by role', $logurl->out(false), $activityrecord->name, $cm->id);
exit;

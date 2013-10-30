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
$state      = optional_param('state', null, PARAM_ALPHA);
$show       = optional_param('show', null, PARAM_ALPHA);
$page       = optional_param('page', 0, PARAM_INT);
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

// use cached params for toggle button groups
$state      = dialogue_get_cached_param('state', $state, dialogue::STATE_OPEN);
$show       = dialogue_get_cached_param('show', $show, dialogue::SHOW_MINE);

// now set params on pageurl will later be set on $PAGE
$pageparams = array('id' => $cm->id, 'state' => $state, 'show' => $show, 'page' => $page, 'sort' => $sort);
$pageurl    = new moodle_url('/mod/dialogue/view.php', $pageparams);

$PAGE->set_pagetype('mod-dialogue-view-index');
$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($activityrecord->name));
$PAGE->set_heading(format_string($course->fullname));

// check if needs to be upgraded
if (dialogue_cm_needs_upgrade($cm->id)) {
    $link = new moodle_url('/course/view.php', array('id' => $COURSE->id));
    notice(get_string('upgrademessage', 'dialogue'), $link);
    exit;
}

dialogue_load_bootstrap_js();// load javascript if not bootstrap theme

$dialogue = new dialogue($cm, $course, $activityrecord);
$total = 0;
$rs = dialogue_get_conversation_listing($dialogue, $total);
$pagination = new paging_bar($total, $page, dialogue::PAGINATION_PAGE_SIZE, $pageurl);

$renderer = $PAGE->get_renderer('mod_dialogue');

echo $OUTPUT->header();
if (!empty($dialogue->activityrecord->intro)) {
    echo $OUTPUT->box(format_module_intro('dialogue', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
}

$groupmode = groups_get_activity_groupmode($cm);
if ($groupmode == SEPARATEGROUPS or $groupmode == VISIBLEGROUPS) {
    echo $OUTPUT->notification(get_string('groupmodenotifymessage', 'dialogue'), 'notifymessage');
}
$groupsurl = clone($pageurl);
$groupsurl->remove_params('page'); // clear page
echo groups_print_activity_menu($cm, $groupsurl, true);
echo html_writer::empty_tag('br');

// render tab navigation, toggle button groups and order by dropdown
echo $renderer->tab_navigation();
echo $renderer->state_button_group();
echo $renderer->show_button_group();
echo $renderer->conversation_list_sortby();

$a = new stdClass();
$a->state = ($state == dialogue::STATE_OPEN) ?
            get_string(dialogue::STATE_OPEN, 'dialogue') :
            get_string(dialogue::STATE_CLOSED, 'dialogue');
$a->show  = ($show == dialogue::SHOW_MINE) ?
            get_string('justmy', 'dialogue') :
            get_string('everyones', 'dialogue');
$a->groupname = '';
$activegroup = groups_get_activity_group($cm, true);
if ($activegroup) {
    $a->groupname = get_string('ingroup', 'dialogue', groups_get_group_name($activegroup));
}

$html = '';
if (!$rs) {
    $html .= html_writer::start_div();
    $html .= html_writer::tag('h6', get_string('conversationlistdisplayheader', 'dialogue', $a));
    $html .= html_writer::end_div();
    $html .= $OUTPUT->notification(get_string('noconversationsfound', 'dialogue'), 'notifyproblem');
} else {

    $unreadcounts = dialogue_unread_counts($dialogue);

    $html .= html_writer::start_div('listing-meta');
   
    $html .= html_writer::tag('h6', get_string('conversationlistdisplayheader', 'dialogue', $a));
    $a         = new stdClass();
    $a->start  = ($page) ? $page * dialogue::PAGINATION_PAGE_SIZE : 1;
    $a->end    = $page * dialogue::PAGINATION_PAGE_SIZE + count($rs);
    $a->total  = $total;
    $html .= html_writer::tag('h6', get_string('listpaginationheader', 'dialogue', $a), array('class'=>'pull-right'));
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
        
        //$unreadcount = $record->unread;
        $unreadcount = $unreadcounts[$record->conversationid];

        $badgeclass = ($unreadcount) ? 'badge label-info' : 'hidden' ;
        $badge = html_writer::span($unreadcount, $badgeclass, array('title'=>get_string('numberunread', 'dialogue', $unreadcount)));
        $html .= html_writer::tag('td', $badge);

        $author = dialogue_get_user_details($dialogue, $record->authorid);
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
            $participanthtml = html_writer::tag('span', $picture.'&nbsp;'.fullname($participant));
            $participantshtml .=  $participanthtml;
        }

        $t = dialogue_listing_summary($record->subject, $record->body);
        $html .= html_writer::tag('td', $t.'<br/>'.$participantshtml);
        //$html .= html_writer::tag('td', $subject.' - '.$shortenedbody.'<br/>'.$participantshtml);
        $date = (object) dialogue_getdate($record->timemodified);
        if ($date->today) {
            $timemodified = $date->time;
        } else if ($date->currentyear) {
            $timemodified = get_string('dateshortyear', 'dialogue', $date);
        } else {
            $timemodified = get_string('datefullyear', 'dialogue', $date);
        }
        $html .= html_writer::tag('td', $timemodified, array('title' => userdate($record->timemodified)));

        $viewurlparams = array('id' => $cm->id, 'conversationid' => $record->conversationid, 'action' => 'view');
        $viewlink = html_writer::link(new moodle_url('conversation.php', $viewurlparams),
                                      get_string('view'), array('class'=>'nonjs-control-show'));

        $html .= html_writer::tag('td', $viewlink);

        $html .= html_writer::end_tag('tr');
    }
    $html .= html_writer::end_tag('tbody');
    $html .= html_writer::end_tag('table');
    $html .= $OUTPUT->render($pagination); // just going to use standard pagebar, to much work to bootstrap it.
}
echo $html;
echo $OUTPUT->footer($course);
$logurl = new moodle_url('view.php', array('id' =>  $cm->id));
add_to_log($course->id, 'dialogue', 'view', $logurl->out(false), $activityrecord->name, $cm->id);

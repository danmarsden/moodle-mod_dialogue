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
$page       = optional_param('page', 0, PARAM_INT);

if ($id) {
    if (! $cm = get_coursemodule_from_id('dialogue', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $activityrecord = $DB->get_record("dialogue", array("id"=>$cm->instance))) {
        print_error('invalidid', 'dialogue');
    }
    if (! $course = $DB->get_record("course", array("id"=>$activityrecord->course))) {
        print_error('coursemisconf');
    }
} else {
    print_error('missingparameter');
}

$context = context_module::instance($cm->id);

require_login($course, false, $cm);

$pageparams = array('id'=>$cm->id);
$pageurl    = new moodle_url('/mod/dialogue/drafts.php', $pageparams);
/// setup page and form
$PAGE->set_pagetype('mod-dialogue-drafts');
$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($activityrecord->name));
$PAGE->set_heading(format_string($course->fullname));

$PAGE->requires->yui_module('moodle-mod_dialogue-clickredirector',
                            'M.mod_dialogue.clickredirector.init', array($cm->id));

$dialogue = new dialogue($cm, $course, $activityrecord);
$total = 0;
$rs = dialogue_get_draft_listing($dialogue, $total);
$pagination = new paging_bar($total, $page, dialogue::PAGINATION_PAGE_SIZE, $pageurl);

// get the dialogue module render
$renderer = $PAGE->get_renderer('mod_dialogue');

echo $OUTPUT->header();
if (!empty($dialogue->activityrecord->intro)) {
    echo $OUTPUT->box(format_module_intro('dialogue', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
}

echo $renderer->tab_navigation($dialogue);
$html = '';
if (!$rs) {
    $html .= $OUTPUT->notification(get_string('nodraftsfound', 'dialogue'), 'notifyproblem');
} else {
    $html .= html_writer::start_div('listing-meta');
    $html .= html_writer::tag('h6', new lang_string('draftlistdisplayheader', 'dialogue'));
    $a = new stdClass();
    $a->start = ($page) ? $page * dialogue::PAGINATION_PAGE_SIZE : 1;
    $a->end = $page * dialogue::PAGINATION_PAGE_SIZE + count($rs);
    $a->total = $total;
    $html .= html_writer::tag('h6', new lang_string('listpaginationheader', 'dialogue', $a), array('class' => 'pull-right'));
    $html .= html_writer::end_div();

    $html .= html_writer::start_tag('table', array('class'=>'conversation-list table table-hover table-condensed'));
    $html .= html_writer::start_tag('tbody');
    foreach($rs as $record) {
        if (dialogue_is_a_conversation($record)) {
            $label = html_writer::tag('span', get_string('draftconversation', 'dialogue'),
                              array('class' => 'state-indicator state-draft'));

            $datattributes = array('data-redirect' => 'conversation',
                                   'data-action'   => 'edit',
                                   'data-conversationid' => $record->conversationid);

            $params = array('id' => $cm->id,
                            'conversationid' => $record->conversationid,
                            'action' => 'edit');

            $editlink = html_writer::link(new moodle_url('conversation.php', $params),
                                      get_string('edit'), array());
        } else {
            $label = html_writer::tag('span', get_string('draftreply', 'dialogue'),
                              array('class' => 'state-indicator state-draft'));

            $datattributes = array('data-redirect' => 'reply',
                                   'data-action'   => 'edit',
                                   'data-conversationid' => $record->conversationid,
                                   'data-messageid' => $record->id);

            $params = array('id' => $cm->id,
                            'conversationid' => $record->conversationid,
                            'messageid' => $record->id,
                            'action' => 'edit');
            
            $editlink = html_writer::link(new moodle_url('reply.php', $params), get_string('edit'), array());
        }
        

        $html .=  html_writer::start_tag('tr', $datattributes);
        $html .= html_writer::tag('td', $label);
        $subject = empty($record->subject) ? get_string('nosubject', 'dialogue') : $record->subject;
        $subject = html_writer::tag('strong', $subject);
        $shortenedbody = dialogue_shorten_html($record->body, 60);
        $shortenedbody = html_writer::tag('span', $shortenedbody);
        $html .= html_writer::tag('td', $subject.' - '.$shortenedbody);
        
        $date = (object) dialogue_getdate($record->timemodified);
        if ($date->today) {
            $timemodified = $date->time;
        } else if ($date->currentyear) {
            $timemodified = new lang_string('dateshortyear', 'dialogue', $date);
        } else {
            $timemodified = new lang_string('datefullyear', 'dialogue', $date);
        }
        $html .= html_writer::tag('td', $timemodified, array('title' => userdate($record->timemodified)));
       
        $html .= html_writer::tag('td', $editlink, array('class'=>'nonjs-control'));
        $html .= html_writer::end_tag('tr');
    }
    $html .= html_writer::end_tag('tbody');
    //$html .= html_writer::tag('caption', '@todo');
    $html .= html_writer::end_tag('table');
    $html .= $OUTPUT->render($pagination); // just going to use standard pagebar, to much work to bootstrap it.
}
echo $html;
echo $OUTPUT->footer($course);
exit;

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

$pageparams = array('id' => $cm->id, 'page' => $page, 'sort' => $sort);
$pageurl    = new moodle_url('/mod/dialogue/bulkopenrules.php', $pageparams);
/// setup page and form
$PAGE->set_pagetype('mod-dialogue-bulkopenrules');
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
$rs = dialogue_get_bulk_open_rule_listing($dialogue, $total);
$pagination = new paging_bar($total, $page, dialogue::PAGINATION_PAGE_SIZE, $pageurl);

// get the dialogue module render
$renderer = $PAGE->get_renderer('mod_dialogue');

echo $OUTPUT->header();
if (!empty($dialogue->activityrecord->intro)) {
    echo $OUTPUT->box(format_module_intro('dialogue', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
}

echo $renderer->tab_navigation($dialogue);

$groupcache = groups_cache_groupdata($course->id);

$html = '';
if (!$rs) {
    $html .= $OUTPUT->notification(get_string('nobulkrulesfound', 'dialogue'), 'notifyproblem');
} else {
    $html .= html_writer::start_div('listing-meta');
    $html .= html_writer::tag('h6', get_string('displaying', 'dialogue'));
    $a = new stdClass();
    $a->start = ($page) ? $page * dialogue::PAGINATION_PAGE_SIZE : 1;
    $a->end = $page * dialogue::PAGINATION_PAGE_SIZE + count($rs);
    $a->total = $total;
    $html .= html_writer::tag('h6', get_string('listpaginationheader', 'dialogue', $a), array('class' => 'pull-right'));
    $html .= html_writer::end_div();


    $html .= html_writer::start_tag('table', array('class'=>'conversation-list table table-hover table-condensed'));
    $html .= html_writer::start_tag('tbody');
    foreach($rs as $record) {

        $datattributes = array('data-redirect' => 'conversation',
                               'data-action'   => 'view',
                               'data-conversationid' => $record->conversationid);

        $html .=  html_writer::start_tag('tr', $datattributes);
        if ($record->lastrun) {
            $lastrun = get_string('lastranon', 'dialogue') . userdate($record->lastrun);
        } else {
            $lastrun = get_string('hasnotrun', 'dialogue');
        }
        $html .= html_writer::tag('td', $lastrun);

        if ($record->includefuturemembers) {
            if ($record->cutoffdate > time()) {
                $runsuntil = html_writer::tag('i', get_string('runsuntil', 'dialogue') . userdate($record->cutoffdate));
                $html .= html_writer::tag('td', $runsuntil);
            } else {
                $html .= html_writer::tag('td', get_string('completed', 'dialogue'));
            }
        } else {
            if (!$record->lastrun) {
                $html .= html_writer::tag('td', '');
            } else {
                $html .= html_writer::tag('td', get_string('completed', 'dialogue'));
            }
        }


        if ($record->type == 'group') {
            $html .= html_writer::tag('td', $groupcache->groups[$record->sourceid]->name);
            
        } else {
            $html .= html_writer::tag('td', get_string('allparticipants'));
        }
        
        $subject = empty($record->subject) ? get_string('nosubject', 'dialogue') : $record->subject;
        $subject = html_writer::tag('strong', $subject);
        $html .= html_writer::tag('td', $subject);

        $params = array('id' => $cm->id, 'conversationid' => $record->conversationid);
        $link = html_writer::link(new moodle_url('conversation.php', $params),
                                   get_string('view'), array());
        $html .= html_writer::tag('td', $link, array('class'=>'nonjs-control'));
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
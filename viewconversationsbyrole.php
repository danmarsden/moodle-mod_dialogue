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
require_once($CFG->dirroot . '/mod/dialogue/classes/conversations.php');
require_once($CFG->dirroot . '/mod/dialogue/classes/conversations_by_role.php');

$id         = required_param('id', PARAM_INT);
$roleid     = optional_param('roleid', 0, PARAM_INT);
$page       = optional_param('page', 0, PARAM_INT);
$sort       = optional_param('sort', 'fullname', PARAM_ALPHANUM);
$direction  = optional_param('direction', 'asc', PARAM_ALPHA);

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

$rolenames = role_fix_names(get_profile_roles($context), $context, ROLENAME_ALIAS, true);
if (!$roleid) {
    $roleid  = $DB->get_field('role', 'id', array('shortname' => 'student'), MUST_EXIST);
}

// now set params on pageurl will later be set on $PAGE
$pageurl = new moodle_url('/mod/dialogue/viewconversationsbyrole.php');
$pageurl->param('id', $cm->id);
if ($page) {
    $pageurl->param('page', $page);
}
$pageurl->param('sort', $sort);
$pageurl->param('direction', $direction);
// set up a return url that will be stored to session
$returnurl = clone($pageurl);
$returnurl->remove_params('page');
$SESSION->dialoguereturnurl = $returnurl->out(false);



$PAGE->set_pagetype('mod-dialogue-view-index');
$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($activityrecord->name));
$PAGE->set_heading(format_string($course->fullname));

$PAGE->requires->yui_module('moodle-mod_dialogue-clickredirector',
                            'M.mod_dialogue.clickredirector.init', array($cm->id));

$dialogue = new \mod_dialogue\dialogue($cm, $course, $activityrecord);
$list = new \mod_dialogue\conversations_by_role($dialogue, $roleid, $page, \mod_dialogue\dialogue::PAGINATION_PAGE_SIZE);
$list->set_order($sort, $direction);

$renderer = $PAGE->get_renderer('mod_dialogue');

echo $OUTPUT->header();
echo $OUTPUT->heading($activityrecord->name);
if (!empty($dialogue->activityrecord->intro)) {
    echo $OUTPUT->box(format_module_intro('dialogue', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
}

// render tab navigation, toggle button groups and order by dropdown
echo $renderer->tab_navigation($dialogue);
$roleselector = '';
$roleselector .= html_writer::start_div('dropdown-group');
$roleselector .= html_writer::start_div('js-control btn-group'); // btn-group required for js
$attributes = array('data-toggle' => 'dropdown', 'class' =>'btn btn-small dropdown-toggle');
$roleselector .= html_writer::start_tag('button', $attributes);
$roleselector .= $rolenames[$roleid] . ' ' . html_writer::tag('span', null, array('class' => 'caret'));
$roleselector .= html_writer::end_tag('button');
$roleselector .= html_writer::start_tag('ul', array('class' => 'dropdown-menu'));
foreach ($rolenames as $roleid => $rolename) {
    $pageurl->param('roleid', $roleid);
    $roleselector .= html_writer::start_tag('li');
    $roleselector .= html_writer::link($pageurl, $rolename);
    $roleselector .= html_writer::end_tag('li');
}
$roleselector .= html_writer::end_tag('ul');
$roleselector .= html_writer::end_div(); // end of js-control
$roleselector .= html_writer::end_div();
echo $roleselector;
echo $renderer->list_sortby(\mod_dialogue\conversations_by_role::get_sort_options(), $sort, $direction);
echo $renderer->conversation_listing($list);
echo $OUTPUT->footer($course);
$logurl = new moodle_url('viewconversationsbyrole.php', array('id' =>  $cm->id));


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
 * Code fragment to define the module version etc.
 *
 * @package mod_dialogue
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('lib.php');
require_once('locallib.php');
require_once($CFG->dirroot . '/mod/dialogue/classes/conversations.php');
require_once($CFG->dirroot . '/mod/dialogue/classes/conversations_by_author.php');

$id         = required_param('id', PARAM_INT);
$state      = optional_param('state', \mod_dialogue\dialogue::STATE_OPEN, PARAM_ALPHA);
$page       = optional_param('page', 0, PARAM_INT);
$sort       = optional_param('sort', 'latest', PARAM_ALPHANUM);
$direction  = optional_param('direction', 'asc', PARAM_ALPHA);

$cm = get_coursemodule_from_id('dialogue', $id, 0, false, MUST_EXIST);
$activityrecord = $DB->get_record('dialogue', ['id' => $cm->instance], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $activityrecord->course], '*', MUST_EXIST);

$context = \context_module::instance($cm->id);

require_login($course, false, $cm);

// Now set params on pageurl will later be set on $PAGE.
$pageurl = new moodle_url('/mod/dialogue/view.php');
$pageurl->param('id', $cm->id);
$pageurl->param('state', $state);
if ($page) {
    $pageurl->param('page', $page);
}
$pageurl->param('sort', $sort);
$pageurl->param('direction', $direction);
// Set up a return url that will be stored to session.
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
$list = new \mod_dialogue\conversations_by_author($dialogue, $page, \mod_dialogue\dialogue::PAGINATION_PAGE_SIZE);
$list->set_state($state);
$list->set_order($sort, $direction);

$renderer = $PAGE->get_renderer('mod_dialogue');

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($activityrecord->name));
if (!empty($dialogue->activityrecord->intro)) {
    echo $OUTPUT->box(format_module_intro('dialogue', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
}

// Render tab navigation, toggle button groups and order by dropdown.
echo $renderer->tab_navigation($dialogue);
echo $renderer->state_button_group();
echo $renderer->list_sortby(\mod_dialogue\conversations_by_author::get_sort_options(), $sort, $direction);
echo $renderer->conversation_listing($list);
echo $OUTPUT->footer($course);

// Trigger course module viewed event.
$eventparams = array(
    'context' => $context,
    'objectid' => $activityrecord->id
);
$event = \mod_dialogue\event\course_module_viewed::create($eventparams);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('dialogue', $activityrecord);
$event->trigger();

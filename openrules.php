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

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/dialogue/locallib.php');

$cmid       = optional_param('id', 0, PARAM_INT);
$d          = optional_param('d', 0, PARAM_INT);
$page       = optional_param('page', 0, PARAM_INT);

if ($cmid) {
    $cm = get_coursemodule_from_id('dialogue', $cmid);
    if (! $cm) {
        print_error('invalidcoursemodule');
    }
} else if ($d) {
    $cm = get_coursemodule_from_instance("dialogue", $d);
    if (! $cm) {
        print_error('invalidcoursemodule');
    }
} else {
    print_error('missingparameter');
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

// now set params on pageurl will later be set on $PAGE
$pageurl = new moodle_url('/mod/dialogue/openrules.php');
$pageurl->param('id', $cm->id);


$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($activityrecord->name));
$PAGE->set_heading(format_string($course->fullname));

dialogue_actions_block();

$dialogue = new \mod_dialogue\dialogue($cm, $course, $activityrecord);
$list = new \mod_dialogue\open_rules_list($dialogue, $page, \mod_dialogue\dialogue::PAGINATION_PAGE_SIZE);

echo $OUTPUT->header();
echo $OUTPUT->heading($activityrecord->name);
if (!empty($dialogue->activityrecord->intro)) {
    echo $OUTPUT->box(format_module_intro('dialogue', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
}
$renderer = $PAGE->get_renderer('mod_dialogue');
if ($list->rows_matched()) {
    echo $renderer->open_rules_listing($list);
} else {
    echo html_writer::tag('h2', get_string('norulesfound', 'dialogue'));
}
echo $OUTPUT->footer($course);

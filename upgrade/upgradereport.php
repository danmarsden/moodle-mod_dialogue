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

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot.'/mod/dialogue/upgrade/upgradelib.php');
require_once($CFG->dirroot.'/mod/dialogue/locallib.php');

$page       = optional_param('page', 0, PARAM_INT);
$upgrade    = optional_param('upgrade', 0, PARAM_INT);
$confirm    = optional_param('confirm', 0, PARAM_INT);

if (dialogue_upgrade_is_complete()) {
    redirect(new moodle_url('/'), get_string('upgradeiscompleted', 'dialogue'), 1);
}

admin_externalpage_setup('dialogueupgradehelper');

$context = context_system::instance();

require_login();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_pagetype('standard');
$PAGE->set_title(new lang_string('modulenameplural', 'dialogue') . ' > ' .
                 new lang_string('upgradereport', 'dialogue'));
$PAGE->set_heading(new lang_string('upgradereport', 'dialogue'));

$pageparams = array('page' => $page, 'upgrade' => $upgrade);
$pageurl = new moodle_url('/mod/dialogue/upgrade/upgradereport.php', $pageparams);
$PAGE->set_url($pageurl);



if (!empty($upgrade)) {
    $returnurl = new moodle_url('/mod/dialogue/upgrade/upgradereport.php'); // used multiple places
    // get cm
    //$cm = dialogue_upgrade_get_course_module_by_dialogue($upgrade);
    $cm = get_coursemodule_from_id('dialogue', $upgrade);
    if (!$cm) {
        print_error('invalidcoursemodule');
        exit;
    }
    // confirm
    if (!$confirm or !confirm_sesskey()) {
        $confirmurl = clone($pageurl);
        $confirmurl->params(array('confirm' => 1, 'sesskey' => sesskey()));
        echo $OUTPUT->header();
        echo $OUTPUT->box_start('noticebox');
        $upgradenotice = get_string('upgradecheck', 'dialogue', $cm->name);
        $continue = new single_button($confirmurl, new lang_string('yes'));
        $cancel = new single_button($returnurl, new lang_string('no'), 'get');
        echo $OUTPUT->confirm($upgradenotice, $continue, $cancel);
        echo $OUTPUT->box_end();
        echo $OUTPUT->footer();
        exit;
    }
    // upgrade
    $result = dialogue_upgrade_course_module($cm);
    if (!$result) {
        print_error('Fail!');
    }
    // cleanup
    dialogue_upgrade_cleanup();

    redirect($returnurl);
}

// table definitions
$tablecolumns = array('id',
                      'course',
                      'name',
                      'timemodified',
                      'upgrade');

$tableheaders = array('#',
                      new lang_string('course'),
                      new lang_string('name'),
                      new lang_string('modified'),
                      ''
                      );

$baseurl = clone($pageurl);

$table = new flexible_table('dialogue-upgrade-report');
$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);
$table->define_baseurl($baseurl->out());
$table->set_attribute('cellspacing', '0');
$table->set_attribute('class', 'generaltable generalbox');

// begin output of page
echo $OUTPUT->header();
$table->setup();

// get list, setup pagination
$matches = 0;
$rs = dialogue_upgrade_get_list($page = 0, dialogue::PAGINATION_PAGE_SIZE, $matches);
$pagination = new paging_bar($matches, $page, dialogue::PAGINATION_PAGE_SIZE, $pageurl);

$upgradeurl = clone($pageurl);
$upgradestring = new lang_string('upgrade', 'dialogue');

foreach ($rs as $record) {
    $data = array();
    $data[] = $record->id;
    $data[] = $record->coursename;
    $data[] = format_text($record->dialoguename);
    $data[] = userdate($record->timemodified);
    $upgradeurl->param('upgrade', $record->id);
    $data[] = html_writer::link($upgradeurl, $upgradestring);
    $table->add_data($data);
}
// output the list
echo $OUTPUT->heading(get_string('upgradenoneedupgrade', 'dialogue', intval($matches)));
$table->print_html();
echo $OUTPUT->render($pagination);
echo $OUTPUT->footer();
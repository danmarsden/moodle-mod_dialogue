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
require_once($CFG->libdir.'/tablelib.php');

$page               = optional_param('page', 0, PARAM_INT);
$upgradedialogues   = optional_param('upgradedialogues', '', PARAM_SEQUENCE);
$confirm            = optional_param('confirm', 0, PARAM_INT);

if (dialogue_upgrade_is_complete()) {
    redirect(new moodle_url('/'), get_string('upgradeiscompleted', 'dialogue'), 1);
}

admin_externalpage_setup('dialogueupgradehelper');

$context = context_system::instance();

require_login();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_pagetype('standard');
$PAGE->set_title(get_string('modulenameplural', 'dialogue') . ' > ' .
                 get_string('upgradereport', 'dialogue'));
$PAGE->set_heading(get_string('upgradereport', 'dialogue'));

$pageparams = array('page' => $page);
$pageurl = new moodle_url('/mod/dialogue/upgrade/upgradereport.php', $pageparams);
$PAGE->set_url($pageurl);

$returnurl = new moodle_url('/mod/dialogue/upgrade/upgradereport.php'); // used multiple places

if (!empty($upgradedialogues) and $confirm and confirm_sesskey()) {
    // Set time limit to run until done
    set_time_limit(0);
    // Increase memory limit
    raise_memory_limit(MEMORY_EXTRA);

    $cmids = explode(',', $upgradedialogues);
    $count = count($cmids);
    foreach($cmids as $cmid) {
        $cm = get_coursemodule_from_id('dialogue', $cmid);
        if (!$cm) {
            print_error('invalidcoursemodule');
            exit;
        }
        echo html_writer::tag('h3', get_string('upgradingsummary', 'dialogue', $cm->name));
        $result = dialogue_upgrade_course_module($cm);
        if (!$result) {
            echo html_writer::tag('strong', get_string('upgradingresultfailed', 'dialogue'));
            exit;

        }
        echo html_writer::tag('strong', get_string('upgradingresultsuccess', 'dialogue'));

    }
    // cleanup
    dialogue_upgrade_cleanup();
    // back to report page
    redirect($returnurl, get_string('upgradedcount', 'dialogue', $count), 2);

} else {

    $form = new dialogue_upgrade_selected_form();
    if ($form->is_submitted()) {
        $data = $form->get_data();
        if ((!$confirm or !confirm_sesskey()) and !empty($data->selecteddialogues)) {
            $confirmurl = clone($pageurl);
            $confirmurl->params(array('confirm' => 1, 'sesskey' => sesskey(), 'upgradedialogues' => $data->selecteddialogues));
            echo $OUTPUT->header();
            echo $OUTPUT->box_start('noticebox');
            $upgradenotice = get_string('upgradeselectedcount', 'dialogue', count(explode(',', $data->selecteddialogues)));
            $notice = html_writer::tag('h2', $upgradenotice);
            $continue = new single_button($confirmurl, get_string('yes'));
            $cancel = new single_button($returnurl, get_string('no'), 'get');
            echo $OUTPUT->confirm($notice, $continue, $cancel);
            echo $OUTPUT->box_end();
            echo $OUTPUT->footer();
            exit;
        }
    }
    // javascript selector
    $PAGE->requires->js('/mod/dialogue/upgrade/upgrade.js');
    $PAGE->requires->js_init_call('M.mod_dialogue.upgrade.init_upgrade_table', array());
    $PAGE->requires->string_for_js('upgradenodialoguesselected', 'dialogue');
    // table definitions
    $tablecolumns = array('select',
                        'id',
                        'coursename',
                        'dialoguename',
                        'timemodified');

    $selectall = '<div class="selectall">' .
                 '<input type="checkbox" name="selectall"/>' .
                 '</div>';

    $tableheaders = array($selectall,
                        '#',
                        get_string('course'),
                        get_string('name'),
                        get_string('modified')
                        );

    $table = new flexible_table('dialogue-upgrade-report');
    $table->define_columns($tablecolumns);
    $table->define_headers($tableheaders);
    $baseurl = clone($pageurl);
    $table->define_baseurl($baseurl->out());
    $table->set_attribute('cellspacing', '0');
    $table->set_attribute('class', 'generaltable generalbox');
    $table->sortable(true, 'timemodified');
    $table->no_sorting('select');
    $table->no_sorting('dialoguename');
    $table->text_sorting('coursename');

    echo $OUTPUT->header();
    $table->setup();

    // get the order for display
    $sortby = $table->get_sql_sort();

    // get list, setup pagination
    $perpage = dialogue::PAGINATION_PAGE_SIZE;
    $matches = 0;
    $rs = dialogue_upgrade_get_list($sortby, $page, $perpage, $matches);
    $pagination = new paging_bar($matches, $page, $perpage, $pageurl);
    foreach ($rs as $record) {
        $row = array();
        $row[] = '<input type="checkbox" name="cmid" value="' . $record->id . '"/>';
        $row[] = $record->id;
        $row[] = $record->coursename;
        $row[] = format_text($record->dialoguename);
        $row[] = userdate($record->timemodified);
        $table->add_data($row);
    }
    echo $OUTPUT->heading(get_string('upgradenoneedupgrade', 'dialogue', intval($matches)));
    $table->print_html();
    echo html_writer::empty_tag('hr');
    $form->display();
    echo $OUTPUT->render($pagination);
    echo $OUTPUT->footer();
}

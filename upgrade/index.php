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

admin_externalpage_setup('dialogueupgradehelper');

$context = context_system::instance();

require_login();
require_capability('moodle/site:config', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dialogueupgradehelper', 'dialogue'));
echo html_writer::start_tag('ul');
$url = new moodle_url('/mod/dialogue/upgrade/upgradereport.php');
$name = new lang_string('upgradereport', 'dialogue');
$description = new lang_string('upgradereportdescription', 'dialogue');
echo html_writer::tag('li', html_writer::link($url, $name) . ' - ' . $description);
echo html_writer::end_tag('ul');
echo $OUTPUT->footer();
 
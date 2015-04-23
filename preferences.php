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

$id         = optional_param('id', $USER->id, PARAM_INT);    // User id; -1 if creating new user.
$returnurl  = optional_param('returnurl', null, PARAM_URL);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/dialogue/preferences.php', array('id' => $id));

require_login();

$site = get_site();
$strpreferences = get_string('preferences');
$strdialogue    = get_string('modulename', 'dialogue');
$title = "$site->shortname: $strdialogue : $strpreferences";
$PAGE->set_title($title);

$form = new \mod_dialogue\form\preference_form();
$form->set_data(
    array('returnurl'=>$returnurl, 'hideclosed'=>get_user_preferences('hideclosed'), 'sortedby'=>get_user_preferences('sortedby')));

// Setup return url.
if (!$returnurl) {
    $returnurl = $CFG->wwwroot;
} else {
    $returnurl = $CFG->wwwroot  . $returnurl;
}

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($form->is_submitted()) {
    $data = $form->get_data();
    $hideclosed = isset($data->hideclosed) ? 1 : 0;
    $sortedby = $data->sortedby;
    set_user_preference('hideclosed', $hideclosed);
    set_user_preference('sortedby', $sortedby);
    redirect($returnurl);
}


echo $OUTPUT->header();
echo $OUTPUT->heading("$strdialogue : $strpreferences", 2);
$form->display();
echo $OUTPUT->footer();
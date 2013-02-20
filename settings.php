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
 * Configure site-wide settings specific to the Dialogue modue
 * 
 * Note: the only setting currently relates to unread-post tracking - this will only be 
 * supported in your courses if you have applied the patch in CONTRIB-1134 which modifies
 * course/lib.php to check and display unread post counts in the course/topic area. 
 * If you havent applied that patch this setting will still be stored in Moodle but it
 * will have no effect on the display of your courses, ie users will not see an unread
 * posts count
 *  
 * @package dialogue
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
require_once($CFG->dirroot.'/mod/dialogue/lib.php');

// whether to provide unread post count
$settings->add(new admin_setting_configcheckbox('dialogue_trackreadentries', get_string('trackdialogue', 'dialogue'),
                   get_string('configtrackreadentries', 'dialogue'), 1));

$maxattachments = array(
    0 => get_string('none'),
    1 => '1',
    2 => '2',
    3 => '3',
    4 => '4',
    5 => '5',
    10 => '10',
    15 => '15',
    20 => '20');

$settings->add(new admin_setting_configselect('dialogue_maxattachments',
                                              get_string('maxattachments', 'dialogue'),
                                              get_string('configmaxattachmentshelp', 'dialogue'), 5, $maxattachments));


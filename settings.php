<?php

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

?>

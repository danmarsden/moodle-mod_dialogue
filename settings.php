<?php  //$Id: settings.php,v 1.1 2009/03/25 23:20:45 deeknow Exp $

require_once($CFG->dirroot.'/mod/dialogue/lib.php');

// whether to provide unread post count
$settings->add(new admin_setting_configcheckbox('dialogue_trackreadentries', get_string('trackdialogue', 'dialogue'),
                   get_string('configtrackreadentries', 'dialogue'), 1));

?>

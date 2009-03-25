<?php // $Id: lib.php,v 1.4.10.2 2009/03/25 23:23:08 deeknow Exp $

$DIALOGUE_DAYS = array (0 => 0, 7 => 7, 14 => 14, 30 => 30, 150 => 150, 365 => 365 );

// STANDARD MODULE FUNCTIONS /////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////
function dialogue_add_instance($dialogue) {
// Given an object containing all the necessary data, 
// (defined by the form in mod.html) this function 
// will create a new instance and return the id number 
// of the new instance.

    $dialogue->timemodified = time();

    return insert_record("dialogue", $dialogue);
}


//////////////////////////////////////////////////////////////////////////////////////
function dialogue_cron () {
// Function to be run periodically according to the moodle cron

    global $CFG, $USER;

    $context_cache = array();
// delete any closed conversations which have expired
    dialogue_delete_expired_conversations();

// Finds all dialogue entries that have yet to be mailed out, and mails them
    $sql = "SELECT e.* FROM {$CFG->prefix}dialogue_entries e " .
            "INNER JOIN {$CFG->prefix}dialogue d ON e.dialogueid = d.id " .
            "WHERE e.timecreated + d.edittime * 60 < ".time()." AND e.mailed = 0 "; 
    if ($entries = get_records_sql($sql)) {
        foreach ($entries as $entry) {

            echo "Processing dialogue entry $entry->id\n";

            if (! $userfrom = get_record("user", "id", "$entry->userid")) {
                mtrace("Could not find user $entry->userid\n");
                continue;
            }
            // get conversation record
            if(!$conversation = get_record("dialogue_conversations", "id", $entry->conversationid)) {
                mtrace("Could not find conversation $entry->conversationid\n");
            }
            if ($userfrom->id == $conversation->userid) {
                if (!$userto = get_record("user", "id", $conversation->recipientid)) {
                    mtrace("Could not find use $conversation->recipientid\n");
                }
            }
            else {
                if (!$userto = get_record("user", "id", $conversation->userid)) {
                    mtrace("Could not find use $conversation->userid\n");
                }
            }

            $USER->lang = $userto->lang;

            if (! $dialogue = get_record("dialogue", "id", $conversation->dialogueid)) {
                echo "Could not find dialogue id $conversation->dialogueid\n";
                continue;
            }
            if (! $course = get_record("course", "id", "$dialogue->course")) {
                echo "Could not find course $dialogue->course\n";
                continue;
            }
            if (! $cm = get_coursemodule_from_instance("dialogue", $dialogue->id, $course->id)) {
                echo "Course Module ID was incorrect\n";
            }
            if (empty($context_cache[$course->id])) {
                $context_cache[$course->id] = get_context_instance(CONTEXT_COURSE, $course->id);
            }
                       
            if (!has_capability('mod/dialogue:participate', $context_cache[$course->id], $userfrom->id) && !has_capability('mod/dialogue:manage', $context_cache[$course->id], $userfrom->id)) {
                set_field("dialogue_entries", "mailed", "1", "id", $entry->id);
                continue;  // Not an active participant
            }
            if (!has_capability('mod/dialogue:participate', $context_cache[$course->id], $userto->id) && !has_capability('mod/dialogue:manage', $context_cache[$course->id], $userto->id)) {
                set_field("dialogue_entries", "mailed", "1", "id", $entry->id);
                continue;  // Not an active participant
            }

            $strdialogues = get_string("modulenameplural", "dialogue");
            $strdialogue  = get_string("modulename", "dialogue");
    
            unset($dialogueinfo);
            $dialogueinfo->userfrom = fullname($userfrom);
            $dialogueinfo->dialogue = "$dialogue->name";
            $dialogueinfo->url = "$CFG->wwwroot/mod/dialogue/view.php?id=$cm->id";

            $postsubject = "$course->shortname: $strdialogues: $dialogue->name: ".
                get_string("newentry", "dialogue");
            $posttext  = "$course->shortname -> $strdialogues -> $dialogue->name\n";
            $posttext .= "---------------------------------------------------------------------\n";
            $posttext .= get_string("dialoguemail", "dialogue", $dialogueinfo)." \n";
            $posttext .= "---------------------------------------------------------------------\n";
            if ($userto->mailformat == 1) {  // HTML
                $posthtml = "<p><font face=\"sans-serif\">".
                "<a href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$course->shortname</a> ->".
                "<a href=\"$CFG->wwwroot/mod/dialogue/index.php?id=$course->id\">dialogues</a> ->".
                "<a href=\"$CFG->wwwroot/mod/dialogue/view.php?id=$cm->id\">$dialogue->name</a></font></p>";
                $posthtml .= "<hr /><font face=\"sans-serif\">";
                $posthtml .= "<p>".get_string("dialoguemailhtml", "dialogue", $dialogueinfo)."</p>";
                $posthtml .= "</font><hr />";
            } else {
              $posthtml = "";
            }

            if (! email_to_user($userto, $userfrom, $postsubject, $posttext, $posthtml)) {
                mtrace("Error: dialogue cron: Could not send out mail for id $entry->id to user $userto->id ($userto->email)\n");
            }
            if (! set_field("dialogue_entries", "mailed", "1", "id", $entry->id)) {
                mtrace("Could not update the mailed field for id $entry->id\n");
            }
        }
    }


    /// Find conversations sent to all participants and check for new participants
    $rs =  get_recordset_select('dialogue_conversations', 'grouping != 0 AND grouping IS NOT NULL', 'dialogueid, grouping');
    $dialogueid = 0;
    $grouping = 0;
    $groupid = null;
    $inconversation = array();
    $newusers = array();

    while ($conversation = rs_fetch_next_record($rs)) {
       
        if ($dialogueid != $conversation->dialogueid || $groupid != $conversation->groupid || $grouping != $conversation->grouping) {
            if ($dialogueid == 0 || $groupid === null) {
                $dialogueid = $conversation->dialogueid;
                $groupid = $conversation->groupid;
            }
            $cm = get_coursemodule_from_instance('dialogue', $dialogueid);
            $context = get_context_instance(CONTEXT_MODULE, $cm->id);

            $users = (array)get_users_by_capability($context, 'mod/dialogue:participate', 'u.id, u.firstname, u.lastname', null, null, null, empty($groupid) ? null:$groupid, null, null,null,false);
            $managers = (array)get_users_by_capability($context, 'mod/dialogue:manage', 'u.id, u.firstname, u.lastname', null, null, null, null, null, null,null,false);
            $dialogueid = $conversation->dialogueid;
            $groupid = $conversation->groupid;
        }

        if ($grouping != $conversation->grouping) {
            if ($grouping) {
                if ($userdiff = array_diff_key($users, $inconversation, $managers)) {
                    foreach ($userdiff as $userid => $value) {
                        $newusers[$userid.','.$grouping] = array('userid'    => $userid,
                                                                 'courseid'  => $cm->course,
                                                                 'grouping'  => $grouping);
                    }
                }
            }
            $inconversation = array();
            $grouping = $conversation->grouping;
        }

        $inconversation[$conversation->recipientid] = true;  
    }

    if (!empty($dialogueid)) {
        // Finish of any remaing users
        $cm = get_coursemodule_from_instance('dialogue', $dialogueid);
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);

        $users = (array)get_users_by_capability($context, 'mod/dialogue:participate', 'u.id, u.firstname, u.lastname', null, null, null, empty($groupid) ? null:$groupid, null, null,null,false);
        $managers = (array)get_users_by_capability($context, 'mod/dialogue:manage', 'u.id, u.firstname, u.lastname', null, null, null, null, null, null,null,false);

        if ($userdiff = array_diff_key($users, $inconversation, $managers)) {
            foreach ($userdiff as $userid => $value) {
                $newusers[$userid.','.$grouping] = array('userid'    => $userid,
                                                         'courseid'  => $cm->course,
                                                         'grouping'  => $grouping);
            }
        }
    }
    rs_close($rs);

    if (!empty($newusers)) {
        foreach($newusers as $key => $newuser) {

            begin_sql();

            course_setup($newuser['courseid']);
            if ($conversation = get_record('dialogue_conversations', 'grouping', $newuser['grouping'])) {
                if ($entry = get_records('dialogue_entries', 'conversationid', $conversation->id, 'id', '*', 0, 1)) {
                    unset($conversation->id);
                    $conversation->recipientid = $newuser['userid'];
                    $conversation->lastrecipientid = $newuser['userid'];
                    $conversation->timemodified = time();
                    $conversation->seenon = false;
                    $conversation->closed = 0;
                    $conversation = addslashes_object($conversation);

                    if (!$conversationid = insert_record('dialogue_conversations', $conversation)) {
                        rollback_sql();
                        continue;
                    }

                    $entry = array_pop($entry);
                    $srcentry = clone $entry;
                    unset($entry->id);
                    $entry->conversationid = $conversationid;
                    $entry->timecreated = $conversation->timemodified;
                    $entry->recipientid = $conversation->recipientid;
                    $entry->mailed = false;
                    $entry = addslashes_object($entry);

                    if (!$entry->id = insert_record('dialogue_entries', $entry)) {
                        rollback_sql();
                        continue;
                    }
                    
                    $read = new stdClass;
                    $lastread = time();
                    $read->conversationid = $conversationid;
                    $read->entryid        = $entry->id;
                    $read->userid         = $conversation->userid;
                    $read->firstread      = $lastread;
                    $read->lastread       = $lastread;

                    insert_record('dialogue_read', $read);

                    if ($entry->attachment) {
                        $srcdir = dialogue_file_area($srcentry);
                        $dstdir = dialogue_file_area($entry);
                        copy($srcdir.'/'.$entry->attachment, $dstdir.'/'.$entry->attachment);
                    }
                } else {
                    mtrace('Failed to find entry for conversation: '.$conversation->id);
                }
            } else {
                mtrace('Failed to find conversation: '.$conversation->id);
            }
            commit_sql();
        }
    }
    

    return true;
}



//////////////////////////////////////////////////////////////////////////////////////
function dialogue_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG;
    // have a look for new entries
    $addentrycontent = false;
    if ($logs = dialogue_get_add_entry_logs($course, $timestart)) {
        // got some, see if any belong to a visible module
        foreach ($logs as $log) {
            // Create a temp valid module structure (only need courseid, moduleid)
            $tempmod->course = $course->id;
            $tempmod->id = $log->dialogueid;
            //Obtain the visible property from the instance
            if (instance_is_visible("dialogue",$tempmod)) {
                $addentrycontent = true;
                break;
            }
        }
        // if we got some "live" ones then output them
        if ($addentrycontent) {
            print_headline(get_string('newdialogueentries', 'dialogue').':');
            foreach ($logs as $log) {
                //Create a temp valid module structure (only need courseid, moduleid)
                $tempmod->course = $course->id;
                $tempmod->id = $log->dialogueid;
                
                $user = get_record('user', 'id', $log->userid);

                //Obtain the visible property from the instance
                if (instance_is_visible("dialogue",$tempmod)) {
                    print_recent_activity_note($log->time, $user, $log->subject,
                                               $CFG->wwwroot.'/mod/dialogue/'.str_replace('&', '&amp;', $log->url));
                }
            }
        }
    }

    // have a look for open conversations
    $opencontent = false;
    if ($logs = dialogue_get_open_conversations($course)) {
        // got some, see if any belong to a visible module
        foreach ($logs as $log) {
            // Create a temp valid module structure (only need courseid, moduleid)
            $tempmod->course = $course->id;
            $tempmod->id = $log->dialogueid;
            //Obtain the visible property from the instance
            if (instance_is_visible('dialogue',$tempmod)) {
                $opencontent = true;
                break;
            }
        }
        // if we got some 'live' ones then output them
        if ($opencontent) {
            print_headline(get_string('opendialogueentries', 'dialogue').':');
            foreach ($logs as $log) {
                //Create a temp valid module structure (only need courseid, moduleid)
                $tempmod->course = $course->id;
                $tempmod->id = $log->dialogueid;
                $user = get_record('user', 'id', $log->userid);
                //Obtain the visible property from the instance
                if (instance_is_visible('dialogue',$tempmod)) {
                    print_recent_activity_note($log->time, $user, $log->name,
                                               $CFG->wwwroot.'/mod/dialogue/'.str_replace('&', '&amp;', $log->url));
                }
            }
        }
    }

    return $addentrycontent or $opencontent;
}


//////////////////////////////////////////////////////////////////////////////////////
function dialogue_user_outline($course, $user, $mod, $dialogue) {
    global $CFG;
    
    $sql = "SELECT COUNT(DISTINCT timecreated) AS count, MAX(e.timecreated) AS timecreated 
            FROM  {$CFG->prefix}dialogue_entries e
            WHERE e.userid = $user->id AND e.dialogueid = $dialogue->id ";
            
    if ($entries = get_record_sql($sql)) {
        $result->info = $entries->count.' '.get_string('posts', 'dialogue');
        $result->time = $entries->timecreated;
        return $result;
    }
    return NULL;
}

//////////////////////////////////////////////////////////////////////////////////////
function dialogue_update_instance($dialogue) {
// Given an object containing all the necessary data, 
// (defined by the form in mod.html) this function 
// will update an existing instance with new data.

    $dialogue->timemodified = time();
    $dialogue->id = $dialogue->instance;

    return update_record("dialogue", $dialogue);
}

function dialogue_delete_instance($id) {
// Given an ID of an instance of this module, 
// this function will permanently delete the instance 
// and any data that depends on it.  

    if (! $dialogue = get_record("dialogue", "id", $id)) {
        return false;
    }

    $result = true;

    if (! delete_records("dialogue_conversations", "dialogueid", $dialogue->id)) {
        $result = false;
   }

    if (! delete_records("dialogue_entries", "dialogueid", $dialogue->id)) {
        $result = false;
    }

    if (! delete_records("dialogue", "id", $dialogue->id)) {
        $result = false;
    }

    return $result;

}

function dialogue_user_complete($course, $user, $mod, $dialogue) {

    if ($conversations = dialogue_get_conversations($dialogue, $user, 'e.userid = '.$user->id)) {
        print_simple_box_start();
        $table->head = array (get_string("dialoguewith", "dialogue"),  
            get_string("numberofentries", "dialogue"), get_string("lastentry", "dialogue"),
            get_string("status", "dialogue"));
        $table->width = "100%";
        $table->align = array ("left", "center", "left", "left");
        $table->size = array ("*", "*", "*", "*");
        $table->cellpadding = 2;
        $table->cellspacing = 0;

        foreach ($conversations as $conversation) {
            if ($user->id != $conversation->userid) {
                if (!$with = get_record("user", "id", $conversation->userid)) {
                    error("User's record not found");
                }
            }
            else {
                if (!$with = get_record("user", "id", $conversation->recipientid)) {
                    error("User's record not found");
                }
            }
            $total = $conversation->total;
            if ($conversation->closed) {
                $status = get_string("closed", "dialogue");
            } else {
                $status = get_string("open", "dialogue");
            }
            $table->data[] = array(fullname($with), 
                $total, userdate($conversation->timemodified), $status);
        }
        print_table($table);
        print_simple_box_end();
    } 
    else {
        print_string("noentry", "dialogue");
     }
}



//////////////////////////////////////////////////////////////////////////////////////
// Extra functions needed by the Standard functions
//////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////
function dialogue_count_entries($dialogue, $conversation, $user = '') {
    
    if (empty($user)) {
        return count_records_select("dialogue_entries", "conversationid = $conversation->id");
    }
    else {
        return count_records_select("dialogue_entries", "conversationid = $conversation->id AND 
            userid = $user->id  ");
    }   
}


//////////////////////////////////////////////////////////////////////////////////////
function dialogue_delete_expired_conversations() {

    if ($dialogues = get_records("dialogue")) {
       foreach ($dialogues as $dialogue) {
           if ($dialogue->deleteafter) {
               $expirytime = time() - $dialogue->deleteafter * 86400;
               if ($conversations = get_records_select("dialogue_conversations",
                   "(timemodified < $expirytime) AND (dialogueid = " . $dialogue->id  . ") AND (closed = 1)")) {
                   echo "\nDeleting expired conversations for Dialogue id " . $dialogue->id; 
                   foreach ($conversations as $conversation) {
                       delete_records("dialogue_conversations", "id", $conversation->id, "dialogueid", $dialogue->id);
                       delete_records("dialogue_entries", "conversationid", $conversation->id, "dialogueid", $dialogue->id);
                   }
               }
           }
       }
    }
}


//////////////////////////////////////////////////////////////////////////////////////
function dialogue_get_add_entry_logs($course, $timestart) {
    // get the "add entry" entries and add the first and last names, we are not interested in the entries 
    // make by this user (the last condition)!
    global $CFG, $USER;
    if (!isset($USER->id)) {
        return false;
    }
    return get_records_sql("SELECT l.time, l.url, u.firstname, u.lastname, e.dialogueid, d.name, c.subject, l.userid
                             FROM {$CFG->prefix}log l,
                                {$CFG->prefix}dialogue d, 
                                {$CFG->prefix}dialogue_conversations c, 
                                {$CFG->prefix}dialogue_entries e, 
                                {$CFG->prefix}user u
                            WHERE l.time > $timestart AND l.course = $course->id AND l.module = 'dialogue'
                                AND l.action = 'add entry'
                                AND e.id = " . sql_cast_char2int('l.info') . "  
                                AND c.id = e.conversationid
                                AND (c.userid = $USER->id or c.recipientid = $USER->id)
                                AND d.id = e.dialogueid
                                AND u.id = e.userid 
                                AND e.userid <> $USER->id");
}




//////////////////////////////////////////////////////////////////////////////////////
function dialogue_get_conversations($dialogue, $user, $condition = '', $order = '') {
    global $CFG, $COURSE;
    
    if (! $cm = get_coursemodule_from_instance("dialogue", $dialogue->id, $COURSE->id)) {
        error("Course Module ID was incorrect");
    }
    
    if (!empty($condition)) {
        $condition = ' AND '.$condition;
    }
    if (empty($order)) {
        $order = "c.timemodified DESC";
    }
    if (has_capability('mod/dialogue:viewall', get_context_instance(CONTEXT_MODULE, $cm->id))) {
    	$whereuser = '';
    } else {
    	$whereuser = ' AND (c.userid = '.$user->id.' OR c.recipientid = '.$user->id.') ';
    }
    
    $sql  = "SELECT c.*, COUNT(e.id) AS total, COUNT(r.id) as readings ";
    $sql .= "FROM {$CFG->prefix}dialogue_conversations c ";
    $sql .= "LEFT JOIN {$CFG->prefix}dialogue_entries e ON e.conversationid = c.id ";
    $sql .= "LEFT JOIN {$CFG->prefix}dialogue_read r ON r.entryid = e.id AND r.userid = $user->id ";
    $sql .= "WHERE c.dialogueid = $dialogue->id $whereuser $condition ";
    $sql .= "GROUP BY c.id, c.userid, c.dialogueid, c.recipientid, c.lastid, c.lastrecipientid, c.timemodified, c.closed, c.seenon, c.ctype, c.format, c.subject, c.groupid, c.grouping ";
    $sql .= "ORDER BY $order ";

    return get_records_sql($sql);
    
    
}


//////////////////////////////////////////////////////////////////////////////////////
function dialogue_get_open_conversations($course) {
    // get the conversations which are waiting for a response for this user. 
    // Add the first and last names of the other participant
    global $CFG, $USER;
    if (empty($USER->id)) {
        return false;
    }
    if ($conversations = get_records_sql("SELECT d.name AS dialoguename, c.id, c.dialogueid, c.timemodified, c.lastid, c.userid
                            FROM {$CFG->prefix}dialogue d, {$CFG->prefix}dialogue_conversations c
                            WHERE d.course = $course->id
                                AND c.dialogueid = d.id
                                AND (c.userid = $USER->id OR c.recipientid = $USER->id)
                                AND c.lastid != $USER->id
                                AND c.closed =0")) {
        
         foreach ($conversations as $conversation) {
            if (!$user = get_record("user", "id", $conversation->lastid)) {
                error("Get open conversations: user record not found");
            }
            if (!$cm = get_coursemodule_from_instance("dialogue", $conversation->dialogueid, $course->id)) {
                error("Course Module ID was incorrect");
            }
            $entry[$conversation->id]->dialogueid = $conversation->dialogueid;
            $entry[$conversation->id]->time = $conversation->timemodified;
            $entry[$conversation->id]->url = "view.php?id=$cm->id";
            $entry[$conversation->id]->firstname = $user->firstname; 
            $entry[$conversation->id]->lastname = $user->lastname;
            $entry[$conversation->id]->name = $conversation->dialoguename;
            $entry[$conversation->id]->userid = $conversation->userid;
        }
        return $entry;
    }
    return;
}


//////////////////////////////////////////////////////////////////////////////////////
function dialogue_get_user_entries($dialogue, $user) {
    global $CFG;
    return get_records_select("dialogue_entries", "dialogueid = $dialogue->id AND userid = $user->id",
                "timecreated DESC");
}


function dialogue_add_attachment($entry, $inputname, &$message) {
	global $CFG, $COURSE;
    
    require_once($CFG->dirroot.'/lib/uploadlib.php');
    $um = new upload_manager($inputname,true,false,$COURSE,false,0,true,true);
    $dir = dialogue_file_area_name($entry);
    if ($um->process_file_uploads($dir)) {
        $message .= $um->get_errors();
        return $um->get_new_filename();
    }
    $message .= $um->get_errors();
    return null;
}

function dialogue_file_area_name($entry) {
    global $CFG, $COURSE;

    return "$COURSE->id/$CFG->moddata/dialogue/$entry->dialogueid/$entry->id";
}

function dialogue_file_area($entry) {
    return make_upload_directory( dialogue_file_area_name($entry) );
}

function dialogue_print_attachments($entry) {
    global $CFG;
    require_once($CFG->dirroot.'/lib/filelib.php');
    
    $filearea = dialogue_file_area_name($entry);

    $imagereturn = "";
    $output = "";

    if ($basedir = dialogue_file_area($entry)) {
        if ($files = get_directory_list($basedir)) {
            $output .= '<p>'.get_string("attachment", "dialogue");
            foreach ($files as $file) {
                $icon = mimeinfo("icon", $file);
                $type = mimeinfo("type", $file);
                if ($CFG->slasharguments) {
                    $ffurl = "$CFG->wwwroot/file.php/$filearea/$file";
                } else {
                    $ffurl = "$CFG->wwwroot/file.php?file=/$filearea/$file";
                }
                $image = "<img src=\"$CFG->pixpath/f/$icon\" class=\"icon\" alt=\"\" />";
                $output .= "<a href=\"$ffurl\">$image</a> ";
                $output .= "<a href=\"$ffurl\">$file</a><br />";

            }
            $output .= '</p>';
        }
    }

    
    return $output;

}


//////////////////////////////////////////////////////////////////////////////////////
function dialogue_count_unread_entries($dialogueid, $userid, $cm) {
    global $CFG;
    static $hascapviewall;

    if (!isset($hascapviewall)) {
        $hascapviewall = has_capability('mod/dialogue:viewall', get_context_instance(CONTEXT_MODULE, $cm->id));
    }

    if ($hascapviewall) {
        $whereuser = '';
    } else {
        $whereuser = ' AND (c.userid = '.$userid.' OR c.recipientid = '.$userid.') ';
    }

    $sql = "SELECT COUNT(e.id) 
            FROM {$CFG->prefix}dialogue_conversations c
                LEFT JOIN {$CFG->prefix}dialogue_entries e ON c.id = e.conversationid 
                LEFT JOIN {$CFG->prefix}dialogue_read r ON e.id = r.entryid AND r.userid = $userid 
            WHERE r.id IS NULL AND c.closed = 0 AND c.dialogueid = $dialogueid $whereuser ";

    return(count_records_sql($sql));
}

/**
 * Determine if a user can track dialogue entries. Checks the site dialogue
 * activity setting and the user's personal preference for trackForums
 * which is a similar requirement/preference so we treat them as equals.
 * This is closely modelled on similar function from course/lib.php
 *
 * @param mixed $userid The user object to check for (optional).
 * @return boolean
 */
function dialogue_can_track_dialogue($user=false) {
    global $USER, $CFG;

    // return unless enabled at site level
    if (empty($CFG->dialogue_trackreadentries)) {
        return false;
    }

    // default to logged if no user passed as param
    if ($user === false) {
        $user = $USER;
    }

    // dont allow guests to track
    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    // finally if user has trackForums set then allow tracking
    return true && !empty($user->trackforums);
}

?>

<?php

/**
 * Library of functions for the Dialogue module
 * 
 * @package dialogue
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

$DIALOGUE_DAYS = array (0 => 0, 7 => 7, 14 => 14, 30 => 30, 150 => 150, 365 => 365 );

// STANDARD MODULE FUNCTIONS /////////////////////////////////////////////////////////

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $dialogue the data that came from the form.
 * @return mixed the id of the new instance on success,
 *          false or a string error message on failure.
 */
function dialogue_add_instance($dialogue) {
    global $DB;

    $dialogue->timemodified = time();

    return $DB->insert_record('dialogue', $dialogue);
}

/**
 * Function to be run periodically according to the moodle cron
 * Mails new conversations out to participants, checks for any new
 * participants, and cleans up expired/closed conversations
 * @return   bool   true when complete
 */
function dialogue_cron() {

    global $DB, $CFG, $USER;

    $context_cache = array();
    // delete any closed conversations which have expired
    dialogue_delete_expired_conversations();

    // Finds all dialogue entries that have yet to be mailed out, and mails them
    $sql =  "SELECT e.* FROM {dialogue_entries} e ".
            "INNER JOIN {dialogue} d ON e.dialogueid = d.id ".
            "WHERE e.timecreated + d.edittime * 60 < :timenow AND e.mailed = 0 ";
    
    if ($entries = $DB->get_records_sql($sql, array('timenow' => time()))) {
        foreach ($entries as $entry) {

            echo "Processing dialogue entry $entry->id\n";

            if (! $userfrom = $DB->get_record('user', array('id' => $entry->userid))) {
                mtrace("Could not find user $entry->userid\n");
                continue;
            }
            // get conversation record
            if (! $conversation = $DB->get_record('dialogue_conversations', array('id' =>
                                            $entry->conversationid))) {
                mtrace("Could not find conversation $entry->conversationid\n");
            }
            if ($userfrom->id == $conversation->userid) {
                if (! $userto = $DB->get_record('user', array('id' => $conversation->recipientid))) {
                    mtrace("Could not find use $conversation->recipientid\n");
                }
            } else {
                if (! $userto = $DB->get_record('user', array('id' => $conversation->userid))) {
                    mtrace("Could not find use $conversation->userid\n");
                }
            }

            $USER->lang = $userto->lang;

            if (! $dialogue = $DB->get_record('dialogue', array('id' => $conversation->dialogueid))) {
                echo "Could not find dialogue id $conversation->dialogueid\n";
                continue;
            }
            if (! $course = $DB->get_record('course', array('id' => $dialogue->course))) {
                echo "Could not find course $dialogue->course\n";
                continue;
            }
            if (! $cm = get_coursemodule_from_instance('dialogue', $dialogue->id, $course->id)) {
                echo "Course Module ID was incorrect\n";
            }
            if (empty($context_cache[$course->id])) {
                $context_cache[$course->id] = get_context_instance(CONTEXT_COURSE, $course->id);
            }

            if (! has_capability('mod/dialogue:participate', $context_cache[$course->id], $userfrom->id)
                 && ! has_capability('mod/dialogue:manage', $context_cache[$course->id], $userfrom->id)) {
                $DB->set_field('dialogue_entries', 'mailed', '1', array('id' => $entry->id));
                continue; // Not an active participant
            }
            if (! has_capability('mod/dialogue:participate', $context_cache[$course->id], $userto->id)
                && ! has_capability('mod/dialogue:manage', $context_cache[$course->id], $userto->id)) {
                $DB->set_field('dialogue_entries', 'mailed', '1', array('id' => $entry->id));
                continue; // Not an active participant
            }

            $strdialogues = get_string('modulenameplural', 'dialogue');
            $strdialogue = get_string('modulename', 'dialogue');

            $dialogueinfo = new object();
            $dialogueinfo->userfrom = fullname($userfrom);
            $dialogueinfo->dialogue = format_string($dialogue->name);
            $dialogueinfo->url = "$CFG->wwwroot/mod/dialogue/view.php?id=$cm->id";

            $postsubject = "$course->shortname: $strdialogues: $dialogueinfo->dialogue: ".
                                         get_string('newentry', 'dialogue');
            $posttext = "$course->shortname -> $strdialogues -> $dialogueinfo->dialogue\n";
            $posttext .= "---------------------------------------------------------------------\n";
            $posttext .= get_string('dialoguemail', 'dialogue', $dialogueinfo)." \n";
            $posttext .= "---------------------------------------------------------------------\n";
            if ($userto->mailformat == 1) { // HTML
                $posthtml = "<p><font face=\"sans-serif\">".
                "<a href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$course->shortname</a> ->".
                "<a href=\"$CFG->wwwroot/mod/dialogue/index.php?id=$course->id\">dialogues</a> ->".
                "<a href=\"$CFG->wwwroot/mod/dialogue/view.php?id=$cm->id\">" . $dialogueinfo->dialogue . "</a></font></p>";
                $posthtml .= "<hr /><font face=\"sans-serif\">";
                $posthtml .= '<p>'.get_string('dialoguemailhtml', 'dialogue', $dialogueinfo).'</p>';
                $posthtml .= "</font><hr />";
            } else {
                $posthtml = '';
            }
            
            if (! email_to_user($userto, $userfrom, $postsubject, $posttext, $posthtml)) {
                mtrace("Error: dialogue cron: Could not send out mail for id $entry->id to user $userto->id ($userto->email)\n");
            }
            if (! $DB->set_field('dialogue_entries', 'mailed', '1', array('id' => $entry->id))) {
                mtrace("Could not update the mailed field for id $entry->id\n");
            }
        }
    }

    /// Find conversations sent to all participants and check for new participants
    $conversation_rs = $DB->get_recordset_select('dialogue_conversations',
                                    'grouping != 0 AND grouping IS NOT NULL',
                                    null,
                                    'dialogueid, grouping');
    $dialogueid = 0;
    $grouping = 0;
    $groupid = null;
    $inconversation = array();
    $newusers = array();

    foreach($conversation_rs as $conversation) {
        if ($dialogueid != $conversation->dialogueid
             || $groupid != $conversation->groupid
             || $grouping != $conversation->grouping) {
            if ($dialogueid == 0 || $groupid === null) {
                $dialogueid = $conversation->dialogueid;
                $groupid = $conversation->groupid;
            }
            $cm = get_coursemodule_from_instance('dialogue', $dialogueid);
            $context = get_context_instance(CONTEXT_MODULE, $cm->id);

            $users = (array) get_users_by_capability($context, 'mod/dialogue:participate',
                                                     'u.id, u.firstname, u.lastname', null, null, null, 
                                                     empty($groupid) ? null : $groupid, 
                                                     null, null, null, false);
            $managers = (array) get_users_by_capability($context, 'mod/dialogue:manage',
                                                     'u.id, u.firstname, u.lastname', 
                                                     null, null, null, null, null, null, null, false);
            $dialogueid = $conversation->dialogueid;
            $groupid = $conversation->groupid;
        }

        if ($grouping != $conversation->grouping) {
            if ($grouping) {
                if ($userdiff = array_diff_key($users, $inconversation, $managers)) {
                    foreach ($userdiff as $userid => $value) {
                        $newusers[$userid.','.$grouping] = array (
                            'userid' => $userid,
                            'courseid' => $cm->course,
                            'grouping' => $grouping
                        );
                    }
                }
            }
            $inconversation = array();
            $grouping = $conversation->grouping;
        }

        $inconversation[$conversation->recipientid] = true;
    }

    if (! empty($dialogueid)) {
        // Finish of any remaing users
        $cm = get_coursemodule_from_instance('dialogue', $dialogueid);
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);

        $users = (array) get_users_by_capability($context, 'mod/dialogue:participate', 
                                                 'u.id, u.firstname, u.lastname', 
                                                 null, null, null, 
                                                 empty($groupid) ? null : $groupid, 
                                                 null, null, null, false);
        $managers = (array) get_users_by_capability($context, 'mod/dialogue:manage',
                                                 'u.id, u.firstname, u.lastname', 
                                                  null, null, null, 
                                                  null, null, null, null, false);

        if ($userdiff = array_diff_key($users, $inconversation, $managers)) {
            foreach ($userdiff as $userid => $value) {
                $newusers[$userid.','.$grouping] = array (
                    'userid' => $userid,
                    'courseid' => $cm->course,
                    'grouping' => $grouping
                );
            }
        }
    }
    $conversation_rs->close();

    if (! empty($newusers)) {
        foreach ($newusers as $key => $newuser) {


            
            $transaction = $DB->start_delegated_transaction();

            if ($conversations = $DB->get_records('dialogue_conversations', array('grouping' =>
                                           $newuser['grouping']), 'id', '*', 0, 1)) {
                $conversation = array_pop($conversations);  // we only need one to get the common field values
                if ($entry = $DB->get_records('dialogue_entries', array('conversationid'=>$conversation->id),'id', '*', 0, 1)) {



                    unset ($conversation->id);
                    $conversation->recipientid = $newuser['userid'];
                    $conversation->lastrecipientid = $newuser['userid'];
                    $conversation->timemodified = time();
                    $conversation->seenon = false;
                    $conversation->closed = 0;
                    
                    try {
                        $conversationid = $DB->insert_record('dialogue_conversations', $conversation);
                    } catch (Exception $e) {
                        $transaction->rollback($e);
                        continue;
                    }


                    $entry = array_pop($entry);
                    $srcentry = clone ($entry);
                    unset ($entry->id);
                    $entry->conversationid = $conversationid;
                    $entry->timecreated = $conversation->timemodified;
                    $entry->recipientid = $conversation->recipientid;
                    $entry->mailed = false;
                    
                    try {
                        $entry->id = $DB->insert_record('dialogue_entries', $entry);
                    } catch (Exception $e) {
                        $transaction->rollback($e);
                        continue;
                    }
                    /// Are there embedded images
                    $fs = get_file_storage();
                    $oldcm = get_coursemodule_from_instance('dialogue', $srcentry->dialogueid);
                    $oldcontext = get_context_instance(CONTEXT_MODULE, $oldcm->id);
                    $oldentryid = $srcentry->id;
 
                    if ($files = $fs->get_area_files($oldcontext->id, 'mod_dialogue', 'entry', $oldentryid)) {
                        foreach($files as $file){
                            $fs->create_file_from_storedfile(array('contextid' => $oldcontext->id,
                                                                   'itemid' => $entry->id), $file);
                        }
                    }
                    // Are there attachment(s)
                    if ($entry->attachment) {
                        if ($files = $fs->get_area_files($oldcontext->id, 'mod_dialogue', 'attachment', $oldentryid)) {
                            foreach($files as $file){
                                $fs->create_file_from_storedfile(array('contextid' => $oldcontext->id,
                                                                       'itemid' => $entry->id), $file);
                            }
                        }
                    }
                    $read = new stdClass;
                    $lastread = time();
                    $read->conversationid = $conversationid;
                    $read->entryid = $entry->id;
                    $read->userid = $conversation->userid;
                    $read->firstread = $lastread;
                    $read->lastread = $lastread;

                    $DB->insert_record('dialogue_read', $read);

                } else {
                    mtrace('Failed to find entry for conversation: '.$conversation->id);
                }
            } else {
                mtrace('Failed to find conversation: '.$conversation->id);
            }
            
            $transaction->allow_commit();
        }
    }

    return true;
}


/**
 * Return a list of 'view' actions to be reported on in the participation reports
 * @return  array   of view action labels
 */
function dialogue_get_view_actions() {
    return array('view');
}


/**
 * Return a list of 'post' actions to be reported on in the participation reports
 * @return  array   of post action labels
 */
function dialogue_get_post_actions() {
    return array('add entry','edit_entry','open');
}


/**
 * Prints any recent dialogue activity since a given time
 * @param   object  $course
 * @param   bool    $viewfullnames capability
 * @param   timestamp   $timestart
 * @return  bool    success
 */
function dialogue_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $DB, $OUTPUT;

    // have a look for new entries
    $addentrycontent = false;
    $tempmod = new object();         // Create a temp valid module structure (only need courseid, moduleid)
    $tempmod->course = $course->id;
    if ($logs = dialogue_get_add_entry_logs($course, $timestart)) {
        // got some, see if any belong to a visible module
        foreach ($logs as $log) {
            $tempmod->id = $log->dialogueid;
            //Obtain the visible property from the instance
            if (instance_is_visible('dialogue', $tempmod)) {
                $addentrycontent = true;
                break;
            }
        }
        // if we got some "live" ones then output them
        if ($addentrycontent) {
            echo $OUTPUT->heading(get_string('newdialogueentries', 'dialogue').':', 3);
            foreach ($logs as $log) {
                $tempmod->id = $log->dialogueid;
                $user = $DB->get_record('user', array('id' => $log->userid));
                //Obtain the visible property from the instance
                if (instance_is_visible('dialogue', $tempmod)) {
                    print_recent_activity_note($log->time, $user, $log->subject, $CFG->wwwroot .
                                               '/mod/dialogue/'.str_replace('&', '&amp;', $log->url));
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
            $tempmod->id = $log->dialogueid;
            //Obtain the visible property from the instance
            if (instance_is_visible('dialogue', $tempmod)) {
                $opencontent = true;
                break;
            }
        }
        // if we got some 'live' ones then output them
        if ($opencontent) {
            echo $OUTPUT->heading(get_string('opendialogueentries', 'dialogue').':', 3);
            foreach ($logs as $log) {
                //Create a temp valid module structure (only need courseid, moduleid)
                $tempmod->id = $log->dialogueid;
                $user = $DB->get_record('user', array('id' => $log->userid));
                //Obtain the visible property from the instance
                if (instance_is_visible('dialogue', $tempmod)) {
                    print_recent_activity_note($log->time, $user, $log->name, $CFG->wwwroot .
                                               '/mod/dialogue/'.str_replace('&', '&amp;', $log->url));
                }
            }
        }
    }

    return $addentrycontent or $opencontent;
}

/**
 * Return a small object with summary information about what a user has done 
 * with a given particular instance of this module
 * 
 * Used for user activity reports.
 * @param   object  $course
 * @param   object  $user
 * @param   object  $dialogue
 * $return->time = the time they did it
 * $return->info = a short text description
 */
function dialogue_user_outline($course, $user, $mod, $dialogue) {
    global $DB, $CFG;

    $sql = "SELECT COUNT(DISTINCT timecreated) AS count, MAX(e.timecreated) AS timecreated".
           " FROM  {$CFG->prefix}dialogue_entries e".
           " WHERE e.userid = :userid AND e.dialogueid = :dialogueid ";

    if ($entries = $DB->get_record_sql($sql, array('userid' => $user->id, 'dialogueid' => $dialogue->id))) {
        $result = new object();
        $result->info = $entries->count.' '.get_string('posts', 'dialogue');
        $result->time = $entries->timecreated;
        return $result;
    }
    return NULL;
}

/**
 * Updates a Dialogue
 * 
 * Given an object containing all the necessary data, (defined by the form in 
 * mod.html) this function will update an existing instance with new data.
 * @param   object  $dialogue object
 * @return  bool    true on success
 */
function dialogue_update_instance($dialogue) {
    global $DB;

    $dialogue->timemodified = time();
    $dialogue->id = $dialogue->instance;

    return $DB->update_record('dialogue', $dialogue);
}

/**
 * Deletes a Dialogue activity
 * 
 * Given an ID of an instance of this module, this function will permanently 
 * delete the instance and any data that depends on it.  
 * @param   int     id of the dialogue object to delete
 * @return  bool    true on success, false if not
 */
function dialogue_delete_instance($id) {
    global $DB;

    if (! $dialogue = $DB->get_record('dialogue', array('id' => $id))) {
        return false;
    }

    $result = true;

    if (! $DB->delete_records('dialogue_conversations', array('dialogueid' => $dialogue->id))) {
        $result = false;
    }

    if (! $DB->delete_records('dialogue_entries', array('dialogueid' => $dialogue->id))) {
        $result = false;
    }

    if (! $DB->delete_records('dialogue', array('id' => $dialogue->id))) {
        $result = false;
    }

    return $result;

}

/**
 * Print complete information about the user's interaction with the Dialogue
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $dialogue
 */
function dialogue_user_complete($course, $user, $mod, $dialogue) {
    global $DB, $OUTPUT;

    if ($conversations = dialogue_get_conversations($dialogue, $user, array('e.userid=?'),array($user->id))) {
        $table = new html_table();
        $table->head = array (
            get_string('dialoguewith', 'dialogue', ''), //Hack alert
            get_string('numberofentries', 'dialogue'),
            get_string('lastentry', 'dialogue'),
            get_string('status', 'dialogue')
        );
        $table->width = '100%';
        $table->align = array ('left', 'center', 'left', 'left');
        $table->size = array ('*', '*', '*', '*');
        $table->cellpadding = 2;
        $table->cellspacing = 0;

        foreach ($conversations as $conversation) {
            if ($user->id != $conversation->userid) {
                if (! $with = $DB->get_record('user', array('id' => $conversation->userid))) {
                    print_error("User's record not found");
                }
            } else {
                if (! $with = $DB->get_record('user', array('id' => $conversation->recipientid))) {
                    print_error("User's record not found");
                }
            }
            $total = $conversation->total;
            if ($conversation->closed) {
                $status = get_string('closed', 'dialogue');
            } else {
                $status = get_string('open', 'dialogue');
            }
            $table->data[] = array (
                fullname($with),
                $total,
                userdate($conversation->timemodified),
                $status
            );
        }
        echo html_writer::table($table);
    } else {
        print_string('noentry', 'dialogue');
    }
}

//////////////////////////////////////////////////////////////////////////////////////
// Extra functions needed by the Standard functions
//////////////////////////////////////////////////////////////////////////////////////

/**
 * Count the number of entries in a specific Dialogue conversation
 * 
 * @param   object  $dialogue
 * @param   object  $conversation
 * @param   object  $user   optionally filter by user
 * @return  int     count of records found
 */
function dialogue_count_entries($dialogue, $conversation, $user = '') {
    global $DB;

    if (empty($user)) {
        return $DB->count_records_select('dialogue_entries', "conversationid = :conversationid", array('conversationid' => $conversation->id));
    } else {
        return $DB->count_records_select('dialogue_entries',
                                    "conversationid = :conversationid AND userid = :userid  ", array('couversationid' => $conversation->id,
                                                                                                       'userid' => $user->id));
    }
}

/**
 * Delete conversations that have expired
 * 
 * Takes no paramaters. Checks the entire dialogue conversations table looking for 
 * expired entries and removes them. Called by dialogue_cron()
 */
function dialogue_delete_expired_conversations() {
    global $DB;

    if ($dialogues = $DB->get_records('dialogue')) {
        foreach ($dialogues as $dialogue) {
            if ($dialogue->deleteafter) {
                $expirytime = time() - $dialogue->deleteafter * 86400;
                if ($conversations = $DB->get_records_select('dialogue_conversations',
                    "(timemodified < :expirytime) AND (dialogueid = :dialogueid) AND (closed = 1)",
                    array('expirytime' => $expirytime, 'dialogueid' => $dialogue->id))) {
                    echo "\nDeleting expired conversations for Dialogue id ".$dialogue->id;
                    foreach ($conversations as $conversation) {
                        $DB->delete_records('dialogue_conversations', array('id' =>
                                       $conversation->id, 'dialogueid' => $dialogue->id));
                        $DB->delete_records('dialogue_entries', array('conversationid' =>
                                       $conversation->id, 'dialogueid' => $dialogue->id));
                    }
                }
            }
        }
    }
}

/**
 * Get rows from the log related to the user's "add entry" activity
 *  
 * Get the "add entry" entries and add the first and last names, we are not 
 * interested in the entries make by this user (the last condition)!
 * Called from get_recent_activity()
 * @param   object  $course
 * @param   timestamp   $timestart  filter by entries later than this date/time
 * @return  array   of log fields and related user fields
 * @todo  recipient id not set in entries table if opener - weird shiz - I love this
 */
function dialogue_get_add_entry_logs($course, $timestart) {
    global $DB, $CFG, $USER;

    if (! isset($USER->id)) {
        return false;
    }

    $sql = "SELECT l.id, l.time, l.userid, l.cmid, l.url, dc.dialogueid, dc.subject, de.recipientid
            FROM {log} l
                JOIN {course_modules} cm
                    ON cm.id = l.cmid
                JOIN {dialogue_conversations} dc
                    ON dc.dialogueid = cm.instance
                JOIN {dialogue_entries} de
                    ON de.conversationid = dc.id
            WHERE l.course = ?
            AND l.module = 'dialogue'
            AND l.action = 'add entry' 
            AND l.time > ?
            AND de.recipientid = ? 
            AND l.userid <> dc.recipientid";

    $addentries = $DB->get_records_sql($sql, array($course->id, $timestart, $USER->id));

    return $addentries;
}

/**
 * Get a set of dialogue conversations records for a given user
 * 
 * @param   object  $dialogue
 * @param   object  $user
 * @param   string  $condition to be included in WHERE clause to be used in sql
 * @param   string  $order by clause to be used in sql
 * @param   int     $groupid    of the group to filter conversations by (default: 0)
 * @return  array   recordset of conversations
 */
function dialogue_get_conversations($dialogue, $user, $condition=array(), $cond_params=array(), $order='', $groupid=0) {
    global $CFG, $COURSE, $DB;

    $params = array($user->id, $dialogue->id);

    if (! $cm = get_coursemodule_from_instance('dialogue', $dialogue->id, $COURSE->id)) {
        print_error('Course Module ID was incorrect');
    }
    if (empty($order)) {
        $order = 'c.timemodified DESC';
    }
    if (has_capability('mod/dialogue:viewall', get_context_instance(CONTEXT_MODULE, $cm->id))) {
        $whereuser = '';
    } else {
        $whereuser = ' AND (c.userid = ? OR c.recipientid = ?) ';
        $params[] = $user->id;
        $params[] = $user->id;
    }
    if (count($condition) > 0) {
        $condition = ' AND '.implode(' AND ', $condition);
        $params = array_merge($params, $cond_params);
    }
    // ULPGC ecastro enforce groups use
    if($groupid) {
        $members = groups_get_members($groupid, 'u.id');
        if($members) {
            $list = '( '.implode(', ', array_keys($members)).' )';
        } else {
            $list = '( 0 ) ';
        }
        $whereuser .= " AND (c.userid IN $list OR c.recipientid IN $list )";
    }

    $sql = "SELECT c.*, COUNT(e.id) AS total, COUNT(r.id) as readings ".
           "FROM {dialogue_conversations} c ".
           "LEFT JOIN {dialogue_entries} e ON e.conversationid = c.id ".
           "LEFT JOIN {dialogue_read} r ON r.entryid = e.id AND r.userid = ? ".
           "WHERE c.dialogueid = ? $whereuser $condition ".
           "GROUP BY c.id, c.userid, c.dialogueid, c.recipientid, c.lastid, c.lastrecipientid, c.timemodified, c.closed, c.seenon, c.ctype, c.format, c.subject, c.groupid, c.grouping ".
           "ORDER BY $order ";
    $conversations = $DB->get_records_sql($sql, $params);

    return $conversations;
}



/**
 * get the conversations which are waiting for a response for this user.
 * 
 * Select conversations which this user has initiated or that they are a recipient
 * for. Also add the first and last names of the other participant
 * @param   object  $course
 * @return  array   of matching conversation objects
 */

function dialogue_get_open_conversations($course) {
    global $DB, $CFG, $USER;

    if (empty($USER->id)) {
        return false;
    }
    if ($conversations = $DB->get_records_sql("SELECT c.id, d.name AS dialoguename, c.dialogueid, c.timemodified, c.lastid, c.userid".
                                              " FROM {dialogue} d, {dialogue_conversations} c".
                                              " WHERE d.course = ?".
                                              " AND c.dialogueid = d.id".
                                              " AND (c.userid = ? OR c.recipientid = ?)".
                                              " AND c.lastid != ?".
                                              " AND c.closed =0", array($course->id, $USER->id, $USER->id, $USER->id))) {
        $entry = array();
        foreach ($conversations as $conversation) {
            if (! $user = $DB->get_record('user', array('id' => $conversation->lastid))) {
                // @todo print_error("Get open conversations: user record not found");
                continue;
            }
            if (! $cm = get_coursemodule_from_instance('dialogue', $conversation->dialogueid, $course->id)) {
                print_error('Course Module ID was incorrect');
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

/**
 * Get a list of Dialogue entries for a user ordered by most recently created
 * 
 * @param   object  $dialogue
 * @param   object  $user
 * @return  array   of "dialogue_entries" records
 */
function dialogue_get_user_entries($dialogue, $user) {
    global $DB, $CFG;
    $sqlparams = array('dialogueid' => $dialogue->id, 'userid' => $user->id);
    return $DB->get_records_select('dialogue_entries', "dialogueid = :dialogueid AND userid = :userid",
                                   'timecreated DESC', $sqlparams);
}

/**
 * Saves an uploaded Dialogue attachment to the moddata directory
 *  
 * @param   
 * @param   
 * @param   
 * @return  
 */
function dialogue_add_attachment($draftitemid, $contextid, $entryid) {
    global $DB, $CFG, $COURSE;

    
    $info = file_get_draft_area_info($draftitemid);
    $present = ($info['filecount']>0) ? '1' : '';


    file_save_draft_area_files($draftitemid, $contextid, 'mod_dialogue', 'attachment', $entryid);
    return $DB->set_field('dialogue_entries', 'attachment', $present, array('id'=>$entryid));
}


/**
 * Returns attachments as formated text/html optionally with separate images
 *
 * @global object
 * @global object
 * @global object
 * @param object $entry
 * @param object $cm
 * @param string $type html/text/separateimages
 * @return mixed string or array of (html text withouth images and image HTML)
 */
function dialogue_print_attachments($entry, $cm, $type) {
    global $CFG, $DB, $USER, $OUTPUT;

    if (empty($entry->attachment)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!in_array($type, array('separateimages', 'html', 'text'))) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }
    $strattachment = get_string('attachment', 'dialogue');

    $fs = get_file_storage();

    $imagereturn = '';
    $output = '';

    if ($files = $fs->get_area_files($context->id, 'mod_dialogue', 'attachment', $entry->id, "timemodified", false)) {

        foreach ($files as $file) {
            $filename = $file->get_filename();
            $mimetype = $file->get_mimetype();
            $iconimage = '<img src="'.$OUTPUT->pix_url(file_mimetype_icon($mimetype)).'" class="icon" alt="'.$mimetype.'" />';
            $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/mod_dialogue/attachment/'.$entry->id.'/'.$filename);

            if ($type == 'html') {
                $output .= "<a href=\"$path\">$iconimage</a> ";
                $output .= "<a href=\"$path\">".s($filename)."</a>";
                $output .= "<br />";

            } else if ($type == 'text') {
                $output .= "$strattachment ".s($filename).":\n$path\n";

            } else { //'returnimages'
                if (in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png'))) {
                    // Image attachments don't get printed as links
                    $imagereturn .= "<br /><img src=\"$path\" alt=\"\" />";

                } else {
                    $output .= "<a href=\"$path\">$iconimage</a> ";
                    $output .= format_text("<a href=\"$path\">".s($filename)."</a>", FORMAT_HTML, array('context'=>$context));
                    $output .= '<br />';
                }
            }
        }
    }

    if ($type !== 'separateimages') {
        return $output;

    } else {
        return array($output, $imagereturn);
    }
}

/**
 * Serves the dialogue attachments. Implements needed access control ;-)
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
function dialogue_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $fileareas = array('attachment', 'entry');
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $itemid = (int)array_shift($args);

    if (!$entry = $DB->get_record('dialogue_entries', array('id'=>$itemid))) {
        return false;
    }

    if (!$conversation = $DB->get_record('dialogue_conversations', array('id'=>$entry->conversationid))) {
        return false;
    }

    if (!$dialogue = $DB->get_record('dialogue', array('id'=>$cm->instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_dialogue/$filearea/$itemid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }



    
    // Make sure groups allow this user to see this file
    if ($conversation->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
        if (!groups_group_exists($conversation->groupid)) { // Can't find group
            return false;                           // Be safe and don't send it to anyone
        }

        if (!groups_is_member($conversation->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
            // do not send posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
            return false;
        }
    }
    
    // finally send the file
    send_stored_file($file, 0, 0, true); // download MUST be forced - security!
}


/**
 * Count number of unread entries a user has for this dialogue
 * 
 * This function only used if site has patched their /course/lib.php to show
 * unread posts in the topic/section areas on the course page.
 * @param   int $cm course module
 * @param   int $userid
 * @return  int count of unread entries
 */
function dialogue_count_unread_entries($cm, $userid) {
    global $CFG, $DB;
    static $hascapviewall;

    if (! isset($hascapviewall)) {
        $hascapviewall = has_capability('mod/dialogue:viewall', get_context_instance(CONTEXT_MODULE, $cm->id));
    }
    $dialogueid = $cm->instance;
    $params = array($userid, $dialogueid);
    if ($hascapviewall) {
        $whereuser = '';
    } else {
        $whereuser = ' AND (c.userid = ? OR c.recipientid = ?) ';
        $params[] = $userid;
        $params[] = $userid;
    }

    $sql = "SELECT COUNT(e.id)".
           " FROM {dialogue_conversations} c".
           " LEFT JOIN {dialogue_entries} e ON c.id = e.conversationid".
           " LEFT JOIN {dialogue_read} r ON e.id = r.entryid AND r.userid = ?".
           " WHERE r.id IS NULL AND c.closed = 0 AND c.dialogueid = ? $whereuser ";

    return ($DB->count_records_sql($sql, $params));
}

/**
 * Determine if a user can track dialogue entries. 
 * 
 * Checks the site dialogue activity setting and the user's personal preference 
 * for trackForums which is a similar requirement/preference so we treat them 
 * as equals. This is closely modelled on similar function from course/lib.php
 *
 * @param mixed $userid The user object to check for (optional).
 * @return boolean
 */
function dialogue_can_track_dialogue($user = false) {
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
    return true && ! empty($user->trackforums);
}

/**
 * Indicates API features that the dialogue supports.
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function dialogue_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_GROUPMEMBERSONLY:        return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
        case FEATURE_COMPLETION_HAS_RULES:    return false;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_RATE:                    return false;
        case FEATURE_BACKUP_MOODLE2:          return true;

        default: return null;
    }
}


/**
 * Adds information about unread messages, that is only required for the course view page (and
 * similar), to the course-module object.
 * @param cm_info $cm Course-module object
 */
function dialogue_cm_info_view(cm_info $cm) {
    global $CFG, $USER;

    // Get tracking status (once per request)
    static $initialised;
    static $usetracking, $strunreaddialoguesone;
    if (!isset($initialised)) {
        if ($usetracking = dialogue_can_track_dialogue()) {
            $strunreaddialoguesone = get_string('unreadone', 'dialogue');
        }
        $initialised = true;
    }

    if ($usetracking) {
        if ($unread = dialogue_count_unread_entries($cm, $USER->id)) {
            $out = '<span class="unread"> <a href="' . $cm->get_url() . '">';
            if ($unread == 1) {
                $out .= $strunreaddialoguesone;
            } else {
                $out .= get_string('unreadnumber', 'dialogue', $unread);
            }
            $out .= '</a></span>';
            $cm->set_after_link($out);
        }
    } 
}
?>

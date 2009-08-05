<?php  // $Id: locallib.php,v 1.3.10.5 2009/08/05 05:18:59 deeknow Exp $

/**
 * Library of extra functions for the dialogue module not part of the standard add-on module API set
 * but used by scripts in the mod/dialogue folder
 * 
 * @package dialogue
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */


define ('DIALOGUETYPE_TEACHERSTUDENT', 0);
define ('DIALOGUETYPE_STUDENTSTUDENT', 1);
define ('DIALOGUETYPE_EVERYONE', 2);

/**
 * Count the number of closed conversations in a given dialogue for a given user
 *  
 * @param   object  $dialogue
 * @param   object  $user
 * @param   bool    $viewall if true show count closed records where user is initator or receipient
 * @return  int     count of the records found
 */
function dialogue_count_closed($dialogue, $user, $viewall=false) {
   
    if ($viewall) {
        $where = 'closed = 1';
    } else {
        $where = '(userid = '.$user->id.' OR recipientid = '.$user->id.') AND closed = 1'; 
    }
    
    return count_records_select('dialogue_conversations', "dialogueid = $dialogue->id AND $where");
}


/**
 * Count the number of open conversations in a given dialogue for a given user
 *  
 * @param   object  $dialogue
 * @param   object  $user
 * @return  int     count of the records found
 */
function dialogue_count_open($dialogue, $user) {
    
    return count_records_select('dialogue_conversations', "dialogueid = $dialogue->id AND 
        (userid = $user->id OR recipientid = $user->id) AND closed = 0");
}


/**
 * Return a list of users that the current user is able to open a dialogue with
 * 
 * Makes calls to dialogue_get_available_students() and dialogue_get_available_teachers()
 * depending on the capability and type of dialogue
 * @param   object  $dialogue
 * @param   object  $context    for a user in this activity
 * @param   int     $editconversationid
 * @return  array   usernames and ids
 */
function dialogue_get_available_users($dialogue, $context, $editconversationid) {

    if (! $course = get_record('course', 'id', $dialogue->course)) {
        error('Course is misconfigured');
    }
    $hascapopen = has_capability('mod/dialogue:participate', $context);
    $hascapmanage = has_capability('mod/dialogue:manage', $context);
    
    
    switch ($dialogue->dialoguetype) {
        case DIALOGUETYPE_TEACHERSTUDENT : // teacher to student
            if ($hascapmanage) {
                return dialogue_get_available_students($dialogue, $context, $editconversationid);
            }
            else {
                return dialogue_get_available_teachers($dialogue,  $context, $editconversationid);
            }
        case DIALOGUETYPE_STUDENTSTUDENT: // student to student
            if (! $hascapmanage && $hascapopen) {
                return dialogue_get_available_students($dialogue, $context, $editconversationid);
            }
            else {
                return; // dont return any students if this is a teacher, ie has manage capability
            }
        case DIALOGUETYPE_EVERYONE: // everyone
            if ($teachers = dialogue_get_available_teachers($dialogue, $context, $editconversationid)) {
                foreach ($teachers as $userid=>$name) {
                    $names[$userid] = $name;
                }
                $names[-1] = '-------------';
            }
            if ($students = dialogue_get_available_students($dialogue, $context, $editconversationid)) {
                foreach ($students as $userid=>$name) {
                    $names[$userid] = $name;
                }
            }
            if (isset($names)) {
                return $names;
            }
            return;
    }
}

                    
/**
 * Return a list of students that the current user is able to open a dialogue with
 * 
 * Called by dialogue_get_available_users(). The list is used to populate a drop-down
 * list in the UI. The returned array of usernames starts with a list of student-groups 
 * for a teacher, followed by an "All Participants" entry if a teacher, followed by a
 * list of students. The students list is refined to just the student's own group 
 * if the activity is in group-mode and also filters out the students own name
 * @param   object  $dialogue
 * @param   object  $context    for a user in this activity
 * @param   int     $editconversationid
 * @return  array   usernames and ids
 */
function dialogue_get_available_students($dialogue, $context, $editconversationid=0) {
    global $USER, $CFG;
    
    if (! $course = get_record('course', 'id', $dialogue->course)) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('dialogue', $dialogue->id, $course->id)) {
        error('Course Module ID was incorrect');
    }

    // get the list of teachers (actually, those who have dialogue:manage capability)
    $teachers = array();
    if ($users = get_users_by_capability($context, 'mod/dialogue:manage', '', 
                                         null, null, null, null, null, null,null,false)) {
        foreach ($users as $user) {
            $teachers[$user->id] = 1;
        }
    }

    $groupid = groups_get_activity_group($cm, true);
    // add current group before list of students if it's the teacher
    if ($teachers[$USER->id]) {
        // show teacher their current group
        if ($groupid) {
            if (! $group = get_record('groups', 'id', $groupid)) {
                error('Dialogue get available students: group not found');
            }
            $gnames["g$groupid"] = $group->name;
        }
        $gnames['g0'] = get_string('allparticipants');
        $gnames['spacer'] = '------------';
    }


    // get the students on this course (default sort order)...
    if ($users = get_users_by_capability($context, 'mod/dialogue:participate', 
                                         null, null, null, null, null, null, null,null,false)) {
        if (! empty($CFG->enablegroupings) && ! empty($cm->groupingid) && ! empty($users)) {
            $groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id');
            foreach($users as $key => $user) {
                if (! isset($groupingusers[$user->id])) {
                    unset($users[$key]);
                }
            }
        }
        foreach ($users as $otheruser) {
            // ...exclude self and...
            if ($USER->id != $otheruser->id) {

                // ...if not a student (eg co-teacher, teacher) then exclude from students list
                if (isset($teachers[$otheruser->id]) && $teachers[$otheruser->id] == 1) {
                    continue;
                }

                // ...if teacher and groups then exclude students not in the current group
                if ($teachers[$USER->id] and groupmode($course, $cm) and $groupid) {
                    if (! ismember($groupid, $otheruser->id)) {
                        continue;
                    }
                }

                // ...if student and groupmode is SEPARATEGROUPS then exclude students not in student's group
                if (! $teachers[$USER->id] and (groupmode($course, $cm) == SEPARATEGROUPS)) {
                    if (! ismember($groupid, $otheruser->id)) {
                        continue;
                    }
                }

                // ... and any already in any open conversations unless multiple conversations allowed
                if ($dialogue->multipleconversations or count_records_select('dialogue_conversations', 
                        "dialogueid = $dialogue->id AND id != $editconversationid AND 
                        ((userid = $USER->id AND recipientid = $otheruser->id) OR 
                        (userid = $otheruser->id AND recipientid = $USER->id)) AND closed = 0") == 0) {
                    $names[$otheruser->id] = fullname($otheruser);
                }
            }
        }
    }
    if (isset($gnames)) {   // group names
        $list = $gnames;
    }
    if (isset($names)) {
        natcasesort($names);
        if (isset($list)) {
            $list += $names;
        } else {
            $list = $names;
        }
    }
    if (isset($list)) {
        return $list;
    } else {
        return;
    }
}


/**
 * Return a list of teachers that the current user is able to open a dialogue with
 * 
 * Called by dialogue_get_available_users(). The list is used to populate a drop-down
 * list in the UI. The returned array of usernames is filtered to hide teacher names
 * if those teachers have a hidden role assignment, unless the list is being returned
 * for a teacher in which case those hidden teachers are listed
 * @param   object  $dialogue
 * @param   object  $context    for a user in this activity
 * @param   int     $editconversationid
 * @return  array   usernames and ids
 */
function dialogue_get_available_teachers($dialogue, $context, $editconversationid = 0) {
    global $USER, $CFG;
    $canseehidden = has_capability('moodle/role:viewhiddenassigns', $context);
    if (! $course = get_record('course', 'id', $dialogue->course)) {
        error('Course is misconfigured');
        }
    if (! $cm = get_coursemodule_from_instance('dialogue', $dialogue->id, $course->id)) {
        error('Course Module ID was incorrect');
    }
    // get the list of teachers (actually, those who have dialogue:manage capability)
    $hiddenTeachers = array();
    if ($users = get_users_by_capability($context, 'mod/dialogue:manage', '', 
                                         null, null, null, null, null, null,true,null)) {
        foreach ($users as $user) {
            $userRoles = get_user_roles($context, $user->id, true);
            foreach ($userRoles as $role) {
                if ($role->hidden == 1) {
                    $hiddenTeachers[$user->id] = 1;
                    break;
                }
            }
        }
        $canSeeHidden = false;
        if (has_capability('moodle/role:viewhiddenassigns', $context)) {
            $canSeeHidden = true;
        }
        $groupid = get_current_group($course->id);
        foreach ($users as $otheruser) {
            // ...exclude self and ...
            if ($USER->id != $otheruser->id) {
                // ...if groupmode is SEPARATEGROUPS then exclude teachers not in student's group
                if ($groupid and (groupmode($course, $cm) == SEPARATEGROUPS)) {
                    if (! ismember($groupid, $otheruser->id)) {
                        continue;
                    }
                }
                if (! $canSeeHidden && array_key_exists($otheruser->id, $hiddenTeachers) 
                      && ($hiddenTeachers[$otheruser->id] == 1)) {
                    continue;
                }
                // ...any already in open conversations unless multiple conversations allowed 
                if ($dialogue->multipleconversations or count_records_select('dialogue_conversations', 
                        "dialogueid = $dialogue->id AND id != $editconversationid AND ((userid = $USER->id AND 
                        recipientid = $otheruser->id) OR (userid = $otheruser->id AND 
                        recipientid = $USER->id)) AND closed = 0") == 0) {
                    $names[$otheruser->id] = fullname($otheruser);
                }
            }
        }
    }
    if (isset($names)) {
        natcasesort($names);
        return $names;
    }
    return;
}


/**
 * List conversations of the current user that are closed
 * 
 * Called when a user clicks the "Closed Dialogues" tab
 * rendering those out directly as HTML inside a print_table() showing who the
 * conversation was with, what the subject was, how many entries there were and
 * what the most recent post date was   
 * @param   object  $dialogue
 */
function dialogue_list_conversations_closed($dialogue) {

    global $USER, $CFG;
  
    if (! $course = get_record('course', 'id', $dialogue->course)) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('dialogue', $dialogue->id, $course->id)) {
        error('Course Module ID was incorrect');
    }
    
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    
    $dialoguemanagers = array_keys(get_users_by_capability($context, 'mod/dialogue:manage'));

    $timenow = time();
    $showbutton = false;

    // list the conversations requiring a resonse from this user in full
    if ($conversations = dialogue_get_conversations($dialogue, $USER, 'closed = 1')) {

        // reorder the conversations by (other) name
        foreach ($conversations as $conversation) {
            
            if (in_array($USER->id, $dialoguemanagers)) {
                if (! in_array($conversation->userid, $dialoguemanagers)) {
                    if (! $with = get_record('user', 'id', $conversation->userid)) {
                        error("User's record not found");
                    }
                }
                else {
                    if (! $with = get_record('user', 'id', $conversation->recipientid)) {
                        error("User's record not found");
                    }                
                }
            } else {
                if ($USER->id != $conversation->userid) {
                    if (! $with = get_record('user', 'id', $conversation->userid)) {
                        error("User's record not found");
                    }
                }
                else {
                    if (! $with = get_record('user', 'id', $conversation->recipientid)) {
                        error("User's record not found");
                    }                
                }
            }
            $names[$conversation->id] = fullname($with);
        }
        natcasesort($names);
        
        print_simple_box_start('center');
        $table->head = array (get_string('dialoguewith', 'dialogue'), get_string('subject', 'dialogue'),  
            get_string('numberofentries', 'dialogue'), get_string('lastentry', 'dialogue'));
        $table->width = '100%';
        $table->align = array ('left', 'left', 'center', 'left');
        $table->size = array ('*', '*', '*', '*');
        $table->cellpadding = 2;
        $table->cellspacing = 0;

        foreach ($names as $cid=>$name) {
            if (! $conversation = get_record('dialogue_conversations', 'id', $cid)) {
                error('Closed conversations: could not find conversation record');
            }
            $total = dialogue_count_entries($dialogue, $conversation);
            
            $table->data[] = array(
                "<a href=\"dialogues.php?id=$cm->id&amp;action=printdialogue&amp;cid=$conversation->id\">".
                "$name</a>",  format_string($conversation->subject), $total,
                userdate($conversation->timemodified)
                );
        }
        print_table($table);
        print_simple_box_end();
    }
}

/**
 * Print a conversation and allow a new entry
 * 
 * Render out entries for the specified conversation as HTML showing the
 * avatar for the user who initiated  the dialogue. Follow up with a text
 * box to allow user to add a new response entry
 *  
 * @param   object  $dialogue
 * @param   int     $conversation
 */
function dialogue_print_conversation($dialogue, $conversation) {

    global $USER, $CFG;

    if (! $course = get_record('course', 'id', $dialogue->course)) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('dialogue', $dialogue->id, $course->id)) {
        error('Course Module ID was incorrect');
    }
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    $dialoguemanagers = array_keys(get_users_by_capability($context, 'mod/dialogue:manage'));

    $timenow = time();
    $showbutton = false;
    
    require_once('dialogue_reply_form.php');
    $mform = new mod_dialogue_reply_form('dialogues.php', array('conversationid' => $conversation->id));
    $mform->set_data(array('id' => $cm->id,
                           'action' => 'insertentries',
                           'pane' => 2));

    $showbutton = true;
    print_simple_box_start('center', '');
    echo "<table align=\"center\" border=\"1\" cellspacing=\"0\" valign=\"top\" cellpadding=\"4\" 
        width=\"100%\">\n";
    echo "<tr><td>\n";
    if (in_array($USER->id, $dialoguemanagers)) {
        if (! in_array($conversation->userid, $dialoguemanagers)) {
            if (! $otheruser = get_record('user', 'id', $conversation->userid)) {
                error("User's record not found");
            }
        }
        else {
            if (! $otheruser = get_record('user', 'id', $conversation->recipientid)) {
                error("User's record not found");
            }                
        }
    } else {
        if ($USER->id != $conversation->userid) {
            if (! $otheruser = get_record('user', 'id', $conversation->userid)) {
                error("User's record not found");
            }
        }
        else {
            if (! $otheruser = get_record('user', 'id', $conversation->recipientid)) {
                error("User's record not found");
            }                
        }
    }
    $picture = print_user_picture($otheruser->id, $course->id, $otheruser->picture, false, true);
    echo $picture." <b>".get_string('dialoguewith', 'dialogue', fullname($otheruser)).
        '</b></td>';
    echo '<td><i>'.format_string($conversation->subject)."&nbsp;</i><br />\n";
    echo "<div align=\"right\">\n";
    if (! $conversation->subject) {
        // conversation does not have a subject, show add subject link
        echo "<a href=\"dialogues.php?action=getsubject&amp;id=$cm->id&amp;cid=$conversation->id&amp;pane=2\">".
            get_string('addsubject', 'dialogue')."</a>\n";
        helpbutton('addsubject', get_string('addsubject', 'dialogue'), 'dialogue');
        echo '&nbsp; | ';
    }
    if (! $conversation->closed && has_capability('mod/dialogue:close', $context)) {
    echo "<a href=\"dialogues.php?action=confirmclose&amp;id=$cm->id&amp;cid=$conversation->id&amp;pane=2\">".
        get_string('close', 'dialogue')."</a>\n";
        helpbutton('closedialogue', get_string('close', 'dialogue'), 'dialogue');
    }
    echo "</div></td></tr>\n";

    if ($entries = get_records_select('dialogue_entries', "conversationid = $conversation->id", 'id')) {
        $firstentry = true;
        foreach ($entries as $entry) {
            if (! $otheruser = get_record('user', 'id', $entry->userid)) {
                error('User not found');
            }
            $canedit = false;
            if (! $conversation->closed && $entry->userid == $USER->id 
                  && $timenow < $entry->timecreated+($dialogue->edittime * 60)) {
            	 $canedit = true;
            }
    
            if ($entry->timecreated != $entry->timemodified) {
                $modified = get_string('updated', 'dialogue', userdate($entry->timemodified));            
            } else {
                $modified = '';
            }
            
            if ($entry->userid == $USER->id) {
                echo "<tr><td colspan=\"2\" bgcolor=\"#FFFFFF\">\n";
                if ($canedit) {
                    if ($firstentry) {
                    	echo "<a href=\"dialogues.php?action=editconversation&amp;id=$cm->id&amp;entryid=$entry->id&amp;pane=2\">".
                            get_string('edit').'</a>';
                    } else {
                        echo "<a href=\"dialogues.php?action=editreply&amp;id=$cm->id&amp;entryid=$entry->id&amp;pane=2\">".
                            get_string('edit').'</a>';
                    }
                }
                echo "<p><font size=\"1\">".get_string('onyouwrote', 'dialogue', 
                            userdate($entry->timecreated).' '.$modified);
                echo ":</font></p><br />".format_text($entry->text);
            }
            else {
                echo "<tr><td colspan=\"2\">\n";
                echo "<p><font size=\"1\">".get_string("onwrote", "dialogue", 
                            userdate($entry->timecreated)." $modified ".fullname($otheruser));
                
                echo ":</font></p><br />".format_text($entry->text);
            }
            echo dialogue_print_attachments($entry);
            echo "</td></tr>\n";
            $firstentry = false;
        }

    }
    echo "</table><br />\n";
    if (! $conversation->closed && (has_capability('mod/dialogue:participateany', $context) 
          || $conversation->userid == $USER->id || $conversation->recipientid == $USER->id)) {
        $mform->display();
    }

    print_simple_box_end();

    if (! $conversation->seenon && $conversation->lastrecipientid == $USER->id) {
        set_field('dialogue_conversations', 'seenon', time(), 'id', $conversation->id); 
    }
    dialogue_mark_conversation_read($conversation->id, $USER->id);
}


/**
 * List open conversations of the current user awaiting their reply
 * 
 * Called when a user clicks the "Current Dialogues" tab
 * rendering those out directly as HTML inside a print_table() showing who the
 * conversation is with, what the subject is, how many entries there are, 
 * how many are un-read and what the most recent post date is 
 *  
 * @param   object  $dialogue
 */
function dialogue_list_conversations($dialogue) {

    global $USER, $CFG;
  
    if (! $course = get_record('course', 'id', $dialogue->course)) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('dialogue', $dialogue->id, $course->id)) {
        error('Course Module ID was incorrect');
    }
    
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    
    $dialoguemanagers = array_keys(get_users_by_capability($context, 'mod/dialogue:manage'));
   
    $timenow = time();
    $showbutton = false;

    // list the conversations requiring a resonse from this user in full
    if ($conversations = dialogue_get_conversations($dialogue, $USER, 'closed = 0 ')) {

        // reorder the conversations by (other) name
        foreach ($conversations as $conversation) {
            
            if (in_array($USER->id, $dialoguemanagers)) {
                if (! in_array($conversation->userid, $dialoguemanagers)) {
                    if (! $with = get_record('user', 'id', $conversation->userid)) {
                        error("User's record not found");
                    }
                }
                else {
                    if (! $with = get_record('user', 'id', $conversation->recipientid)) {
                        error("User's record not found");
                    }                
                }
            } else {
                if ($USER->id != $conversation->userid) {
                    if (! $with = get_record('user', 'id', $conversation->userid)) {
                        error("User's record not found");
                    }
                }
                else {
                    if (! $with = get_record('user', 'id', $conversation->recipientid)) {
                        error("User's record not found");
                    }                
                }
            }
            $names[$conversation->id] = fullname($with);
        }
        natcasesort($names);
        
        print_simple_box_start('center');
        $table = new object();
        $table->head = array (
            get_string('dialoguewith', 'dialogue'), 
            get_string('subject', 'dialogue'),  
            get_string('numberofentries', 'dialogue'), 
            get_string('unread', 'dialogue'), 
            get_string('lastentry', 'dialogue')
        );
        $table->width = '100%';
        $table->align = array ('left', 'left', 'center', 'center', 'left');
        $table->size = array ('*', '*', '*', '*', '*');
        $table->cellpadding = 2;
        $table->cellspacing = 0;

        foreach ($names as $cid=>$name) {
            $conversation = $conversations[$cid];
           
            if ($conversation->total-$conversation->readings > 0) {
                $unread = '<span class="unread">'.($conversation->total-$conversation->readings).'</span>';
            } else {
                $unread = 0;
            }
         
            $table->data[] = array(
                "<a href=\"dialogues.php?id=$cm->id&amp;action=printdialogue&amp;cid=$conversation->id\">".
                "$name</a>", format_string($conversation->subject), $conversation->total, $unread,
                userdate($conversation->timemodified)
            );
        }
        print_table($table);
        print_simple_box_end();
    }
}

/**
 * Mark all entries in a conversation as read for a given user 
 * 
 * Called when a user views a conversation
 * @param   int  $conversationid
 * @param   int  $userid
 */
function dialogue_mark_conversation_read($conversationid, $userid) {
    global $CFG;

    $lastread = time();

    // Update any previously seen entries in this conversaion
    set_field('dialogue_read', 'lastread', $lastread, 'conversationid', $conversationid, 'userid', $userid);

    $sql = "SELECT e.id FROM {$CFG->prefix}dialogue_entries e
                LEFT JOIN {$CFG->prefix}dialogue_read r ON e.id = r.entryid AND r.userid = $userid 
            WHERE e.conversationid = $conversationid AND r.id IS NULL ";


    if ($unread = get_records_sql($sql)) {
        foreach($unread as $entry) {
            $read = new stdClass;
            $read->conversationid = $conversationid;
            $read->entryid        = $entry->id;
            $read->userid         = $userid;
            $read->firstread      = $lastread;
            $read->lastread       = $lastread;

            insert_record('dialogue_read', $read);
        }
    }
}
?>

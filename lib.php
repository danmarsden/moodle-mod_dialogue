<?PHP // $Id: lib.php,v 1.1 2003/10/02 16:21:02 moodler Exp $

$DIALOGUE_DAYS = array (0 => 0, 7 => 7, 14 => 14, 30 => 30, 150 => 150, 365 => 365 );


// STANDARD MODULE FUNCTIONS /////////////////////////////////////////////////////////

function dialogue_add_instance($dialogue) {
// Given an object containing all the necessary data, 
// (defined by the form in mod.html) this function 
// will create a new instance and return the id number 
// of the new instance.

    $dialogue->timemodified = time();

    return insert_record("dialogue", $dialogue);
}


function dialogue_cron () {
// Function to be run periodically according to the moodle cron
// Finds all dialogue entries that have yet to be mailed out, and mails them

    global $CFG, $USER;

    if ($entries = get_records_select("dialogue_entries", "mailed = '0'")) {
        foreach ($entries as $entry) {

            echo "Processing dialogue entry $entry->id\n";

            if (! $userfrom = get_record("user", "id", "$entry->userid")) {
                echo "Could not find user $entry->userid\n";
                continue;
			}
			// get conversation record
			if(!$conversation = get_record("dialogue_conversations", "id", $entry->conversationid)) {
				echo "Could not find conversation $entry->conversationid\n";
			}
			if ($userfrom->id == $conversation->userid) {
				if (!$userto = get_record("user", "id", $conversation->recipientid)) {
					echo "Could not find use $conversation->recipientid\n";
				}
			}
			else {
				if (!$userto = get_record("user", "id", $conversation->userid)) {
					echo "Could not find use $conversation->userid\n";
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

            if (! isstudent($course->id, $userfrom->id) and !isteacher($course->id, $userfrom->id)) {
                continue;  // Not an active participant
            }
            if (! isstudent($course->id, $userto->id) and !isteacher($course->id, $userto->id)) {
                continue;  // Not an active participant
            }

			$strdialogues = get_string("modulenameplural", "dialogue");
			$strdialogue  = get_string("modulename", "dialogue");
	
            unset($dialogueinfo);
            $dialogueinfo->userfrom = "$userfrom->firstname $userfrom->lastname";
            $dialogueinfo->dialogue = "$dialogue->name";
            $dialogueinfo->url = "$CFG->wwwroot/mod/dialogue/view.php?id=$cm->id";

            $postsubject = "$course->shortname: $strdialogues: $dialogue->name: ".get_string("newentry", "dialogue");
            $posttext  = "$course->shortname -> $strdialogues -> $dialogue->name\n";
            $posttext .= "---------------------------------------------------------------------\n";
            $posttext .= get_string("dialoguemail", "dialogue", $dialogueinfo);
            $posttext .= "---------------------------------------------------------------------\n";
            if ($userto->mailformat == 1) {  // HTML
                $posthtml = "<p><font face=\"sans-serif\">".
                "<a href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$course->shortname</a> ->".
                "<a href=\"$CFG->wwwroot/mod/dialogue/index.php?id=$course->id\">dialogues</a> ->".
                "<a href=\"$CFG->wwwroot/mod/dialogue/view.php?id=$cm->id\">$dialogue->name</a></font></p>";
                $posthtml .= "<hr><font face=\"sans-serif\">";
                $posthtml .= "<p>".get_string("dialoguemailhtml", "dialogue", $dialogueinfo)."</p>";
                $posthtml .= "</font><hr>";
            } else {
              $posthtml = "";
            }

            if (! email_to_user($userto, $userfrom, $postsubject, $posttext, $posthtml)) {
                echo "Error: dialogue cron: Could not send out mail for id $entry->id to user $userto->id ($userto->email)\n";
            }
            if (! set_field("dialogue_entries", "mailed", "1", "id", "$entry->id")) {
                echo "Could not update the mailed field for id $entry->id\n";
            }
        }
    }

    return true;
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


function dialogue_print_recent_activity($course, $isteacher, $timestart) {
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
			$strftimerecent = get_string("strftimerecent");
			print_headline(get_string("newdialogueentries", "dialogue").":");
			foreach ($logs as $log) {
				//Create a temp valid module structure (only need courseid, moduleid)
				$tempmod->course = $course->id;
				$tempmod->id = $log->dialogueid;
				//Obtain the visible property from the instance
				if (instance_is_visible("dialogue",$tempmod)) {
					$date = userdate($log->time, $strftimerecent);
					echo "<p><font size=1>$date - $log->firstname $log->lastname<br />";
					echo "\"<a href=\"$CFG->wwwroot/mod/dialogue/$log->url\">";
					echo "$log->name";
					echo "</a>\"</font></p>";
				}
			}
		}
	}

	// have a look for closed conversations
	$closedcontent = false;
    if ($logs = dialogue_get_closed_logs($course, $timestart)) {
		// got some, see if any belong to a visible module
		foreach ($logs as $log) {
			// Create a temp valid module structure (only need courseid, moduleid)
			$tempmod->course = $course->id;
			$tempmod->id = $log->dialogueid;
			//Obtain the visible property from the instance
			if (instance_is_visible("dialogue",$tempmod)) {
				$closedcontent = true;
				break;
			}
		}
		// if we got some "live" ones then output them
		if ($closedcontent) {
			$strftimerecent = get_string("strftimerecent");
			print_headline(get_string("modulenameplural", "dialogue").":");
			foreach ($logs as $log) {
				//Create a temp valid module structure (only need courseid, moduleid)
				$tempmod->course = $course->id;
				$tempmod->id = $log->dialogueid;
				//Obtain the visible property from the instance
				if (instance_is_visible("dialogue",$tempmod)) {
					$date = userdate($log->time, $strftimerecent);
					echo "<p><font size=1>$date - ".get_string("namehascloseddialogue", "dialogue",
						"$log->firstname $log->lastname")."<br />";
					echo "\"<a href=\"$CFG->wwwroot/mod/dialogue/$log->url\">";
					echo "$log->name";
					echo "</a>\"</font></p>";
				}
			}
		}
	}
    return $addentrycontent or $closedcontent;
}



function dialogue_update_instance($dialogue) {
// Given an object containing all the necessary data, 
// (defined by the form in mod.html) this function 
// will update an existing instance with new data.

    $dialogue->timemodified = time();
    $dialogue->id = $dialogue->instance;

    return update_record("dialogue", $dialogue);
}


function dialogue_user_complete($course, $user, $mod, $dialogue) {

    if ($conversations = dialogue_get_conversations($dialogue, $user)) {
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
			$total = dialogue_count_entries($dialogue, $conversation);
			$byuser = dialogue_count_entries($dialogue, $conversation, $user);
			if ($conversation->closed) {
				$status = get_string("closed", "dialogue");
			} else {
				$status = get_string("open", "dialogue");
			}
			$table->data[] = array("$with->firstname $with->lastname", $byuser." ".
                get_string("of", "dialogue")." ".$total, userdate($conversation->timemodified), $status);
		}
		print_table($table);
	    print_simple_box_end();
	} 
    else {
        print_string("noentry", "dialogue");
    }
}


function dialogue_user_outline($course, $user, $mod, $dialogue) {
    if ($entries = dialogue_get_user_entries($dialogue, $user)) {
        $result->info = count($entries);
		foreach ($entries as $entry) {
			// dialogue_get_user_entries returns the most recent entry first
			$result->time = $entry->timecreated;
			break;
		}
        return $result;
    }
    return NULL;
}


// SQL FUNCTIONS ///////////////////////////////////////////////////////////////////

function dialogue_count_closed($dialogue, $user) {
	
	return count_records_select("dialogue_conversations", "dialogueid = $dialogue->id AND 
        (userid = $user->id OR recipientid = $user->id) AND closed = 1");
	}


function dialogue_count_entries($dialogue, $conversation, $user = '') {
	
	if (empty($user)) {
		return count_records_select("dialogue_entries", "conversationid = $conversation->id");
	}
	else {
		return count_records_select("dialogue_entries", "conversationid = $conversation->id AND 
            userid = $user->id");
	}	
}


function dialogue_get_available_users($dialogue) {

    if (! $course = get_record("course", "id", $dialogue->course)) {
        error("Course is misconfigured");
    }
    switch ($dialogue->dialoguetype) {
        case 0 : // teacher to student
            if (isteacher($course->id)) {
                return dialogue_get_available_students($dialogue);
            }
            else {
                return dialogue_get_available_teachers($dialogue);
            }
        case 1: // student to student
            if (isstudent($course->id)) {
                return dialogue_get_available_students($dialogue);
            }
            else {
                return;
            }
        case 2: // everyone
            if ($teachers = dialogue_get_available_teachers($dialogue)) {
                foreach ($teachers as $userid=>$name) {
                    $names[$userid] = $name;
                }
                $names[-1] = "-------------";
            }
            if ($students = dialogue_get_available_students($dialogue)) {
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

                    
function dialogue_get_available_students($dialogue) {
global $USER;
   	
    if (! $course = get_record("course", "id", $dialogue->course)) {
        error("Course is misconfigured");
    }
     // get the students on this course...
	if ($users = get_course_students($course->id, "u.firstname, u.lastname")) {
		foreach ($users as $otheruser) {
			// ...exclude self and...
			if ($USER->id != $otheruser->id) {
				// ...any already in any open conversations
				if (count_records_select("dialogue_conversations", "dialogueid = $dialogue->id AND 
                        ((userid = $USER->id AND recipientid = $otheruser->id) OR 
                        (userid = $otheruser->id AND recipientid = $USER->id)) AND closed = 0") == 0) {
					$names[$otheruser->id] = "$otheruser->firstname $otheruser->lastname";
				}
			}
		}
	}
    if (isset($names)) {
        return $names;
    }
    return;
}


function dialogue_get_available_teachers($dialogue) {
global $USER;
   	
    if (! $course = get_record("course", "id", $dialogue->course)) {
        error("Course is misconfigured");
        }
    // get the teachers on this course...
	if ($users = get_course_teachers($course->id, "u.firstname, u.lastname")) {
		// $names[0] = "-----------------------";
		foreach ($users as $otheruser) {
            // ...exclude self and ...
			if ($USER->id != $otheruser->id) {
                // ...any already in open conversations 
				if (count_records_select("dialogue_conversations", "dialogueid = $dialogue->id AND 
                        ((userid = $USER->id AND recipientid = $otheruser->id) OR 
                        (userid = $otheruser->id AND recipientid = $USER->id)) AND closed = 0") == 0) {
					$names[$otheruser->id] = "$otheruser->firstname $otheruser->lastname";
				}
			}
		}
	}
	if (isset($names)) {
		return $names;
	}
	return;
}



function dialogue_get_conversations($dialogue, $user, $condition = '') {
    global $CFG;
	
	if (!empty($condition)) {
		return get_records_select("dialogue_conversations", "dialogueid = $dialogue->id AND 
                (userid = $user->id OR recipientid = $user->id) AND $condition", "timemodified DESC");
	}
	else {
		return get_records_select("dialogue_conversations", "dialogueid = $dialogue->id AND 
                (userid = $user->id OR recipientid = $user->id)", "timemodified DESC");
	}
}


function dialogue_get_users_done($dialogue) {
    global $CFG;
    return get_records_sql("SELECT u.* 
                              FROM {$CFG->prefix}user u, 
                                   {$CFG->prefix}user_students s, 
                                   {$CFG->prefix}user_teachers t, 
                                   {$CFG->prefix}dialogue_entries j
                             WHERE ((s.course = '$dialogue->course' AND s.userid = u.id) 
                                OR  (t.course = '$dialogue->course' AND t.userid = u.id))
                               AND u.id = j.userid 
                               AND j.dialogue = '$dialogue->id'
                          ORDER BY j.modified DESC");
}


function dialogue_get_user_entries($dialogue, $user) {
    global $CFG;
    return get_records_select("dialogue_entries", "dialogueid = $dialogue->id AND userid = $user->id",
                "timecreated DESC");
}


function dialogue_get_add_entry_logs($course, $timestart) {
	// get the "add entry" entries and add the first and last names, we are not interested in the entries 
	// make by this user (the last condition)!
	global $CFG, $USER;
    return get_records_sql("SELECT l.time, l.url, u.firstname, u.lastname, e.dialogueid, d.name
                             FROM {$CFG->prefix}log l,
								{$CFG->prefix}dialogue d, 
        						{$CFG->prefix}dialogue_conversations c, 
                                {$CFG->prefix}dialogue_entries e, 
                                {$CFG->prefix}user u
                            WHERE l.time > $timestart AND l.course = $course->id AND l.module = 'dialogue'
								AND l.action = 'add entry'
								AND e.id = l.info 
								AND c.id = e.conversationid
                                AND (c.userid = $USER->id or c.recipientid = $USER->id)
								AND d.id = e.dialogueid
								AND u.id = e.userid 
								AND e.userid != $USER->id");
}


function dialogue_get_closed_logs($course, $timestart) {
	// get the "closed" entries and add the first and last names, we are not interested in the entries 
	// make by this user (the last condition)!
	global $CFG, $USER;
    return get_records_sql("SELECT l.time, l.url, u.firstname, u.lastname, c.dialogueid, d.name
                             FROM {$CFG->prefix}log l,
								{$CFG->prefix}dialogue d, 
        						{$CFG->prefix}dialogue_conversations c, 
                                {$CFG->prefix}user u
                            WHERE l.time > $timestart AND l.course = $course->id AND l.module = 'dialogue'
								AND l.action = 'closed'
								AND c.id = l.info 
                                AND (c.userid = $USER->id or c.recipientid = $USER->id)
								AND d.id = c.dialogueid
								AND u.id = c.lastid 
								AND c.lastid != $USER->id");
}


// OTHER dialogue FUNCTIONS ///////////////////////////////////////////////////////////////////

function dialogue_list_closed_conversations($dialogue, $user) {
	
	if (! $course = get_record("course", "id", $dialogue->course)) {
        error("Course is misconfigured");
    }
    if (! $cm = get_coursemodule_from_instance("dialogue", $dialogue->id, $course->id)) {
        error("Course Module ID was incorrect");
    }
	
    if ($conversations = dialogue_get_conversations($dialogue, $user, "closed = 1")) {
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
			$total = dialogue_count_entries($dialogue, $conversation);
			$byuser = dialogue_count_entries($dialogue, $conversation, $user);
			if ($conversation->closed) {
				$status = get_string("closed", "dialogue");
			} else {
				$status = get_string("open", "dialogue");
			}
			$table->data[] = array("<a href=\"dialogues.php?id=$cm->id&action=showdialogue&conversationid=$conversation->id\">".
				"$with->firstname $with->lastname</a>", $byuser." ".get_string("of", "dialogue")." ".$total,
				userdate($conversation->timemodified), $status);
			}
		print_table($table);
	    print_simple_box_end();
	} 
	else {
        print_string("noentry", "dialogue");
    }
}


function dialogue_list_conversations($dialogue, $user) {
    global $THEME;
	
	if (! $course = get_record("course", "id", $dialogue->course)) {
        error("Course is misconfigured");
    }
    if (! $cm = get_coursemodule_from_instance("dialogue", $dialogue->id, $course->id)) {
        error("Course Module ID was incorrect");
    }
	
	$timenow = time();
	$showbutton = false;
	$showemoticon = false;  // never show emoticons for now - need to close or reload the  popup 
                            // window to get the focus into the correct textarea on the second time round
	
	echo "<form name=\"replies\" method=\"post\" action=\"dialogues.php\">\n";
	echo "<input type=\"hidden\" name=\"action\" value=\"insertentries\">\n";
	echo "<input type=\"hidden\" name=\"id\" value=\"$cm->id\">\n";

	// first the ones requiring a resonse from the user
	if ($conversations = dialogue_get_conversations($dialogue, $user, "lastid != $user->id AND closed = 0")) {
		$showbutton = true;
		echo "<hr />\n";
		print_simple_box_start("center");
		print_heading(get_string("dialoguesawaitingreply", "dialogue"));
		foreach ($conversations as $conversation) {
			echo "<center><TABLE BORDER=1 CELLSPACING=0 valign=top cellpadding=4 width=\"100%\">\n";
				
			echo "<TR>";
			echo "<TD BGCOLOR=\"$THEME->cellheading2\" VALIGN=TOP>\n";
			if ($conversation->userid == $user->id) {
				if (!$otheruser = get_record("user", "id", $conversation->recipientid)) {
					error("User not found");
				}
			}
			else {
				if (!$otheruser = get_record("user", "id", $conversation->userid)) {
					error("User not found");
				}
			}
			// print_user_picture($user->id, $course->id, $user->picture);
			echo "<b>".get_string("dialoguewith", "dialogue", "$otheruser->firstname $otheruser->lastname").
                "</b>";
			echo "</td><td bgcolor=\"$THEME->cellheading2\" align=\"right\">\n";
			if (dialogue_count_entries($dialogue, $conversation)) {
				echo "<a href=\"dialogues.php?action=confirmclose&id=$cm->id&conversationid=$conversation->id\">".
					get_string("close", "dialogue")."</a>\n";
				helpbutton("closedialogue", get_string("close", "dialogue"), "dialogue");
			}
			else {
				echo "&nbsp;";
			}
			echo "</td></tr>";
		
			if ($entries = get_records_select("dialogue_entries", "conversationid = $conversation->id", "id")) {
				foreach ($entries as $entry) {
					if ($entry->userid == $user->id) {
						echo "<tr><td colspan=\"2\" bgcolor=\"#FFFFFF\">\n";
						echo text_to_html("<font size=\"1\">".get_string("onyouwrote", "dialogue", 
                            userdate($entry->timecreated)).":</font><br />".$entry->text);
						echo "</td></tr>\n";
					}
					else {
						echo "<tr><td colspan=\"2\" bgcolor=\"$THEME->body\">\n";
						echo text_to_html("<font size=\"1\">".get_string("onwrote", "dialogue", 
                               userdate($entry->timecreated)." ".$otheruser->firstname).
                               ":</font><br />".$entry->text);
						echo "</td></tr>\n";
					}
				}
			}
		
			echo "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">\n";
			if ($entries) {
				echo "<i>".get_string("typereply", "dialogue")."</i>\n";
			}
			else {
				echo "<i>".get_string("typefirstentry", "dialogue")."</i>\n";
			}
			echo "</td></tr>\n";
			echo "<tr><td valign=\"top\" align=\"right\">\n";
			helpbutton("writing", get_string("helpwriting"), "dialogue", true, true);
			echo "<br />";
			if ($showemoticon) {
				emoticonhelpbutton("replies", "reply$conversation->id");
				$showemoticon = false;
			}				
			echo "</td><td>\n";
			// use a cumbersome name on the textarea as the emoticonhelp doesn't like an "array" name 
			echo "<TEXTAREA NAME=\"reply$conversation->id\" ROWS=5 COLS=60 WRAP=virtual>";
			echo "</TEXTAREA>\n";
			echo "</td></tr>";
			echo "</TABLE></center><BR CLEAR=ALL>\n";
		}
		print_simple_box_end();
	}
	// and now the one the user has already replied to
	if ($conversations = dialogue_get_conversations($dialogue, $user, "lastid = $user->id AND closed = 0")) {
		$showbutton = true;
		echo "<hr />\n";
		print_simple_box_start("center", "", $THEME->cellcontent2);
		print_heading(get_string("dialoguesawaitingreplyfromother", "dialogue"));
		foreach ($conversations as $conversation) {
			echo "<center><TABLE BORDER=1 CELLSPACING=0 valign=top cellpadding=4 width=\"100%\">\n";
				
			echo "<tr>";
			echo "<td bgcolor=\"$THEME->cellheading2\" valign=\"top\">\n";
			if ($conversation->userid == $user->id) {
				if (!$otheruser = get_record("user", "id", $conversation->recipientid)) {
					error("User not found");
					}
			}
			else {
				if (!$otheruser = get_record("user", "id", $conversation->userid)) {
					error("User not found");
				}
			}
			// print_user_picture($user->id, $course->id, $user->picture);
			echo "<b>".get_string("dialoguewith", "dialogue", "$otheruser->firstname $otheruser->lastname").
                "</b>";
			echo "</td><td bgcolor=\"$THEME->cellheading2\" align=\"right\">\n";
			echo "<a href=\"dialogues.php?action=confirmclose&id=$cm->id&conversationid=$conversation->id\">".
				get_string("close", "dialogue")."</a>\n";
			helpbutton("closedialogue", get_string("close", "dialogue"), "dialogue");
			echo "</td></tr>\n";
		
			if ($entries = get_records_select("dialogue_entries", "conversationid = $conversation->id", "id")) {
				foreach ($entries as $entry) {
					if ($entry->userid == $user->id) {
						echo "<tr><td colspan=\"2\" bgcolor=\"#FFFFFF\">\n";
						echo text_to_html("<font size=\"1\">".get_string("onyouwrote", "dialogue", 
                               userdate($entry->timecreated)).":</font><br />".$entry->text);
					}
					else {
						echo "<tr><td colspan=\"2\" bgcolor=\"$THEME->body\">\n";
						echo text_to_html("<font size=\"1\">".get_string("onwrote", "dialogue", 
                            userdate($entry->timecreated)." ".$otheruser->firstname).":</font><br />".
                            $entry->text);
					}
				}
    			echo "</td></tr>\n";
			}
			echo "<tr><td colspan=\"2\" align=\"center\" valign=\"top\"><i>".
                get_string("typefollowup", "dialogue")."</i></td></tr>\n";
			echo "<tr><td valign=\"top\" align=\"right\">\n";
			helpbutton("writing", get_string("helpwriting"), "dialogue", true, true);
			echo "<br />";
			if ($showemoticon) {
				emoticonhelpbutton("replies", "reply$conversation->id");
				$showemoticon = false;
			}
			echo "</td><td>\n";
			// use a cumbersome name on the textarea as the emoticonhelp doesn't like an "array" name 
			echo "<TEXTAREA NAME=\"reply$conversation->id\" ROWS=5 COLS=60 WRAP=virtual>";
			echo "</TEXTAREA>\n";
			echo "</td></tr>";
			echo "</TABLE></center><BR CLEAR=ALL>\n";
		}
		print_simple_box_end();
	}
	if ($showbutton) {
		echo "<hr />\n";
		echo "<b>".get_string("sendmailmessages", "dialogue").":</b> \n";
		if ($dialogue->maildefault) {
			echo "<input type=\"checkbox\" name=\"sendthis\" value=\"1\" checked>\n";
		}
		else {
			echo "<input type=\"checkbox\" name=\"sendthis\" value=\"1\">\n";
		}
		echo "<br /><input type=\"submit\" value=\"".get_string("addmynewentries", "dialogue")."\">\n";
	}
	echo "</form>\n";
}


function dialogue_print_feedback($course, $entry, $grades) {
    global $CFG, $THEME;

    if (! $teacher = get_record("user", "id", $entry->teacher)) {
        error("Weird dialogue error");
    }

    echo "\n<TABLE BORDER=0 CELLPADDING=1 CELLSPACING=1 ALIGN=CENTER><TR><TD BGCOLOR=#888888>";
    echo "\n<TABLE BORDER=0 CELLPADDING=3 CELLSPACING=0 VALIGN=TOP>";

    echo "\n<TR>";
    echo "\n<TD ROWSPAN=3 BGCOLOR=\"$THEME->body\" WIDTH=35 VALIGN=TOP>";
    print_user_picture($teacher->id, $course->id, $teacher->picture);
    echo "</TD>";
    echo "<TD NOWRAP WIDTH=100% BGCOLOR=\"$THEME->cellheading\">$teacher->firstname $teacher->lastname";
    echo "&nbsp;&nbsp;<FONT SIZE=2><I>".userdate($entry->timemarked)."</I>";
    echo "</TR>";

    echo "\n<TR><TD WIDTH=100% BGCOLOR=\"$THEME->cellcontent\">";

    echo "<P ALIGN=RIGHT><FONT SIZE=-1><I>";
    if ($grades[$entry->rating]) {
        echo get_string("grade").": ";
        echo $grades[$entry->rating];
    } else {
        print_string("nograde");
    }
    echo "</I></FONT></P>";

    echo text_to_html($entry->comment);
    echo "</TD></TR></TABLE>";
    echo "</TD></TR></TABLE>";
}


function dialogue_print_user_entry($course, $user, $entry, $teachers, $grades) {
    global $THEME, $USER;

    if ($entry->timemarked < $entry->modified) {
        $colour = $THEME->cellheading2;
    } else {
        $colour = $THEME->cellheading;
    }

    echo "\n<TABLE BORDER=1 CELLSPACING=0 valign=top cellpadding=10>";
        
    echo "\n<TR>";
    echo "\n<TD ROWSPAN=2 BGCOLOR=\"$THEME->body\" WIDTH=35 VALIGN=TOP>";
    print_user_picture($user->id, $course->id, $user->picture);
    echo "</TD>";
    echo "<TD NOWRAP WIDTH=100% BGCOLOR=\"$colour\">$user->firstname $user->lastname";
    if ($entry) {
        echo "&nbsp;&nbsp;<FONT SIZE=1>".get_string("lastedited").": ".userdate($entry->modified)."</FONT>";
    }
    echo "</TR>";

    echo "\n<TR><TD WIDTH=100% BGCOLOR=\"$THEME->cellcontent\">";
    if ($entry) {
        echo format_text($entry->text, $entry->format);
    } else {
        print_string("noentry", "dialogue");
    }
    echo "</TD></TR>";

    if ($entry) {
        echo "\n<TR>";
        echo "<TD WIDTH=35 VALIGN=TOP>";
        if (!$entry->teacher) {
            $entry->teacher = $USER->id;
        }
        print_user_picture($entry->teacher, $course->id, $teachers[$entry->teacher]->picture);
        echo "<TD BGCOLOR=\"$colour\">".get_string("feedback").":";
        choose_from_menu($grades, "r$entry->id", $entry->rating, get_string("nograde")."...");
        if ($entry->timemarked) {
            echo "&nbsp;&nbsp;<FONT SIZE=1>".userdate($entry->timemarked)."</FONT>";
        }
        echo "<BR><TEXTAREA NAME=\"c$entry->id\" ROWS=12 COLS=60 WRAP=virtual>";
        p($entry->comment);
        echo "</TEXTAREA><BR>";
        echo "</TD></TR>";
    }
    echo "</TABLE><BR CLEAR=ALL>\n";
}


function dialogue_show_conversation($dialogue, $conversation, $user) {
    global $THEME;
	
	if (! $course = get_record("course", "id", $dialogue->course)) {
        error("Course is misconfigured");
        }
    if (! $cm = get_coursemodule_from_instance("dialogue", $dialogue->id, $course->id)) {
        error("Course Module ID was incorrect");
    }
	
	$timenow = time();
	print_simple_box_start("center");
	echo "<center><TABLE BORDER=1 CELLSPACING=0 valign=top cellpadding=4 width=\"100%\">\n";
		
	echo "<TR>";
	echo "<TD BGCOLOR=\"$THEME->cellheading2\" VALIGN=TOP>\n";
	if ($conversation->userid == $user->id) {
		if (!$otheruser = get_record("user", "id", $conversation->recipientid)) {
			error("User not found");
		}
	}
	else {
		if (!$otheruser = get_record("user", "id", $conversation->userid)) {
			error("User not found");
		}
	}
	// print_user_picture($user->id, $course->id, $user->picture);
	echo "<b>".get_string("dialoguewith", "dialogue", "$otheruser->firstname $otheruser->lastname")."</b>";
	echo "</td></tr>";

	if ($entries = get_records_select("dialogue_entries", "conversationid = $conversation->id", "id")) {
		foreach ($entries as $entry) {
			if ($entry->userid == $user->id) {
				echo "<tr><td  bgcolor=\"#FFFFFF\">\n";
				echo text_to_html("<font size=\"1\">".get_string("onyouwrote", "dialogue", 
                    userdate($entry->timecreated)).
					":</font><br />".$entry->text);
				echo "</td></tr>\n";
			}
			else {
				echo "<tr><td  bgcolor=\"$THEME->body\">\n";
				echo text_to_html("<font size=\"1\">".get_string("onwrote", "dialogue", 
                    userdate($entry->timecreated)." ".$otheruser->firstname).":</font><br />".$entry->text);
				echo "</td></tr>\n";
			}
		}
	}
	echo "</TABLE></center><BR CLEAR=ALL>\n";
	print_simple_box_end();
	print_continue("dialogues.php?id=$cm->id&action=listclosed");
}


function dialogue_show_other_conversations($dialogue, $conversation) {
// prints the other CLOSED conversations for this pair of users
    global $THEME, $USER;
	
	if (! $course = get_record("course", "id", $dialogue->course)) {
        error("Course is misconfigured");
        }
    if (! $cm = get_coursemodule_from_instance("dialogue", $dialogue->id, $course->id)) {
        error("Course Module ID was incorrect");
    }
	
	if (!$user = get_record("user", "id", $conversation->userid)) {
		error("User not found");
		}
	if (!$otheruser = get_record("user", "id", $conversation->recipientid)) {
		error("User not found");
		}
	
	if ($conversations = get_records_select("dialogue_conversations", "dialogueid = $dialogue->id AND 
			(userid = $user->id AND recipientid = $otheruser->id) OR (userid = $otheruser->id AND 
            recipientid = $user->id) AND closed = 1", "timemodified DESC")) {
		if (count($conversations) > 1) {
			$timenow = time();
			foreach ($conversations as $otherconversation) {
				if ($conversation->id != $otherconversation->id) {
					// for this conversation work out which is the other user
                    if ($otherconversation->userid == $USER->id) {
                        if (!$otheruser = get_record("user", "id", $otherconversation->recipientid)) {
                            error("Show other conversations: could not get user record");
                        }
                    }
                    else {
                        if (!$otheruser = get_record("user", "id", $otherconversation->userid)) {
                            error("Show other conversations: could not get user record");
                        }
                    } 
                    print_simple_box_start("center");
					echo "<center><TABLE BORDER=1 CELLSPACING=0 valign=top cellpadding=4 width=\"100%\">\n";
						
					echo "<TR>";
					echo "<TD BGCOLOR=\"$THEME->cellheading2\" VALIGN=TOP>\n";
				    // print_user_picture($otheruser->id, $course->id, $otheruser->picture);
				    echo "<b>".get_string("dialoguewith", "dialogue", 
                        "$otheruser->firstname $otheruser->lastname")."</b>";
                    echo "</td></tr>";
				    if ($entries = get_records_select("dialogue_entries", 
                            "conversationid = $otherconversation->id", "id")) {
						foreach ($entries as $entry) {
							if ($entry->userid == $USER->id) {
								echo "<tr><td  bgcolor=\"#FFFFFF\">\n";
								echo text_to_html("<font size=\"1\">".get_string("onyouwrote", "dialogue", 
                                    userdate($entry->timecreated)).":</font><br />".$entry->text);
								echo "</td></tr>\n";
							}
							else {
								echo "<tr><td  bgcolor=\"$THEME->body\">\n";
								echo text_to_html("<font size=\"1\">".get_string("onwrote", "dialogue", 
                                    userdate($entry->timecreated)." ".$otheruser->firstname).
                                    ":</font><br />".$entry->text);
								echo "</td></tr>\n";
							}
						}
					}
				
					echo "</TABLE></center><BR CLEAR=ALL>\n";
					print_simple_box_end();
				}
            }
        	print_continue("dialogues.php?id=$cm->id&action=listclosed");
        }
  	}
}



?>


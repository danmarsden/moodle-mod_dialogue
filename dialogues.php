<?php   // $Id: dialogues.php,v 1.9.10.11 2009/12/16 01:45:32 deeknow Exp $

/**
 * Displays conversations/posts that are part of a dialogue module instance
 * 
 * ACTIONS handled are:
 *  closeconversation
 *  confirmclose
 *  getsubject
 *  insertentries
 *  openconversation
 *  printdialogue
 *  updatesubject
 *  editreply
 *  editconversation
 * 
 * @package dialogue
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

    require_once('../../config.php');
    require_once('lib.php');
    require_once('locallib.php');
    require_once('dialogue_open_form.php');
    require_once('dialogue_reply_form.php');

    // get parameters
    $params = new stdClass();
    $params->id = required_param('id',PARAM_INT);
    $params->action = required_param('action',PARAM_ALPHA);
    $params->cid = optional_param('cid',0,PARAM_INT);
    $params->pane = optional_param('pane',0,PARAM_INT);
    $params->recipientid = optional_param('recipientid','',PARAM_ALPHANUM);
    $params->subject = optional_param('subject','',PARAM_CLEAN);
    $params->firstentry = optional_param('firstentry', '', PARAM_CLEANHTML);
    $params->entryid = optional_param('entryid', 0, PARAM_INT);
    $params->conversationid = optional_param('conversationid', 0, PARAM_INT);
    $params->deleteattachment = optional_param('deleteattachment', 0, PARAM_INT);
    $params->cancel = optional_param('cancel', '', PARAM_ALPHA);

    if (! $cm = get_coursemodule_from_id('dialogue', $params->id)) {
        error('Course Module ID was incorrect');
    }

    if (! $course = get_record('course', 'id', $cm->course)) {
        error('Course is misconfigured');
    }

    if (! $dialogue = get_record('dialogue', 'id', $cm->instance)) {
        error('Course module dialogue is incorrect');
    }

    require_login($course, false, $cm);
    
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    // set up some general variables
    $usehtmleditor = can_use_html_editor();

    $strdialogues = get_string('modulenameplural', 'dialogue');
    $strdialogue  = get_string('modulename', 'dialogue');

    // ... print the header and...
    $navlinks = array(array('name' => $strdialogues, 'link' => "index.php?id=$course->id", 'type' => 'activity' ),
                      array('name' => format_string($dialogue->name), 'link' => "view.php?id=$cm->id", 'type' => 'activityinstance'),
                      array('name' => $strdialogue, 'link' => "view.php?id=$cm->id", 'type' => 'activityinstance')
                     );
    $navigation = build_navigation($navlinks);                     
    print_header_simple(format_string($dialogue->name), '',$navigation, '', '', true);

    // vet conversation id, if present
    if (! empty($params->cid)) {
        if ($conversation = get_record('dialogue_conversations', 'id', $params->cid)) {
            if ($conversation->userid <> $USER->id and $conversation->recipientid <> $USER->id 
                and ! has_capability('mod/dialogue:viewall', $context)) {
                error('Dialogue id incorrect');
            }
        } else {
            error('Dialogue: Conversation record not found');
        }
    }
    if ($params->cancel) {
        redirect('view.php?id='.$params->id.'&pane='.DIALOGUEPANE_CURRENT, null, 0);
    }


    if ($params->action == 'closeconversation') {
/************** close conversation ************************************/
        if (empty($params->cid)) {
            error('Close dialogue: Missing conversation id');
        }
        if (! set_field('dialogue_conversations', 'closed', 1, 'id', $params->cid)) {
            error('Close dialogue: unable to set closed');
        }
        if (! set_field('dialogue_conversations', 'lastid', $USER->id, 'id', $params->cid)) {
            error('Close dialogue: unable to set lastid');
        }

        add_to_log($course->id, 'dialogue', 'closed', "view.php?id=$cm->id", $params->cid, $cm->id);
        redirect("view.php?id=$cm->id&amp;pane={$params->pane}", get_string('dialogueclosed', 'dialogue'));

    } else if ($params->action == 'confirmclose' ) {
/****************** confirm close ************************************/
        
        if (empty($params->cid)) {
            error('Confirm Close: conversation id missing');
        }
        if (! $conversation = get_record('dialogue_conversations', 'id', $params->cid)) {
            error('Confirm close: cannot get conversation record');
        }
        require_capability('mod/dialogue:close', $context);
        
        if ($conversation->userid == $USER->id) {
            if (! $user = get_record('user', 'id', $conversation->recipientid)) {
                error('Confirm Close: cannot get recipient record');
            }
        }
        else {
            if (! $user = get_record('user', 'id', $conversation->userid)) {
                error('Confirm Close: cannot get user record');
            }
        }
        notice_yesno(get_string('confirmclosure', 'dialogue', fullname($user)),
             "dialogues.php?action=closeconversation&amp;id=$cm->id&amp;cid=$conversation->id&amp;pane={$params->pane}",
             "view.php?id=$cm->id&amp;pane={$params->pane}");

    } else if ($params->action == 'getsubject' ) {
/****************** get subject ************************************/
        
        if (empty($params->cid)) {
            error('Confirm Close: conversation id missing');
        }
        print_heading(get_string('addsubject', 'dialogue'));
        echo "<form name=\"getsubjectform\" method=\"post\" action=\"dialogues.php\">\n";
        echo "<input type=\"hidden\" name=\"action\" value=\"updatesubject\"/>\n";
        echo "<input type=\"hidden\" name=\"id\" value=\"{$params->id}\"/>\n";
        echo "<input type=\"hidden\" name=\"cid\" value=\"{$params->cid}\"/>\n";
        echo "<input type=\"hidden\" name=\"pane\" value=\"{$params->pane}\"/>\n";
        echo "<table align=\"center\" border=\"1\" width=\"60%\">\n";
        echo "<tr><td align=\"right\"><b>".get_string("subject", "dialogue")."</b></td>";
        echo "<td><input type=\"text\" size=\"50\" maxsize=\"100\" name=\"subject\"
                value=\"\" /></td></tr>\n";
        echo "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"".
            get_string("addsubject", "dialogue")."\" /></td></tr>\n";
        echo "</table></form>\n";

    } else if ($params->action == 'insertentries' ) {
/****************** insert conversation entries ******************************/
        
        $timenow = time();
        $n = 0;
        $foundid = 0; // so we can display the entry after posting it
        // get all the open conversations for this user
        if ($conversations = dialogue_get_conversations($dialogue, $USER, 'closed = 0')) {
            foreach ($conversations as $conversation) {
                $textarea_name = "reply$conversation->id";
                $stripped_text = '';
                // check if a post variable named with this conversation id, use the text from it in a new entry 
                if (isset($_POST[$textarea_name])) {
                    $stripped_text = clean_param($_POST[$textarea_name], PARAM_CLEAN);
                }

                if ($stripped_text) {
                    unset($item);
                    $item->dialogueid = $dialogue->id;
                    $item->conversationid = $conversation->id;
                    $item->userid = $USER->id;
                    $item->timecreated = $timenow;
                    $item->timemodified = $item->timecreated;
                    // reverse the dialogue mail default
                    $item->mailed = ! $dialogue->maildefault;
                    
                    $item->text = $stripped_text;
                    if (! $item->id = insert_record('dialogue_entries', $item)) {
                        error('Insert Entries: Could not insert dialogue record!');
                    }
                    if (! set_field('dialogue_conversations', 'lastid', $USER->id, 'id', $conversation->id)) {
                        error('Insert Dialogue Entries: could not set lastid');
                    }
                    if (! set_field('dialogue_conversations', 'lastrecipientid', $conversation->lastid, 'id', $conversation->id)) {
                        error('Insert Dialogue Entries: could not set lastid');
                    }
                    if (! set_field('dialogue_conversations', 'timemodified', $timenow, 'id',
                            $conversation->id)) {
                        error('Insert Dialogue Entries: could not set lastid');
                    }
                    // reset seenon time
                    if (! set_field('dialogue_conversations', 'seenon', 0, 'id',
                            $conversation->id)) {
                        error('Insert Dialogue Entries: could not reset seenon');
                    }
                    add_to_log($course->id, 'dialogue', 'add entry', "view.php?id=$cm->id", $item->id, $cm->id);
                    if ($item->attachment = dialogue_add_attachment($item, 'attachment', $messages)) {
                        set_field('dialogue_entries', 'attachment', $item->attachment, 'id', $item->id);
                    }
                    $n++;
                    $foundid = $conversation->id;
                    dialogue_mark_conversation_read($conversation->id, $USER->id);
                }
            }
        }

        $a = new stdClass();
        $a->number = $n;
        $a->edittime = $dialogue->edittime;
        redirect("dialogues.php?id=$cm->id&amp;action=printdialogue&amp;cid=$foundid", 
                 get_string('numberofentriesadded', 'dialogue', $a));


    } else if ($params->action == 'openconversation' ) {
/****************** open conversation ************************************/

        $timenow = time();
        
        if (empty($params->recipientid)) {
            redirect("view.php?id=$cm->id", get_string('nopersonchosen', 'dialogue'));
        } else {
            if (substr($params->recipientid, 0, 1) == 'g') { // it's a group
                $groupid = intval(substr($params->recipientid, 1));
                $sql = "SELECT MAX(grouping)+1 AS grouping from {$CFG->prefix}dialogue_conversations";
                $grouping = get_field_sql($sql);
                if (! $grouping) {
                    $grouping = 1;
                }
                 
                if ($groupid) { // it's a real group
                    $recipients = get_records_sql("SELECT u.*".
                                " FROM {$CFG->prefix}user u,".
                                " {$CFG->prefix}groups_members g".
                                " WHERE g.groupid = $groupid ".
                                " AND u.id = g.userid");                        
                } else { // it's all participants
                    $recipients = get_course_students($course->id);
                }
            } else {
                $recipients[$params->recipientid] = get_record('user', 'id', $params->recipientid);
                $groupid = 0;
                $grouping = 0;
            }
            if ($recipients) {
                $n = 0;
                foreach ($recipients as $recipient) {
                    if ($recipient->id == $USER->id) { // teacher could be member of a group
                        continue;
                    }
                    if (empty($params->firstentry)) {
                        redirect("view.php?id=$cm->id", get_string('notextentered', 'dialogue'));
                    }
                    unset($conversation);
                    $conversation->dialogueid = $dialogue->id;
                    $conversation->userid = $USER->id;
                    $conversation->recipientid = $recipient->id;
                    $conversation->lastid = $USER->id; // this USER is adding an entry too
                    $conversation->lastrecipientid = $recipient->id;
                    $conversation->timemodified = $timenow;
                    $conversation->subject = $params->subject; // may be blank
                    $conversation->groupid = $groupid;
                    $conversation->grouping = $grouping;
                    
                    if (! $conversation->id = insert_record('dialogue_conversations', $conversation)) {
                        error('Open dialogue: Could not insert dialogue record!');
                    }
                    add_to_log($course->id, 'dialogue', 'open', "view.php?id=$cm->id", $dialogue->id, $cm->id);

                    // now add the entry
                    unset($entry);
                    $entry->dialogueid = $dialogue->id;
                    $entry->conversationid = $conversation->id;
                    $entry->userid = $USER->id;
                    $entry->recipientid = $recipient->id;
                    $entry->timecreated = $timenow;
                    $entry->timemodified = $timenow;
                    // reverse the dialogue default value
                    $entry->mailed = ! $dialogue->maildefault;
                    $entry->text = addslashes($params->firstentry);
                    if (! $entry->id = insert_record('dialogue_entries', $entry)) {
                        error('Insert Entries: Could not insert dialogue record!');
                    }
                    add_to_log($course->id, 'dialogue', 'add entry', "view.php?id=$cm->id", $entry->id, $cm->id);
                    if (isset($srcattachment)) {
                        $entry->attachment = $srcattachment->attachment;
                        $srcdir = dialogue_file_area($srcattachment);
                        $dstdir = dialogue_file_area($entry);
                        copy($srcdir.'/'.$entry->attachment, $dstdir.'/'.$entry->attachment);
                        set_field('dialogue_entries', 'attachment', $entry->attachment, 'id', $entry->id);
                    } elseif ($entry->attachment = dialogue_add_attachment($entry, 'attachment', $messages)) {
                        $srcattachment = clone($entry);
                        set_field('dialogue_entries', 'attachment', $entry->attachment, 'id', $entry->id);
                    }
                    
                    dialogue_mark_conversation_read($conversation->id, $USER->id);
                    $n++;
                }
                
                $a = new stdClass();
                $a->edittime = $dialogue->edittime;
                $a->number = $n;
                if ($n > 1) { // return to dialogue page if more than one recipent 
                    redirect("view.php?id=$cm->id", get_string('numberofentriesadded', 'dialogue', $a));
                } else { // if only one recipent show the new conversation with them
                    redirect("dialogues.php?id=$cm->id&amp;action=printdialogue&amp;cid=$conversation->id", get_string('numberofentriesadded', 'dialogue', $a));
                }
            } else {
                redirect("view.php?id=$cm->id", get_string('noavailablepeople', 'dialogue'));
            }
        }

    } else if ($params->action == 'printdialogue') {
/****************** print dialogue (allowing new entry)********************/

        print_simple_box(format_text($dialogue->intro), 'center');
        echo '<br />';

        dialogue_print_conversation($dialogue, $conversation);


    } else if ($params->action == 'updatesubject') {
/****************** update subject ****************************************/

        if (! $conversation = get_record('dialogue_conversations', 'id', $params->cid)) {
            error('Update Subject: can not get conversation record');
        }

        if (empty($params->subject)) {
            redirect("view.php?id=$cm->id&amp;pane={$params->pane}", get_string('nosubject', 'dialogue'));
        } elseif (! set_field('dialogue_conversations', 'subject', $params->subject, 'id', $params->cid)) {
            error('Update subject: could not update conversation record');
        }
        redirect("view.php?id=$cm->id&amp;pane={$params->pane}", get_string('subjectadded', 'dialogue'));

    } else if ($params->action == 'editreply') {
/****************** edit a reply ****************************************/

        $entry = get_record('dialogue_entries', 'id', $params->entryid);
        
        if (isset($_POST['reply'.$entry->conversationid])) {
            $reply = addslashes(clean_param($_POST['reply'.$entry->conversationid], PARAM_CLEANHTML));
            
            if ($params->deleteattachment && $entry->attachment) {
                $dir = dialogue_file_area_name($entry);
                unlink($CFG->dataroot.'/'.$dir.'/'.$entry->attachment);
                set_field('dialogue_entries', 'attachment', '', 'id', $entry->id);
            }
            
            set_field('dialogue_entries', 'text', $reply, 'id', $entry->id);
            set_field('dialogue_entries', 'timemodified', time(), 'id', $entry->id);
            if ($entry->attachment = dialogue_add_attachment($entry, 'attachment', $messages)) {
                set_field('dialogue_entries', 'attachment', $entry->attachment, 'id', $entry->id);
            }
            
            add_to_log($course->id, 'dialogue', 'edit entry', "view.php?id=$cm->id", $entry->id, $cm->id);
            
            redirect("dialogues.php?id=$cm->id&amp;pane={$params->pane}&amp;action=printdialogue&amp;cid={$entry->conversationid}", 
                get_string('replyupdated', 'dialogue'));
        }

        $mform = new mod_dialogue_reply_form('dialogues.php', array('conversationid' => $entry->conversationid, 'currentattachment' => $entry->attachment));
        $mform->set_data(array('id' => $cm->id,
                               'entryid' => $entry->id,
                               'action' => 'editreply',
                               "reply{$entry->conversationid}" => $entry->text));
        $mform->display();

    } else if ($params->action == 'editconversation') {
/****************** edit a conversation ****************************************/

        $entry = get_record('dialogue_entries', 'id', $params->entryid);
        $conversation = get_record('dialogue_conversations', 'id', $entry->conversationid);
        
        if (! empty($params->firstentry)) {

            
            set_field('dialogue_entries', 'text', addslashes($params->firstentry), 'id', $entry->id);
            set_field('dialogue_entries', 'timemodified', time(), 'id', $entry->id);
            set_field('dialogue_conversations', 'recipientid', $params->recipientid, 'id', $conversation->id);
            set_field('dialogue_conversations', 'lastrecipientid', $params->recipientid, 'id', $conversation->id);
            set_field('dialogue_conversations', 'subject', $params->subject, 'id', $conversation->id);
            
            if ($entry->attachment = dialogue_add_attachment($entry, 'attachment', $messages)) {
                set_field('dialogue_entries', 'attachment', $entry->attachment, 'id', $entry->id);
            }
            
            add_to_log($course->id, 'dialogue', 'edit entry', "view.php?id=$cm->id", $entry->id, $cm->id);
            
            redirect("dialogues.php?id=$cm->id&amp;pane={$params->pane}&amp;action=printdialogue&amp;cid={$conversation->id}",
                    get_string('replyupdated', 'dialogue'));
        }

        if ($names = dialogue_get_available_users($dialogue, $context, $conversation->id)) {
                    
            $mform = new mod_dialogue_open_form('dialogues.php', array('names' => $names));
            $mform->set_data(array('id' => $cm->id,
                                   'recipientid' => $conversation->recipientid,
                                   'entryid' => $entry->id,
                                   'subject' => stripslashes($conversation->subject),
                                   'firstentry' => $entry->text,
                                   'action' => 'editconversation'));
            $mform->display();
        }

    } else {
/*************** no man's land **************************************/
        error('Fatal Error: Unknown Action: '.$params->action."\n");
    }

    print_footer($course);

?>

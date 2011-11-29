<?php   
 /*
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
    $params->entryid = optional_param('entryid', 0, PARAM_INT);
    $params->conversationid = optional_param('conversationid', 0, PARAM_INT);
    $params->deleteattachment = optional_param('deleteattachment', 0, PARAM_INT);
    $params->cancel = optional_param('cancel', '', PARAM_ALPHA);

    // Hack alert, this sux!
    if ($formdata = data_submitted()) {
        if (!confirm_sesskey()) {
            print_error(get_string('confirmsesskeybad'));
        }
        /*print_object($formdata);*/
    }

    if (! $cm = get_coursemodule_from_id('dialogue', $params->id)) {
        print_error('Course Module ID was incorrect');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('Course is misconfigured');
    }

    if (! $dialogue = $DB->get_record('dialogue', array('id' => $cm->instance))) {
        print_error('Course module dialogue is incorrect');
    }

    require_login($course, false, $cm);
    // get module context
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    $PAGE->set_url(new moodle_url('/mod/dialogue/dialogues.php', array('id' => $params->id, 'action' => $params->action)));
    $PAGE->set_cm($cm, $course, $dialogue);
    $PAGE->set_context($context);
    $PAGE->set_title(format_string($dialogue->name));
    $PAGE->set_heading($course->fullname);

    $strdialogues = get_string('modulenameplural', 'dialogue');
    $strdialogue  = get_string('modulename', 'dialogue');

    // vet conversation id, if present
    if (! empty($params->cid)) {
        if ($conversation = $DB->get_record('dialogue_conversations', array('id'=> $params->cid))) {
            if ($conversation->userid <> $USER->id and $conversation->recipientid <> $USER->id 
                and ! has_capability('mod/dialogue:viewall', $context)) {
                print_error('Dialogue id incorrect');
            }
        } else {
            print_error('Dialogue: Conversation record not found');
        }
    }
    if ($params->cancel) {
        redirect(new moodle_url('view.php', array('id'=>$params->id, 'pane'=>DIALOGUEPANE_CURRENT)),
                 null, 0);
    }


    if ($params->action == 'closeconversation') {
/************** close conversation ************************************/
        if (empty($params->cid)) {
            print_error('Close dialogue: Missing conversation id');
        }
        if (! $DB->set_field('dialogue_conversations', 'closed', 1, array('id' => $params->cid))) {
            print_error('Close dialogue: unable to set closed');
        }
        if (! $DB->set_field('dialogue_conversations', 'lastid', $USER->id, array('id' => $params->cid))) {
            print_error('Close dialogue: unable to set lastid');
        }

        add_to_log($course->id, 'dialogue', 'closed', "view.php?id=$cm->id", $params->cid, $cm->id);

        redirect(new moodle_url('/mod/dialogue/view.php', array('id'=> $cm->id,'pane'=>$params->pane)),
                 get_string('dialogueclosed', 'dialogue'));

    } else if ($params->action == 'confirmclose' ) {
/****************** confirm close ************************************/
        
        if (empty($params->cid)) {
            print_error('Confirm Close: conversation id missing');
        }
        if (! $conversation = $DB->get_record('dialogue_conversations', array('id' => $params->cid))) {
            print_error('Confirm close: cannot get conversation record');
        }
        require_capability('mod/dialogue:close', $context);
        
        if ($conversation->userid == $USER->id) {
            if (! $user = $DB->get_record('user', array('id' => $conversation->recipientid))) {
                print_error('Confirm Close: cannot get recipient record');
            }
        }
        else {
            if (! $user = $DB->get_record('user', array('id' => $conversation->userid))) {
                print_error('Confirm Close: cannot get user record');
            }
        }
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(get_string('confirmclosure', 'dialogue', fullname($user)),
             new moodle_url('dialogues.php', array('action'=>'closeconversation',
                            'id'=>$cm->id,
                            'cid'=> $conversation->id,
                            'pane' => $params->pane)),
             new moodle_url('view.php', array('id'=> $cm->id, 'pane' => $params->pane)));
        echo $OUTPUT->footer();
        exit(0);
    } else if ($params->action == 'getsubject' ) {
/****************** get subject ************************************/
        
        if (empty($params->cid)) {
            print_error('Confirm Close: conversation id missing');
        }
        echo $OUTPUT->heading(get_string('addsubject', 'dialogue'));
        echo "<form name=\"getsubjectform\" method=\"post\" action=\"dialogues.php\">\n";
        echo "<input type=\"hidden\" name=\"action\" value=\"updatesubject\"/>\n";
        echo "<input type=\"hidden\" name=\"id\" value=\"{$params->id}\"/>\n";
        echo "<input type=\"hidden\" name=\"cid\" value=\"{$params->cid}\"/>\n";
        echo "<input type=\"hidden\" name=\"pane\" value=\"{$params->pane}\"/>\n";
        echo '<input type="hidden" name="sesskey" value="'. sesskey().'"/>';
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
        if ($conversations = dialogue_get_conversations($dialogue, $USER, array('closed = ?'), array(0))) {
            foreach ($conversations as $conversation) {
                $editorname = "reply$conversation->id";
                if (isset($formdata->{$editorname}['text'])) {
                    unset($item);
                    $item->dialogueid = $dialogue->id;
                    $item->conversationid = $conversation->id;
                    $item->userid = $USER->id;
                    $item->timecreated = $timenow;
                    $item->timemodified = $item->timecreated;
                    // reverse the dialogue mail default
                    $item->mailed = ! $dialogue->maildefault;
                    $item->text = clean_param($formdata->{$editorname}['text'], PARAM_CLEANHTML);
                    $item->format = clean_param($formdata->{$editorname}['format'], PARAM_INT);
                    $item->itemid = clean_param($formdata->{$editorname}['itemid'], PARAM_INT);

                    if (! $item->id = $DB->insert_record('dialogue_entries', $item)) {
                        print_error('Insert Entries: Could not insert dialogue record!');
                    }
                    // save editor embedded files
                    $messagetext = file_save_draft_area_files($item->itemid,
                                                              $context->id,
                                                              'mod_dialogue',
                                                              'entry',
                                                              $item->id,
                                                              array('subdirs'=>true),
                                                              $item->text);
                    
                    $DB->set_field('dialogue_entries', 'text', $messagetext, array('id'=>$item->id));

                    if (! $DB->set_field('dialogue_conversations', 'lastid', $USER->id, array('id' => $conversation->id))) {
                        print_error('Insert Dialogue Entries: could not set lastid');
                    }
                    if (! $DB->set_field('dialogue_conversations', 'lastrecipientid', $conversation->lastid, array('id' => $conversation->id))) {
                        print_error('Insert Dialogue Entries: could not set lastid');
                    }
                    if (! $DB->set_field('dialogue_conversations', 'timemodified', $timenow, array('id'=> $conversation->id))) {
                        print_error('Insert Dialogue Entries: could not set lastid');
                    }
                    // reset seenon time
                    if (! $DB->set_field('dialogue_conversations', 'seenon', 0, array('id'=>$conversation->id))) {
                        print_error('Insert Dialogue Entries: could not reset seenon');
                    }

                    add_to_log($course->id, 'dialogue', 'add entry', "view.php?id=$cm->id", $item->id, $cm->id);
                    if (!empty($formdata->attachment)){
                        if (dialogue_add_attachment(file_get_submitted_draft_itemid('attachment'), $context->id, $item->id)) {
                            $DB->set_field('dialogue_entries', 'attachment', 1, array('id' => $item->id));
                        }
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
        if ($foundid){
             redirect(new moodle_url('dialogues.php',array('id' => $cm->id, 'action' => 'printdialogue', 'cid' => $foundid)) ,
                      get_string('numberofentriesadded', 'dialogue', $a));
        }
        redirect(new moodle_url('view.php', array('id' => $cm->id)));

    } else if ($params->action == 'openconversation' ) {
/****************** open conversation ************************************/

        $timenow = time();
        
        if (empty($params->recipientid)) {
            redirect(new moodle_url('view.php', array('id'=>$cm->id)),
                     get_string('nopersonchosen', 'dialogue'));
        } else {
            if (substr($params->recipientid, 0, 1) == 'g') { // it's a group
                $groupid = intval(substr($params->recipientid, 1));
                $sql = "SELECT MAX(grouping)+1 AS grouping from {dialogue_conversations}";
                $grouping = $DB->get_field_sql($sql);
                if (! $grouping) {
                    $grouping = 1;
                }   
                if ($groupid) { // it's a real group
                    $recipients = $DB->get_records_sql("SELECT u.*".
                                                       " FROM {user} u,".
                                                       " {groups_members} g".
                                                       " WHERE g.groupid = ? ".
                                                       " AND u.id = g.userid", array($groupid));                        
                } else { // it's all participants
                    // get_course_students($course->id) depreciated using bycap
                    $recipients = get_users_by_capability($context, 'mod/dialogue:participate');
                }
            } else {
                $recipients[$params->recipientid] = $DB->get_record('user', array('id' => $params->recipientid));
                $groupid = 0;
                $grouping = 0;
            }
            if ($recipients) {
                $n = 0;
                foreach ($recipients as $recipient) {
                    if ($recipient->id == $USER->id) { // teacher could be member of a group
                        continue;
                    }
                    if (empty($formdata->firstentry['text'])) {
                        redirect(new moodle_url('view.php', array('id'=>$cm->id)),
                                 get_string('notextentered', 'dialogue'));
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
                    if (! $conversation->id = $DB->insert_record('dialogue_conversations', $conversation)) {
                        print_error('Open dialogue: Could not insert dialogue record!');
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
                    $entry->text = clean_param($formdata->firstentry['text'], PARAM_CLEANHTML);
                    $entry->format = clean_param($formdata->firstentry['format'], PARAM_INT);
                    $entry->itemid = clean_param($formdata->firstentry['itemid'], PARAM_INT);
                    if (! $entry->id = $DB->insert_record('dialogue_entries', $entry)) {
                        print_error('Insert Entries: Could not insert dialogue record!');
                    }
                    dialogue_add_attachment(file_get_submitted_draft_itemid('attachment'), $context->id, $entry->id);
                    // save editor embedded files
                    $message = file_save_draft_area_files($entry->itemid,
                                                          $context->id,
                                                          'mod_dialogue',
                                                          'entry',
                                                          $entry->id,
                                                          array('subdirs'=>true),
                                                          $entry->text);
                  
                    $DB->set_field('dialogue_entries', 'text', $message, array('id'=>$entry->id));

                    add_to_log($course->id, 'dialogue', 'add entry', "view.php?id=$cm->id", $entry->id, $cm->id);

                    dialogue_mark_conversation_read($conversation->id, $USER->id);
                    $n++;
                }
                
                $a = new stdClass();
                $a->edittime = $dialogue->edittime;
                $a->number = $n;
                if ($n > 1) { // return to dialogue page if more than one recipent 
                    redirect(new moodle_url('view.php', array('id'=>$cm->id)),
                             get_string('numberofentriesadded', 'dialogue', $a));
                } else { // if only one recipent show the new conversation with them
                    redirect(new moodle_url('dialogues.php', array('id'=>$cm->id, 'action'=>'printdialogue',
                                                                   'cid'=>$conversation->id)),
                             get_string('numberofentriesadded', 'dialogue', $a));
                }
            } else {
                redirect(new moodle_url('view.php', array('id'=>$cm->id)),
                         get_string('noavailablepeople', 'dialogue'));
            }
        }

    } else if ($params->action == 'printdialogue') {
/****************** print dialogue (allowing new entry)********************/

        echo $OUTPUT->header();
        echo $OUTPUT->box(format_text($dialogue->intro), 'generalbox', 'intro');

        dialogue_print_conversation($dialogue, $conversation);

        echo $OUTPUT->footer();
        exit(0);


    } else if ($params->action == 'updatesubject') {
/****************** update subject ****************************************/

        if (! $conversation = $DB->get_record('dialogue_conversations', array('id' => $params->cid))) {
            print_error('Update Subject: can not get conversation record');
        }

        if (empty($params->subject)) {
            redirect(new moodle_url('view.php', array('id'=>$cm->id, 'pane'=>$params->pane)),
                     get_string('nosubject', 'dialogue'));
        } elseif (! $DB->set_field('dialogue_conversations', 'subject', $params->subject, array('id'=>$params->cid))) {
            print_error('Update subject: could not update conversation record');
        }

        redirect(new moodle_url('/mod/dialogue/view.php', array('id'=>$cm->id, 'pane'=>$params->pane)),
                                get_string('subjectadded', 'dialogue'));

    } else if ($params->action == 'editreply') {
/****************** edit a reply ****************************************/

        $entry = $DB->get_record('dialogue_entries', array('id' => $params->entryid));
        $replyid = 'reply'. $entry->conversationid;
       
        if (isset($formdata->{$replyid})) {

            $replytext = clean_param($formdata->{$replyid}['text'], PARAM_CLEAN);

            $replyformat = clean_param($formdata->{$replyid}['format'], PARAM_INT);
            $replyitemid = clean_param($formdata->{$replyid}['itemid'], PARAM_INT);print_object($replyitemid);

            // save editor embedded files
            $message = file_save_draft_area_files($replyitemid,
                                                  $context->id,
                                                  'mod_dialogue',
                                                  'entry',
                                                  $entry->id,
                                                  array('subdirs'=>true),
                                                  $replytext);

            $DB->set_field('dialogue_entries', 'text', $message, array('id' => $entry->id));
            $DB->set_field('dialogue_entries', 'format', $replyformat, array('id' => $entry->id));
            $DB->set_field('dialogue_entries', 'timemodified', time(), array('id' => $entry->id));

            $draftitemid = file_get_submitted_draft_itemid('attachment');
            dialogue_add_attachment($draftitemid, $context->id, $entry->id);

            add_to_log($course->id, 'dialogue', 'edit entry', "view.php?id=$cm->id", $entry->id, $cm->id);
            
            redirect(new moodle_url('dialogues.php', array('id'=>$cm->id, 'pane'=>$params->pane,
                                                           'action'=>'printdialogue', 'cid'=>$entry->conversationid)),
                                    get_string('replyupdated', 'dialogue'));
        }

        

        //$entry->text = file_rewrite_pluginfile_urls($entry->text, 'pluginfile.php', $context->id, 'mod_dialogue', 'entry', $entry->id);

        $draftid_editor = file_get_submitted_draft_itemid($replyid);
        $currenttext = file_prepare_draft_area($draftid_editor, $context->id, 'mod_dialogue', 'entry', empty($entry->id) ? null : $entry->id, array('subdirs'=>true), $entry->text);
        $mform = new mod_dialogue_reply_form('dialogues.php', array('conversationid' => $entry->conversationid,
                                                                    'context' => $context));

        $draftitemid = file_get_submitted_draft_itemid('attachment');
        file_prepare_draft_area($draftitemid, $context->id, 'mod_dialogue', 'attachment', empty($entry->id) ? null : $entry->id);

        $mform->set_data(array('id' => $cm->id,
                               'entryid' => $entry->id,
                               'action' => 'editreply',
                               "reply{$entry->conversationid}" => array('text' => $currenttext,
                                                                        'format' => $entry->format,
                                                                        'itemid' => $draftid_editor),
                                'attachment' => $draftitemid));

        echo $OUTPUT->header();
        echo $OUTPUT->box(format_string($dialogue->name), 'generalbox', 'intro');
        $mform->display();
        echo $OUTPUT->footer($course);
        exit(0);

    } else if ($params->action == 'editconversation') {
/****************** edit a conversation ****************************************/
        if (empty($params->entryid)) {
            print_error('EntryID is missing');
        }
        $entry = $DB->get_record('dialogue_entries', array('id' => $params->entryid));
        $conversation = $DB->get_record('dialogue_conversations', array('id' => $entry->conversationid));

        $firstentry = 'firstentry';
        if (isset($formdata->{$firstentry})) {
            $firstentrytext = clean_param($formdata->{$firstentry}['text'], PARAM_CLEANHTML);
            $firstentryformat = clean_param($formdata->{$firstentry}['format'], PARAM_INT);
            $firstentryitemid = clean_param($formdata->{$firstentry}['itemid'], PARAM_INT);
          
            $DB->set_field('dialogue_entries', 'format', $firstentryformat, array('id' => $entry->id));
            $DB->set_field('dialogue_entries', 'timemodified', time(), array('id' => $entry->id));
            $DB->set_field('dialogue_conversations', 'recipientid', $params->recipientid, array('id' => $conversation->id));
            $DB->set_field('dialogue_conversations', 'lastrecipientid', $params->recipientid, array('id' => $conversation->id));
            $DB->set_field('dialogue_conversations', 'subject', $params->subject, array('id' => $conversation->id));

            $draftitemid = file_get_submitted_draft_itemid('attachment');
            dialogue_add_attachment($draftitemid, $context->id, $entry->id);
            // save editor embedded files
            $message = file_save_draft_area_files($firstentryitemid,
                                                  $context->id,
                                                          'mod_dialogue',
                                                          'entry',
                                                          $entry->id,
                                                          array('subdirs'=>true),
                                                          $firstentrytext);

            $DB->set_field('dialogue_entries', 'text', $message, array('id' => $entry->id));

            add_to_log($course->id, 'dialogue', 'edit entry', "view.php?id=$cm->id", $entry->id, $cm->id);
            
            redirect(new moodle_url('dialogues.php', array('id'=>$cm->id, 'pane'=>$params->pane,
                                                           'action'=>'printdialogue',
                                                           'cid'=>$conversation->id)),
                     get_string('replyupdated', 'dialogue'));
        }
        echo $OUTPUT->header();
        echo $OUTPUT->box(format_string($dialogue->name), 'generalbox', 'intro');
        if ($names = dialogue_get_available_users($dialogue, $context, $conversation->id)) {
            //$entry->text = file_rewrite_pluginfile_urls($entry->text, 'pluginfile.php', $context->id, 'mod_dialogue', 'entry', $entry->id);
            $draftid_editor = file_get_submitted_draft_itemid('firstentry');
            $currenttext = file_prepare_draft_area($draftid_editor, $context->id, 'mod_dialogue', 'entry', empty($entry->id) ? null : $entry->id, array('subdirs'=>true), $entry->text);

            $mform = new mod_dialogue_open_form('dialogues.php', array('context' => $context, 'names' => $names));
            $draftitemid = file_get_submitted_draft_itemid('attachment');
            file_prepare_draft_area($draftitemid, $context->id, 'mod_dialogue', 'attachment', empty($entry->id) ? null : $entry->id);
            $mform->set_data(array('id' => $cm->id,
                                   'recipientid' => $conversation->recipientid,
                                   'entryid' => $entry->id,
                                   'subject' => $conversation->subject,
                                   'firstentry' => array('text' => $currenttext,
                                                         'format' => $entry->format,
                                                         'itemid' => $draftid_editor),
                                   'action' => 'editconversation',
                                   'attachment' => $draftitemid));
            $mform->display();
           
        }
        echo $OUTPUT->footer($course);
        exit(0);
    } else {
/*************** no man's land **************************************/
        print_error('Fatal Error: Unknown Action: '.$params->action."\n");
    }

?>

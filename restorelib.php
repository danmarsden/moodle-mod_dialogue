<?php
    //This php script contains all the stuff to backup/restore
    //dialogue mods

    //This is the "graphical" structure of the dialogue mod:
    //
    //                     dialogue                                      
    //                   (CL,pk->id)
    //                        |
    //                        |
    //                        |
    //               dialogue_conversations
    //            (UL,pk->id, fk->dialogueid)
    //                        |
    //                        |
    //                        |
    //                 dialogue_entries 
    //            (UL,pk->id, fk->conversationid)     
    //
    // Meaning: pk->primary key field of the table
    //          fk->foreign key to link with parent
    //          nt->nested field (recursive data)
    //          CL->course level info
    //          UL->user level info
    //          files->table may have files)
    //
    //-----------------------------------------------------------

    //This function executes all the restore procedure about this mod
    function dialogue_restore_mods($mod,$restore) {

        global $CFG;

        $status = true;

        //Get record from backup_ids
        $data = backup_getid($restore->backup_unique_code,$mod->modtype,$mod->id);

        if ($data) {
            //Now get completed xmlized object
            $info = $data->info;
            //traverse_xmlize($info);                                                               //Debug
            //print_object ($GLOBALS['traverse_array']);                                            //Debug
            //$GLOBALS['traverse_array']="";                                                        //Debug

            //Now, build the dialogue record structure
            $dialogue->course = $restore->course_id;
            $dialogue->name = backup_todb($info['MOD']['#']['NAME']['0']['#']);
            $dialogue->intro = backup_todb($info['MOD']['#']['INTRO']['0']['#']);
            $dialogue->deleteafter = backup_todb($info['MOD']['#']['DELETEAFTER']['0']['#']);
            $dialogue->dialoguetype = backup_todb($info['MOD']['#']['DIALOGUETYPE']['0']['#']);
            $dialogue->multipleconversations = backup_todb($info['MOD']['#']['MULTIPLECONVERSATIONS']['0']['#']);
            $dialogue->maildefault = backup_todb($info['MOD']['#']['MAILDEFAULT']['0']['#']);
            $dialogue->timemodified = backup_todb($info['MOD']['#']['TIMEMODIFIED']['0']['#']);
            $dialogue->edittime = backup_todb($info['MOD']['#']['EDITTIME']['0']['#']);

            //The structure is equal to the db, so insert the dialogue
            $newid = insert_record ("dialogue",$dialogue);

            //Do some output
            echo "<li>".get_string("modulename","dialogue")." \"".format_string($dialogue->name)."\"</li>";
            backup_flush(300);

            if ($newid) {
                //We have the newid, update backup_ids
                backup_putid($restore->backup_unique_code,$mod->modtype,
                             $mod->id, $newid);
                //Now check if want to restore user data and do it.
                if ($restore->mods['dialogue']->userinfo) {
                    //Restore dialogue_conversations
                    $status = dialogue_conversations_restore($mod->id, $newid,$info,$restore);
                }
            } else {
                $status = false;
            }
        } else {
            $status = false;
        }

        return $status;
    }


    //This function restores the dialogue_conversations
    function dialogue_conversations_restore($old_dialogue_id, $new_dialogue_id,$info,$restore) {

        global $CFG;

        $status = true;

        //Get the entries array
        $conversations = $info['MOD']['#']['CONVERSATIONS']['0']['#']['CONVERSATION'];

        if ($conversations) {
            //Iterate over conversations
            for($i = 0; $i < sizeof($conversations); $i++) {
                $conversation_info = $conversations[$i];
                //traverse_xmlize($conversation_info);                                  //Debug
                //print_object ($GLOBALS['traverse_array']);                            //Debug
                //$GLOBALS['traverse_array']="";                                        //Debug

                //We'll need this later!!
                $oldid = backup_todb($conversation_info['#']['ID']['0']['#']);
                $olduserid = backup_todb($conversation_info['#']['USERID']['0']['#']);

                //Now, build the dialogue_ENTRIES record structure
                $conversation->dialogueid = $new_dialogue_id;
                $conversation->userid = backup_todb($conversation_info['#']['USERID']['0']['#']);
                $conversation->recipientid = backup_todb($conversation_info['#']['RECIPIENTID']['0']['#']);
                $conversation->lastid = backup_todb($conversation_info['#']['LASTID']['0']['#']);
                $conversation->timemodified = backup_todb($conversation_info['#']['TIMEMODIFIED']['0']['#']);
                $conversation->closed = backup_todb($conversation_info['#']['CLOSED']['0']['#']);
                $conversation->seenon = backup_todb($conversation_info['#']['SEENON']['0']['#']);
                $conversation->ctype = backup_todb($conversation_info['#']['CTYPE']['0']['#']);
                $conversation->format = backup_todb($conversation_info['#']['FORMAT']['0']['#']);
                $conversation->subject = backup_todb($conversation_info['#']['SUBJECT']['0']['#']);
                $conversation->groupid = backup_todb($conversation_info['#']['GROUPID']['0']['#']);
                $conversation->grouping = backup_todb($conversation_info['#']['GROUPING']['0']['#']);
                
                //We have to recode the userid and recipientid groupid grouping fields
                $user = backup_getid($restore->backup_unique_code,"user",$conversation->userid);
                if ($user) {
                    $conversation->userid = $user->new_id;
                }
                $user = backup_getid($restore->backup_unique_code,"user",$conversation->recipientid);
                if ($user) {
                    $conversation->recipientid = $user->new_id;
                }
                $group = backup_getid($restore->backup_unique_code, 'group', $conversation->groupid);
                if ($group) {
                    $conversation->groupid = $group->new_id;
                }
                
                //The structure is equal to the db, so insert the dialogue_conversation
                $newid = insert_record ("dialogue_conversations",$conversation);

                //Do some output
                if (($i+1) % 50 == 0) {
                    echo ".";
                    if (($i+1) % 1000 == 0) {
                        echo "<br />";
                    }
                    backup_flush(300);
                }

                if ($newid) {
                    //We have the newid, update backup_ids
                    backup_putid($restore->backup_unique_code, "dialogue_conversations",
                            $oldid, $newid);
                    //Now check if want to restore user data and do it.
                    if ($status) {
                        //Restore dialogue_entries
                        $status = dialogue_entries_restore($new_dialogue_id, $newid,$conversation_info,
                                $restore);
                        $status = dialogue_read_restore($new_dialogue_id, $newid,$conversation_info,
                                $restore);                             
                    }
                } else {
                    $status = false;
                }
            }

       }

        return $status;
    }

    //This function restores the dialogue_entries
    function dialogue_entries_restore($new_dialogue_id, $new_conversation_id,$info,$restore) {

        global $CFG;

        $status = true;

        //Get the entries array
        if (isset($info['#']['ENTRIES']['0']['#']['ENTRY'])) {
            $entries = $info['#']['ENTRIES']['0']['#']['ENTRY'];

            //Iterate over entries
            for($i = 0; $i < sizeof($entries); $i++) {
                $entry_info = $entries[$i];
                //traverse_xmlize($entry_info);                                                      //Debug
                //print_object ($GLOBALS['traverse_array']);                                         //Debug
                //$GLOBALS['traverse_array']="";                                                     //Debug

                //We'll need this later!!
                $oldid = backup_todb($entry_info['#']['ID']['0']['#']);
                $olduserid = backup_todb($entry_info['#']['USERID']['0']['#']);

                //Now, build the dialogue_ENTRIES record structure
                $entry->dialogueid = $new_dialogue_id;
                $entry->conversationid = $new_conversation_id;
                $entry->userid = backup_todb($entry_info['#']['USERID']['0']['#']);
                $entry->timecreated = backup_todb($entry_info['#']['TIMECREATED']['0']['#']);
                $entry->mailed = backup_todb($entry_info['#']['MAILED']['0']['#']);
                $entry->text = backup_todb($entry_info['#']['TEXT']['0']['#']);
                $entry->attachment = backup_todb($entry_info['#']['ATTACHMENT']['0']['#']);
                $entry->timemodified = backup_todb($entry_info['#']['TIMEMODIFIED']['0']['#']);

                //We have to recode the userid field
                $user = backup_getid($restore->backup_unique_code,"user",$entry->userid);
                if ($user) {
                    $entry->userid = $user->new_id;
                }

                //The structure is equal to the db, so insert the dialogue_entry
                $newid = insert_record ("dialogue_entries",$entry);

                //Do some output
                if (($i+1) % 50 == 0) {
                    echo ".";
                    if (($i+1) % 1000 == 0) {
                        echo "<br />";
                    }
                    backup_flush(300);
                }

                if ($newid) {
                    //We have the newid, update backup_ids
                    backup_putid($restore->backup_unique_code,"dialogue_entry",$oldid,
                            $newid);
                    
                    //Get old dialogue id from backup_ids
                    $rec = get_record("backup_ids","backup_code",$restore->backup_unique_code,
                                                   "table_name","dialogue",
                                                   "new_id",$new_dialogue_id);

                    $status = dialogue_restore_files($rec->old_id, $new_dialogue_id, $oldid, $newid, $restore);

                } else {
                    $status = false;
                }
            }
        }

        return $status;
    }

    
    //This function restores the dialogue_read
    function dialogue_read_restore($new_dialogue_id, $new_conversation_id,$info,$restore) {

        global $CFG;

        $status = true;

        //Get the entries array
        if (isset($info['#']['READS']['0']['#']['READ'])) {
            $reads = $info['#']['READS']['0']['#']['READ'];

            //Iterate over reads
            for($i = 0; $i < sizeof($reads); $i++) {
                $read_info = $reads[$i];

                //We'll need this later!!
                $oldid = backup_todb($read_info['#']['ID']['0']['#']);
                $olduserid = backup_todb($read_info['#']['USERID']['0']['#']);

                //Now, build the dialogue_ENTRIES record structure
                $read->entryid = backup_todb($read_info['#']['ENTRYID']['0']['#']);
                $read->conversationid = $new_conversation_id;
                $read->userid = backup_todb($read_info['#']['USERID']['0']['#']);
                $read->firstread = backup_todb($read_info['#']['FIRSTREAD']['0']['#']);
                $read->lastread = backup_todb($read_info['#']['LASTREAD']['0']['#']);

                //We have to recode the userid field
                $user = backup_getid($restore->backup_unique_code,"user",$read->userid);
                if ($user) {
                    $entry->userid = $user->new_id;
                }
                //We have to recode the entryid field
                $entry = backup_getid($restore->backup_unique_code,"dialogue_entry",$read->entryid);
                if ($entry) {
                    $read->entryid = $entry->new_id;
                }

                //The structure is equal to the db, so insert the dialogue_entry
                $newid = insert_record ("dialogue_read",$read);

                //Do some output
                if (($i+1) % 50 == 0) {
                    echo ".";
                    if (($i+1) % 1000 == 0) {
                        echo "<br />";
                    }
                    backup_flush(300);
                }
                if (!$newid) {
                    $status = false;
                }
            }
        }

        return $status;
    }

    //This function copies the dialogue related info from backup temp dir to course moddata folder,
    //creating it if needed and recoding everything (dialogue id and entry id)
    function dialogue_restore_files ($olddialogueid, $newdialogueid, $oldentryid, $newentryid, $restore) {

        global $CFG;

        $status = true;
        $todo = false;
        $moddata_path = "";
        $dialogue_path = "";
        $temp_path = "";

        //First, we check to "course_id" exists and create is as necessary
        //in CFG->dataroot
        $dest_dir = $CFG->dataroot."/".$restore->course_id;
        $status = check_dir_exists($dest_dir,true);

        //First, locate course's moddata directory
        $moddata_path = $CFG->dataroot."/".$restore->course_id."/".$CFG->moddata;

        //Check it exists and create it
        $status = check_dir_exists($moddata_path,true);

        //Now, locate dialogue directory
        if ($status) {
            $dialogue_path = $moddata_path."/dialogue";
            //Check it exists and create it
            $status = check_dir_exists($dialogue_path,true);
        }

        //Now locate the temp dir we are restoring from
        if ($status) {
            $temp_path = $CFG->dataroot."/temp/backup/".$restore->backup_unique_code.
                         "/moddata/dialogue/".$olddialogueid."/".$oldentryid;
            //Check it exists
            if (is_dir($temp_path)) {
                $todo = true;
            }
        }

        //If todo, we create the neccesary dirs in course moddata/dialogue
        if ($status and $todo) {
            //First this dialogue id
            $this_dialogue_path = $dialogue_path."/".$newdialogueid;
            $status = check_dir_exists($this_dialogue_path,true);
            //Now this post id
            $post_dialogue_path = $this_dialogue_path."/".$newentryid;
            //And now, copy temp_path to post_dialogue_path
            $status = backup_copy_file($temp_path, $post_dialogue_path);
        }

        return $status;
    }
    
?>

<?php //$Id: backuplib.php,v 1.4.10.2 2009/08/05 05:06:55 deeknow Exp $
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
    //     (UL,pk->id, fk->dialogueid,conversationid)
    //                        |
    //                        |
    //                        |
    //                  dialogue_read
    //   (UL,pk->id, fk->entryid,userid,conversationid)     
    //
    // Meaning: pk->primary key field of the table
    //          fk->foreign key to link with parent
    //          nt->nested field (recursive data)
    //          CL->course level info
    //          UL->user level info
    //          files->table may have files)
    //
    //-----------------------------------------------------------

    function dialogue_backup_mods($bf,$preferences) {

        global $CFG;

        $status = true;

        //Iterate over dialogue table
        $dialogues = get_records ("dialogue","course",$preferences->backup_course,"id");
        if ($dialogues) {
            foreach ($dialogues as $dialogue) {
                $status = dialogue_backup_one_mod($bf, $preferences, $dialogue);
            }
        }
        return $status;
    }

    function dialogue_backup_one_mod($bf, $preferences, $dialogue) {
        
        if (is_numeric($dialogue)) {
            $dialogue = get_record('dialogue','id',$dialogue);
        }
        
        //Start mod
        fwrite ($bf,start_tag("MOD",3,true));
        //Print dialogue data
        fwrite ($bf,full_tag("ID",4,false,$dialogue->id));
        fwrite ($bf,full_tag("MODTYPE",4,false,"dialogue"));
        fwrite ($bf,full_tag("NAME",4,false,format_string($dialogue->name)));
        fwrite ($bf,full_tag("INTRO",4,false,format_text($dialogue->intro)));
        fwrite ($bf,full_tag("DELETEAFTER",4,false,$dialogue->deleteafter));
        fwrite ($bf,full_tag("DIALOGUETYPE",4,false,$dialogue->dialoguetype));
        fwrite ($bf,full_tag("MULTIPLECONVERSATIONS",4,false,$dialogue->multipleconversations));
        fwrite ($bf,full_tag("MAILDEFAULT",4,false,$dialogue->maildefault));
        fwrite ($bf,full_tag("TIMEMODIFIED",4,false,$dialogue->timemodified));
        fwrite ($bf,full_tag("EDITTIME",4,false,$dialogue->edittime));

        //if we've selected to backup users info, then execute backup_dialogue_conversations
        if ($preferences->mods["dialogue"]->userinfo) {
            $status = backup_dialogue_conversations($bf,$preferences,$dialogue->id);
        }
        //End mod
        $status =fwrite ($bf,end_tag("MOD",3,true));
        return($status);
    }
    
    //Backup dialogue_conversations contents (executed from dialogue_backup_mods)
    function backup_dialogue_conversations ($bf,$preferences,$dialogue) {

        global $CFG;

        $status = true;

        $dialogue_conversations = get_records("dialogue_conversations","dialogueid",$dialogue,"id");
        //If there is conversations
        if ($dialogue_conversations) {
            //Write start tag
            $status =fwrite ($bf,start_tag("CONVERSATIONS",4,true));
            //Iterate over each entry
            foreach ($dialogue_conversations as $conversation) {
                //Start entry
                $status =fwrite ($bf,start_tag("CONVERSATION",5,true));
                //Print dialogue_entries contents
                fwrite ($bf,full_tag("ID",6,false,$conversation->id));
                fwrite ($bf,full_tag("USERID",6,false,$conversation->userid));
                fwrite ($bf,full_tag("RECIPIENTID",6,false,$conversation->recipientid));
                fwrite ($bf,full_tag("LASTID",6,false,$conversation->lastid));
                fwrite ($bf,full_tag("TIMEMODIFIED",6,false,$conversation->timemodified));
                fwrite ($bf,full_tag("CLOSED",6,false,$conversation->closed));
                fwrite ($bf,full_tag("SEENON",6,false,$conversation->seenon));
                fwrite ($bf,full_tag("CTYPE",6,false,$conversation->ctype));
                fwrite ($bf,full_tag("FORMAT",6,false,$conversation->format));
                fwrite ($bf,full_tag("SUBJECT",6,false,$conversation->subject));
                fwrite ($bf,full_tag("GROUPID",6,false,$conversation->groupid));
                fwrite ($bf,full_tag("GROUPING",6,false,$conversation->grouping));
               
                //if we've selected to backup users info, then execute backup_dialogue_entries
                if ($preferences->mods["dialogue"]->userinfo) {
                    $status = backup_dialogue_entries($bf,$preferences,$conversation->id);
                    $status = backup_dialogue_read ($bf,$preferences,$conversation->id);
                }
                //End entry
                $status =fwrite ($bf,end_tag("CONVERSATION",5,true));
             }
 
            //Write end tag
            $status =fwrite ($bf,end_tag("CONVERSATIONS",4,true));
        }
        return $status;
    }
 
    //Backup dialogue_entries contents (executed from dialogue_backup_mods)
    function backup_dialogue_entries ($bf,$preferences,$conversationid) {

        global $CFG;

        $status = true;

        $dialogue_entries = get_records("dialogue_entries","conversationid",$conversationid,"id");
        //If there is entries
        if ($dialogue_entries) {
            //Write start tag
            $status =fwrite ($bf,start_tag("ENTRIES",4,true));
            //Iterate over each entry
            foreach ($dialogue_entries as $entry) {
                //Start entry
                $status =fwrite ($bf,start_tag("ENTRY",5,true));
                //Print dialogue_entries contents
                fwrite ($bf,full_tag("ID",6,false,$entry->id));
                fwrite ($bf,full_tag("USERID",6,false,$entry->userid));
                fwrite ($bf,full_tag("TIMECREATED",6,false,$entry->timecreated));
                fwrite ($bf,full_tag("MAILED",6,false,$entry->mailed));
                fwrite ($bf,full_tag("TEXT",6,false,$entry->text));
                fwrite ($bf,full_tag("RECIPIENTID",6,false,$entry->recipientid));
                fwrite ($bf,full_tag("ATTACHMENT",6,false,$entry->attachment));
                fwrite ($bf,full_tag("TIMEMODIFIED",6,false,$entry->timemodified));
                //End entry
                $status =fwrite ($bf,end_tag("ENTRY",5,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag("ENTRIES",4,true));
        }
        return $status;
    }
 
    function backup_dialogue_read ($bf,$preferences,$conversationid) {
        global $CFG;
        
        $status = true;
        
        $dialogue_reads = get_records('dialogue_read', 'conversationid', $conversationid, 'id');
        
        if ($dialogue_reads) {
            //Write start tag
            $status =fwrite ($bf,start_tag("READS",4,true));
            
            foreach($dialogue_reads as $read) {
                $status = fwrite($bf, start_tag('READ', 5, true));
                fwrite ($bf,full_tag("ID",6,false,$read->id));
                fwrite ($bf,full_tag("ENTRYID",6,false,$read->entryid));
                fwrite ($bf,full_tag("USERID",6,false,$read->userid));
                fwrite ($bf,full_tag("FIRSTREAD",6,false,$read->firstread));
                fwrite ($bf,full_tag("LASTREAD",6,false,$read->lastread));
                fwrite ($bf,full_tag("CONVERSATIONID",6,false,$read->conversationid));
                $status =fwrite ($bf,end_tag("READ",5,true));
            }
            $status =fwrite ($bf,end_tag("READS",4,true));
        }
        return($status);
    }
    
   ////Return an array of info (name,value)
   function dialogue_check_backup_mods($course,$user_data=false,$backup_unique_code) {
        //First the course data
        $info[0][0] = get_string("modulenameplural","dialogue");
        if ($ids = dialogue_ids ($course)) {
            $info[0][1] = count($ids);
        } else {
            $info[0][1] = 0;
        }

        //Now, if requested, the user_data
        if ($user_data) {
            $info[1][0] = get_string("entries");
            if ($ids = dialogue_entry_ids_by_course ($course)) {
                $info[1][1] = count($ids);
            } else {
                $info[1][1] = 0;
            }
        }
        return $info;
    }






    // INTERNAL FUNCTIONS. BASED IN THE MOD STRUCTURE

    //Returns an array of dialogues id
    function dialogue_ids ($course) {

        global $CFG;

        return get_records_sql ("SELECT a.id, a.course
                                 FROM {$CFG->prefix}dialogue a
                                 WHERE a.course = '$course'");
    }
   
    //Returns an array of dialogue entries id
    function dialogue_entry_ids_by_course ($course) {

        global $CFG;

        return get_records_sql ("SELECT s.id , s.dialogueid
                                 FROM {$CFG->prefix}dialogue_entries s,
                                      {$CFG->prefix}dialogue a
                                 WHERE a.course = '$course' AND
                                       s.dialogueid = a.id");
    }
?>

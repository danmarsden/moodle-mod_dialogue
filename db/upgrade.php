<?php

function xmldb_dialogue_upgrade($oldversion=0) {

    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

    if ($oldversion < 2010123102) {
    /// Define field introformat to be added to dialogue
        $table = new xmldb_table('dialogue');
        $field = new xmldb_field('introformat');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'intro');

    /// Conditionally launch add field introformat
        if (!$dbman->field_exists($table,$field)) {
            $dbman->add_field($table, $field);
        }


    /// Add field format on table dialogue_entries
        $table = new xmldb_table('dialogue_entries');
        $field = new xmldb_field('format', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'text');

    /// Conditionally launch add field  format
        if (!$dbman->field_exists($table,$field)) {
            $dbman->add_field($table, $field);
        }
    /// dialogue savepoint reached
        upgrade_mod_savepoint(true, 2010123102, 'dialogue');
    }
    if ($oldversion < 2010123103) {
        /////////////////////////////////////
        /// new file storage upgrade code ///
        /////////////////////////////////////

        $fs = get_file_storage();

        $siteid = get_site()->id;

        $base = preg_quote($CFG->wwwroot,"/");

        $empty = $DB->sql_empty(); // silly oracle empty string handling workaround

        $fs = get_file_storage();

        $empty = $DB->sql_empty(); // silly oracle empty string handling workaround

        $sqlfrom = "FROM {dialogue_entries} e
                    JOIN {dialogue_conversations} c ON c.id = e.conversationid
                    JOIN {dialogue} d ON d.id = c.dialogueid
                    JOIN {modules} m ON m.name = 'dialogue'
                    JOIN {course_modules} cm ON (cm.module = m.id AND cm.instance = d.id) ";
                  // WHERE e.attachment <> '$empty' AND e.attachment <> '1'";

        $count = $DB->count_records_sql("SELECT COUNT('x') $sqlfrom");

        $rs = $DB->get_recordset_sql("SELECT e.id, e.text, e.attachment, e.userid, e.recipientid, c.dialogueid, d.course, cm.id AS cmid $sqlfrom ORDER BY d.course, d.id, c.id");
       
        if ($rs->valid()) {
            $pbar = new progress_bar('migratedialoguefiles', 500, true);
            $i = 0;
            foreach ($rs as $entry) {
                $i++;
                upgrade_set_timeout(60); // set up timeout, may also abort execution
                $pbar->update($i, $count, "Migrating dialogue entries - $i/$count.");
                /// Migrate embedded message images
                $context     = get_context_instance(CONTEXT_COURSE, $entry->course);
                $modcontext  = get_context_instance(CONTEXT_MODULE, $entry->cmid);     
                $filerecord  = array('contextid'=>$modcontext->id, 'component'=>'mod_dialogue', 'filearea'=>'entry', 'itemid'=>$entry->id);
                $search="|$CFG->wwwroot/file.php(\?file=)?/$entry->course(/[^\s'\"&\?#]+)|";
                if (preg_match_all($search, $entry->text, $matches)) {        
                    $text = $entry->text;
                    foreach ($matches[2] as $i=>$imagepath) {
                        $path = "/$context->id/course/legacy/0" . $imagepath; 
                        if ($file = $fs->get_file_by_hash(sha1($path))) {
                            try {
                                $fs->create_file_from_storedfile($filerecord, $file);
                                $text = str_replace($matches[0][$i], '@@PLUGINFILE@@'.$imagepath , $text);           
                            } catch (Exception $e) {
                            }
                        }
                    }
                    $DB->set_field('dialogue_entries', 'text', $content, array('id'=> $entry->id));
                }

                
                if ($entry->attachment) {
                    $filepath = "$CFG->dataroot/$entry->course/$CFG->moddata/dialogue/$entry->dialogueid/$entry->id/$entry->attachment";
          
                    if (!is_readable($filepath)) {
                        //file missing??
                        echo $OUTPUT->notification("File not readable, skipping: ".$filepath);
                        $entry->attachment = '';
                        $DB->update_record('dialogue_entries', $entry);
                        continue;
                    }
                    $context = get_context_instance(CONTEXT_MODULE, $entry->cmid);

                
                $filename = clean_param($entry->attachment, PARAM_FILE);
                if ($filename === '') {
                    echo $OUTPUT->notification("Unsupported entry filename, skipping: ".$filepath);
                    $entry->attachment = '';
                    $DB->update_record('dialogue_entries', $entry);
                    continue;
                }
                if (!$fs->file_exists($context->id, 'mod_dialogue', 'attachment', $entry->id, '/', $filename)) {
                    $file_record = array('contextid'=>$context->id, 'component'=>'mod_dialogue', 'filearea'=>'attachment', 'itemid'=>$entry->id, 'filepath'=>'/', 'filename'=>$filename, 'userid'=>$entry->userid);
                    if ($fs->create_file_from_pathname($file_record, $filepath)) {
                        $entry->attachment = '1';
                        $DB->update_record('dialogue_entries', $entry);
                        unlink($filepath);
                    }
                }

                // remove dirs if empty
                @rmdir("$CFG->dataroot/$entry->course/$CFG->moddata/dialogue/$entry->dialogueid/$entry->id");
                @rmdir("$CFG->dataroot/$entry->course/$CFG->moddata/dialogue/$entry->dialogueid");
                @rmdir("$CFG->dataroot/$entry->course/$CFG->moddata/dialogue");
                }
            }

        }
        $rs->close();

        upgrade_mod_savepoint(true, 2010123103, 'dialogue');
        
    }

    if ($oldversion < 2010123104) {

    /// Add field format on table dialogue_entries
        $table = new xmldb_table('dialogue_entries');
        $field = new xmldb_field('format', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'text');

    /// Conditionally launch add field  format
        if (!$dbman->field_exists($table,$field)) {
            $dbman->add_field($table, $field);
        }
    /// dialogue savepoint reached
        upgrade_mod_savepoint(true, 2010123104, 'dialogue');
    }

    if ($oldversion < 2010123105) {

    /// Define field trust to be added to dialogue_entries
        $table = new xmldb_table('dialogue_entries');
        $field = new xmldb_field('trust', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'format');

    /// Conditionally launch add field trust
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

    /// dialogue savepoint reached
        upgrade_mod_savepoint(true, 2010123105, 'dialogue');
    }

    if ($oldversion < 2010123106) {

        $trustmark = '#####TRUSTTEXT#####';
        $rs = $DB->get_recordset_sql("SELECT * FROM {dialogue_entries} WHERE text LIKE ?", array($trustmark.'%'));
        foreach ($rs as $entry) {
            if (strpos($entry->text, $trustmark) !== 0) {
                // probably lowercase in some DBs
                continue;
            }
            $entry->text      = str_replace($trustmark, '', $entry->text);
            $entry->trust = 1;
            $DB->update_record('dialogue_entries', $entry);
        }
        $rs->close();

    /// dialogue savepoint reached
        upgrade_mod_savepoint(true, 2010123106, 'dialogue');
    }

    return true;
}
?>
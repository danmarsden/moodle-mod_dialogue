<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Creates the required new tables, called inside the module upgrade process.
 *
 * @global stdClass $DB
 */
function dialogue_upgrade_prepare_new_tables() {
    global $DB;

    $dbman = $DB->get_manager();

    // Dialogue
    if (!$dbman->table_exists('dialogue')) {
        $table = new xmldb_table('dialogue');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('course', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('intro', XMLDB_TYPE_TEXT, 'small', null, XMLDB_NOTNULL, null, null);
        $table->add_field('introformat', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('maxattachments', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('maxbytes', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('notifications', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1');
        $table->add_field('notificationcontent', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('multipleconversations', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('course_fk', XMLDB_KEY_FOREIGN, array('course'), 'course', array('id'));
        $dbman->create_table($table);
    }
    // Dialogue conversations
    if (!$dbman->table_exists('dialogue_conversations')) {
        $table = new xmldb_table('dialogue_conversations');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('course', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('dialogueid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('dialogueid', XMLDB_INDEX_NOTUNIQUE, array('dialogueid'));
        $dbman->create_table($table);
    }
    // Dialogue participants
    if (!$dbman->table_exists('dialogue_participants')) {
        $table = new xmldb_table('dialogue_participants');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('dialogueid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('conversationid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('userid-dialogueid', XMLDB_INDEX_NOTUNIQUE, array('userid', 'dialogueid'));
        $table->add_index('userid-conversationid', XMLDB_INDEX_NOTUNIQUE, array('userid', 'conversationid'));
        $dbman->create_table($table);
    }

    // Dialogue messages
    if (!$dbman->table_exists('dialogue_messages')) {
        $table = new xmldb_table('dialogue_messages');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('dialogueid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('conversationid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('conversationindex', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('authorid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('body', XMLDB_TYPE_TEXT, 'medium', null, XMLDB_NOTNULL, null, null);
        $table->add_field('bodyformat', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('bodytrust', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('attachments', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('state', XMLDB_TYPE_CHAR, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('authorid', XMLDB_INDEX_NOTUNIQUE, array('authorid'));
        $dbman->create_table($table);
    }

    // Dialogue flags
    if (!$dbman->table_exists('dialogue_flags')) {
        $table = new xmldb_table('dialogue_flags');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('dialogueid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('conversationid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('messageid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('flag', XMLDB_TYPE_CHAR, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('userid-dialogueid', XMLDB_INDEX_NOTUNIQUE, array('userid', 'dialogueid'));
        $table->add_index('userid-conversationid', XMLDB_INDEX_NOTUNIQUE, array('userid', 'conversationid'));
        $table->add_index('userid-messageid', XMLDB_INDEX_NOTUNIQUE, array('userid', 'messageid'));
        $dbman->create_table($table);
    }

    // Dialogue bulk opener rules
    if (!$dbman->table_exists('dialogue_bulk_opener_rules')) {
        $table = new xmldb_table('dialogue_bulk_opener_rules');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('dialogueid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('conversationid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('type', XMLDB_TYPE_CHAR, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('includefuturemembers', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('cutoffdate', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastrun', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $dbman->create_table($table);
    }
}

/**
 * Check if upgrade is complete
 *
 * @global stdClass $DB
 * @return boolean true | false
 */
function dialogue_upgrade_is_complete() {
    global $DB;
    
    $dbmanager = $DB->get_manager();
    if ($dbmanager->table_exists('dialogue_old')) {
        return false;
    }
    return true;
}

/**
 * Get a list of dialogues and their cmid that need to be upgraded. Can return
 * count of matches by reference.
 *
 * @global stdClass $DB
 * @param int $page
 * @param int $limit
 * @param int $matches
 * @return array
 */
function dialogue_upgrade_get_list($sortby, $page = 0, $limit = dialogue::PAGINATION_PAGE_SIZE, &$matches = null) {
    global $DB;

    $countsql   = "SELECT COUNT(1) ";

    $selectsql  = "SELECT cm.id, d.name AS dialoguename, d.timemodified, 
                          c.shortname AS coursename ";
    
    $basesql    = "FROM {dialogue} d
                   JOIN {dialogue_old} o ON o.id = d.id
                   JOIN {course} c ON c.id = d.course
                   JOIN {course_modules} cm ON cm.instance = d.id
                   JOIN {modules} m ON m.id = cm.module
                  WHERE m.name = 'dialogue'";

    $orderbysql = "ORDER BY $sortby";

    $matches = $DB->count_records_sql($countsql . $basesql);

    $records = array();
    if ($matches) { // don't bother running select if zero
        $offset = $page * $limit;
        $records = $DB->get_records_sql($selectsql . $basesql . $orderbysql, null, $offset, $limit);
    }

    return $records;
}

/**
 * Get a dialogue course module that needs to be upgraded by the old dialogueid.
 *
 * @global stdClass $DB
 * @param int $dialogueid
 * @return stdClass
 */
function dialogue_upgrade_get_course_module_by_dialogue($dialogueid) {
    global $DB;

    $sql = "SELECT cm.*, d.name AS dialoguename
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
              JOIN {dialogue} d ON d.id = cm.instance
              JOIN {dialogue_old} o ON o.id = d.id
             WHERE m.name = 'dialogue'
               AND d.id = ?";

    return $DB->get_record_sql($sql, array($dialogueid));
}

/**
 * Get a dialogue course module that needs to be upgraded by the cmid
 *
 * @global stdClass $DB
 * @param int $cmid
 * @return stdClass course_module
 */
function dialogue_upgrade_get_course_module_by_instance($cmid) {
    global $DB;

    $sql = "SELECT cm.*, d.name AS dialoguename
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
              JOIN {dialogue} d ON d.id = cm.instance
              JOIN {dialogue_old} o ON o.id = d.id
             WHERE m.name = 'dialogue'
               AND cm.id = ?";

    return $DB->get_record_sql($sql, array($cmid));
}

/**
 * Get all dialogue course modules in a course that need to be upgraded by the
 * courseid
 *
 * @global stdClass $DB
 * @param int $courseid
 * @return array
 */
function dialogue_upgrade_get_course_modules_by_course($courseid) {
    global $DB;
    
    $sql = "SELECT cm.*, d.name AS dialoguename
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
              JOIN {dialogue} d ON d.id = cm.instance
              JOIN {dialogue_old} o ON o.id = d.id
             WHERE m.name = 'dialogue'
               AND d.course = ?";

    return $DB->get_records_sql($sql, array($courseid));
}

/**
 * This function will upgrade an old dialogue course module to use the new table
 * layout and fileareas. Removes old data on sucess.
 * 
 * @global stdClass $DB
 * @param stdClass $oldcm
 * @return boolean sucess | failure
 */
function dialogue_upgrade_course_module(stdClass $oldcm) {
    global $DB;

    raise_memory_limit(MEMORY_EXTRA);

    $fs = get_file_storage();
    
    // defaults
    $config = get_config('dialogue');
    
    $defaultmaxattachments  = isset($config->maxattachments) ? $config->maxattachments : 5;
    $defaultmaxbytes        = isset($config->maxbytes) ? $config->maxbytes : 10485760; //10MB

    try {
        // start a delegated transaction
        $transaction = $DB->start_delegated_transaction();

        // get old context
        $oldcontext = context_module::instance($oldcm->id);

        // get old dialogue instance to process...
        $olddialogue = $DB->get_record('dialogue_old', array('id'=>$oldcm->instance));

        // build new dialogue record based on old dialogue information
        $dialogue = new stdClass();
        $dialogue->course                   = $olddialogue->course;
        $dialogue->name                     = $olddialogue->name;
        $dialogue->intro                    = $olddialogue->intro;
        $dialogue->introformat              = $olddialogue->introformat;
        $dialogue->maxattachments           = $defaultmaxattachments;
        $dialogue->maxbytes                 = $defaultmaxbytes;
        $dialogue->multipleconversations    = $olddialogue->multipleconversations;
        $dialogue->timemodified             = $olddialogue->timemodified;

        // save dialogue instance.
        $dialogue->id = $DB->insert_record('dialogue', $dialogue);

        // make a new course module
        $newcm = dialogue_upgrade_duplicate_course_module($oldcm, $dialogue->id);

        // get the new context
        $newcontext = context_module::instance($newcm->id);

        // get all old conversations that live in old dialogue instance.
        $olddialogueconversations = $DB->get_records('dialogue_conversations_old', array('dialogueid'=>$olddialogue->id));
        // process old conversations...
        foreach ($olddialogueconversations as $olddialogueconversation) {
            $participants = array();// reset, and start the participant grab
            // add author to participants
            if ($olddialogueconversation->userid) {
                $participants[$olddialogueconversation->userid] = true;
            }
            // add recipient to participants
            if ($olddialogueconversation->recipientid) {
                $participants[$olddialogueconversation->recipientid] = true;
            }
            // is conversation closed, need for state
            $closed = $olddialogueconversation->closed;
            // build new conversation record based on old conversation information
            $dialogueconversation = new stdClass();
            $dialogueconversation->course = $dialogue->course;
            $dialogueconversation->dialogueid = $dialogue->id;
            $dialogueconversation->subject = $olddialogueconversation->subject;
            // save the conversation.
            $dialogueconversation->id = $DB->insert_record('dialogue_conversations', $dialogueconversation);
            // get all old dialogue entries that live in this conversation
            $olddialogueentries = $DB->get_records('dialogue_entries_old', array('conversationid'=>$olddialogueconversation->id), 'timecreated ASC');
            // opening message is always number 1
            $conversationindex = 1;
            // process old conversations... they will become messages
            foreach ($olddialogueentries as $olddialogueentry) {
                // add author to participants
                if ($olddialogueentry->userid) {
                    $participants[$olddialogueentry->userid] = true;
                }
                // add recipient to participants
                if ($olddialogueentry->recipientid) {
                    $participants[$olddialogueentry->recipientid] = true;
                }
                // build new message record based on old dialogue entry information
                $dialoguemessage                     = new stdClass();
                $dialoguemessage->dialogueid         = $dialogueconversation->dialogueid;
                $dialoguemessage->conversationid     = $dialogueconversation->id;
                $dialoguemessage->conversationindex  = $conversationindex;
                $dialoguemessage->authorid           = $olddialogueentry->userid;
                $dialoguemessage->body               = $olddialogueentry->text;
                $dialoguemessage->bodyformat         = $olddialogueentry->format;
                $dialoguemessage->attachments        = $olddialogueentry->attachment;
                $dialoguemessage->state              = ($closed) ? dialogue::STATE_CLOSED : dialogue::STATE_OPEN;
                $dialoguemessage->timemodified       = $olddialogueentry->timemodified;
                // save dialogue message.
                $dialoguemessage->id = $DB->insert_record('dialogue_messages', $dialoguemessage);
                // process files (img's etc) that live in old entry text
                $oldfiles = $fs->get_area_files($oldcontext->id, 'mod_dialogue', 'entry', $olddialogueentry->id);
                if ($oldfiles) {
                   foreach ($oldfiles as $oldfile) {
                       $filerecord = new stdClass();
                       $filerecord->contextid = $newcontext->id;
                       $filerecord->filearea = 'message'; // new filearea
                       $filerecord->itemid = $dialoguemessage->id;
                       $fs->create_file_from_storedfile($filerecord, $oldfile);
                   }
                }
                // process file attachments
                if ($olddialogueentry->attachment) {
                    $oldfiles = $fs->get_area_files($oldcontext->id, 'mod_dialogue', 'attachment', $olddialogueentry->id);
                    if ($oldfiles) {
                        foreach ($oldfiles as $oldfile) {
                            $filerecord = new stdClass();
                            $filerecord->contextid = $newcontext->id;
                            $filerecord->itemid = $dialoguemessage->id;
                            $fs->create_file_from_storedfile($filerecord, $oldfile);
                        }
                    }
                }
                // process old read flags
                $oldreadflags = $DB->get_records('dialogue_read_old', array('entryid'=>$olddialogueentry->id));
                foreach ($oldreadflags as $oldreadflag) {
                    $dialogueflag = new stdClass();
                    $dialogueflag->dialogueid = $dialogue->id;
                    $dialogueflag->conversationid = $dialogueconversation->id;
                    $dialogueflag->messageid = $dialoguemessage->id;
                    $dialogueflag->userid = $oldreadflag->userid;
                    $dialogueflag->flag = dialogue::FLAG_READ;
                    $dialogueflag->timemodified = $oldreadflag->lastread;
                    $dialogueflag->id = $DB->insert_record('dialogue_flags', $dialogueflag);
                }
                // increment conversation index for next message
                $conversationindex++;
            }

            // process participants array that has every user that was part of the conversation.
            foreach (array_keys($participants) as $userid) {
                 $dialogueparticipant = new stdClass();
                 $dialogueparticipant->dialogueid = $dialogue->id;
                 $dialogueparticipant->conversationid = $dialogueconversation->id;
                 $dialogueparticipant->userid = $userid;
                 $dialogueparticipant->id = $DB->insert_record('dialogue_participants', $dialogueparticipant);
            }

        }
        // got this far, ok...
        $transaction->allow_commit();

        // apply role override for legacy type
        dialogue_apply_legacy_permissions($newcontext, $olddialogue->dialoguetype);

        /** Final clean up process **/

        // delete entries related to old instance
        $DB->delete_records('dialogue_entries_old', array('dialogueid' => $olddialogue->id));

        // delete conversations related to old instance
        $DB->delete_records('dialogue_conversations_old', array('dialogueid' => $olddialogue->id));

        // delete old instance
        $DB->delete_records('dialogue_old', array('id' => $olddialogue->id));

        // delete old place holder instance from dialogue
        $DB->delete_records('dialogue', array('id' => $olddialogue->id));

        // delete all files in old context
        $fs->delete_area_files($oldcontext->id, 'mod_dialogue');

        // delete the old context.
        context_helper::delete_instance(CONTEXT_MODULE, $oldcm->id);

        // delete the old module from the course_modules table.
        $DB->delete_records('course_modules', array('id' => $oldcm->id));

        // delete old module from that section.
        delete_mod_from_section($oldcm->id, $oldcm->section);

        rebuild_course_cache($newcm->course, true);

        $result = true;

    } catch(Exception $e) {

        $transaction->rollback($e);

        $result = false;
    }

    return $result;
}

/**
 * Cleanup, remove dialogue_old* tables if no more old dialogue instances to
 * upgrade. Unset config setting and purge cache.
 * 
 * @global stdClass $DB
 */
function dialogue_upgrade_cleanup() {
    global $DB;

    $dbmanager = $DB->get_manager();
    if ($dbmanager->table_exists('dialogue_old')) {
        $count = $DB->count_records('dialogue_old');
        if (!$count) {
            $tables = array('dialogue_old', 'dialogue_conversations_old', 'dialogue_entries_old', 'dialogue_read_old');
            foreach ($tables as $tablename) {
                $dbmanager->drop_table(new xmldb_table($tablename));
            }
            unset_config('upgraderequired', 'dialogue');
            purge_all_caches();
        }
    }
}

/**
 * Create a duplicate course module record so we can create the upgraded
 * assign module alongside the old assignment module.
 *
 * @param stdClass $cm The old course module record
 * @param int $newinstanceid The id of the new instance of the assign module
 * @return mixed stdClass|bool The new course module record or FALSE
 */
function dialogue_upgrade_duplicate_course_module(stdClass $cm, $newinstanceid) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/course/lib.php');
    $newcm = new stdClass();
    $newcm->course           = $cm->course;
    $newcm->module           = $cm->module;
    $newcm->instance         = $newinstanceid;
    $newcm->visible          = $cm->visible;
    $newcm->section          = $cm->section;
    $newcm->score            = $cm->score;
    $newcm->indent           = $cm->indent;
    $newcm->groupmode        = $cm->groupmode;
    $newcm->groupingid       = $cm->groupingid;
    $newcm->groupmembersonly = $cm->groupmembersonly;
    $newcm->completion                = $cm->completion;
    $newcm->completiongradeitemnumber = $cm->completiongradeitemnumber;
    $newcm->completionview            = $cm->completionview;
    $newcm->completionexpected        = $cm->completionexpected;
    if (!empty($CFG->enableavailability)) {
        $newcm->availablefrom             = $cm->availablefrom;
        $newcm->availableuntil            = $cm->availableuntil;
        $newcm->showavailability          = $cm->showavailability;
    }
    $newcm->showdescription = $cm->showdescription;

    $newcmid = add_course_module($newcm);
    $newcm = get_coursemodule_from_id('', $newcmid, $cm->course);
    if (!$newcm) {
        return false;
    }
    $section = $DB->get_record("course_sections", array("id"=>$newcm->section));
    if (!$section) {
        return false;
    }

    $newcm->section = course_add_cm_to_section($newcm->course, $newcm->id, $section->section, $cm->id);

    set_coursemodule_visible($newcm->id, $newcm->visible);

    return $newcm;
}

require_once($CFG->libdir.'/formslib.php');
class dialogue_upgrade_selected_form extends moodleform {
    /**
     * Define this form - is called from parent constructor.
     */
    public function definition() {
        $mform = $this->_form;
        // Visible elements.
        $mform->addElement('hidden', 'selecteddialogues', '', array('class'=>'selecteddialogues'));
        $mform->setType('selecteddialogues', PARAM_SEQUENCE);

        $mform->addElement('submit', 'upgradeselected', get_string('upgradeselected', 'dialogue'));
    }
}

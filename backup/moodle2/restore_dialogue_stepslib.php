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

/**
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards -
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define all the restore steps that will be used by the restore_dialogue_activity_task
 */

/**
 * Structure step to restore one dialogue activity
 */
class restore_dialogue_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        // Main dialogue processor can handle legacy data.
        $paths[] = new restore_path_element('dialogue', '/activity/dialogue');

        $userinfo = $this->get_setting_value('userinfo');
        if ($userinfo) {
            if ($this->task->get_old_moduleversion() < 2013050100) {
                // Restoring from a version 2.0.x -> 2.4.x.
                $paths[] = new restore_path_element('conversation_legacy',
                                                    '/activity/dialogue/conversations/conversation');
                $paths[] = new restore_path_element('entry_legacy',
                                                    '/activity/dialogue/conversations/conversation/entries/entry');
                $paths[] = new restore_path_element('read_legacy',
                                                    '/activity/dialogue/conversations/conversation/read_entries/read_entry');
            } else {
                // Restoring from a version 2.5 or later.
                $paths[] = new restore_path_element('conversation',
                                                    '/activity/dialogue/conversations/conversation');
                $paths[] = new restore_path_element('participant',
                                                    '/activity/dialogue/conversations/conversation/participants/participant');
                $paths[] = new restore_path_element('bulkopenerrule',
                                                    '/activity/dialogue/conversations/conversation/bulkopenerrules/bulkopenerrule');
                $paths[] = new restore_path_element('message',
                                                    '/activity/dialogue/conversations/conversation/messages/message');
                $paths[] = new restore_path_element('flag',
                                                    '/activity/dialogue/conversations/conversation/flags/flag');
            }
        }
        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_dialogue($data) {
        global $DB;

        $pluginconfig = get_config('dialogue');

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->maxattachments = $pluginconfig->maxattachments;
        $data->maxbytes = $pluginconfig->maxbytes;
        $newitemid = $DB->insert_record('dialogue', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_bulkopenerrule($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
    }

    protected function process_conversation($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->course = $this->get_courseid();
        $data->dialogueid = $this->get_new_parentid('dialogue');
        $newitemid = $DB->insert_record('dialogue_conversations', $data);
        $this->set_mapping('dialogue_conversation', $oldid, $newitemid);
    }

    protected function process_conversation_legacy($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->dialogueid = $this->get_new_parentid('dialogue');
        $newitemid = $DB->insert_record('dialogue_conversations', $data);
        $this->set_mapping('dialogue_conversation', $oldid, $newitemid);
        // Unsure if should be using mapping like this.
        $this->set_mapping('dialogue_conversation_var_closed', $oldid, $data->closed);
        // Add user and recipient to participants, process method will do mapping.
        if ($data->userid) {
            $user = new stdClass();
            $user->conversationid = $oldid;
            $user->userid = $data->userid;
            $this->process_participant($user);
        }
        if ($data->recipientid) {
            $recipient = new stdClass();
            $recipient->conversationid = $oldid;
            $recipient->userid = $data->recipientid;
            $this->process_participant($recipient);
        }
    }

    protected function process_entry_legacy($data) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/dialogue/locallib.php');

        $data = (object)$data;
        $oldid = $data->id;

        $data->dialogueid = $this->get_new_parentid('dialogue');
        $data->conversationid = $this->get_mappingid('dialogue_conversation', $data->conversationid);
        $data->conversationindex = 0; // Will need fixing, can only do after execute.
        $data->authorid = $this->get_mappingid('user', $data->userid);
        $data->body = $data->text;
        $data->bodyformat = $data->format;
        $data->bodytrust = $data->trust;
        $data->attachments = $data->attachment;

        $closed = $this->get_mappingid('dialogue_conversation_var_closed', $data->conversationid);
        $data->state = ($closed) ? dialogue::STATE_CLOSED : dialogue::STATE_OPEN;

        $newitemid = $DB->insert_record('dialogue_messages', $data);
        $this->set_mapping('dialogue_message', $oldid, $newitemid, true);

         // Add user and recipient to participants, process method will do mapping.
        $user = new stdClass();
        $user->conversationid = $data->conversationid; // Old conversationid.
        $user->userid = $data->userid; // Old userid.
        $this->process_participant($user);
        // Recipientid maybe null.
        if ($data->recipientid) {
            $recipient = new stdClass();
            $recipient->conversationid = $data->conversationid; // Old conversationid.
            $recipient->userid = $data->recipientid; // Old recipientid.
            $this->process_participant($recipient);
        }
    }

    protected function process_message($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->dialogueid = $this->get_new_parentid('dialogue');
        $data->conversationid = $this->get_mappingid('dialogue_conversation', $data->conversationid);
        $data->authorid = $this->get_mappingid('user', $data->authorid);
        $newitemid = $DB->insert_record('dialogue_messages', $data);
        $this->set_mapping('dialogue_message', $oldid, $newitemid, true);
    }

    protected function process_flag($data) {
        global $DB;

        $data = (object) $data;
        $data->dialogueid = $this->get_new_parentid('dialogue');
        $data->conversationid = $this->get_mappingid('dialogue_conversation', $data->conversationid);
        $data->messageid = $this->get_mappingid('dialogue_message', $data->messageid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $newitemid = $DB->insert_record('dialogue_flags', $data);
    }

    protected function process_participant($data) {
        global $DB;

        $data = (object) $data;
        $data->dialogueid = $this->get_new_parentid('dialogue');
        $data->conversationid = $this->get_mappingid('dialogue_conversation', $data->conversationid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        // Record exists params.
        $params = array('dialogueid' => $data->dialogueid,
                        'conversationid' => $data->conversationid,
                        'userid' => $data->userid);
        if (!$DB->record_exists('dialogue_participants', $params)) {
            $newitemid = $DB->insert_record('dialogue_participants', $data);
        }
    }

    protected function process_read_legacy($data) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/dialogue/locallib.php');

        $data = (object)$data;
        $oldid = $data->id;

        $data->messageid = $data->entryid;
        $data->flag = dialogue::FLAG_READ;
        $data->timemodified = $data->lastread;

        $this->process_flag($data);
    }


    public function add_related_legacy_files($component, $filearea, $mappingitemname) {
        global $CFG, $DB;

        $results = array();
        $restoreid = $this->get_restoreid();
        $oldcontextid = $this->task->get_old_contextid();
        $component = 'mod_dialogue';

        $newfilearea = $filearea;

        if ($filearea == 'entry') {
            $newfilearea = 'message';
        }

        if ($filearea == 'attachment') {
            $newfilearea = 'attachment';
        }

        // Get new context, must exist or this will fail.
        if (!$newcontextid = restore_dbops::get_backup_ids_record($restoreid, 'context', $oldcontextid)->newitemid) {
            throw new restore_dbops_exception('unknown_context_mapping', $oldcontextid);
        }

        $sql = "SELECT id AS bftid, contextid, component, filearea, itemid, itemid AS newitemid, info
                      FROM {backup_files_temp}
                     WHERE backupid = ?
                       AND contextid = ?
                       AND component = ?
                       AND filearea  = ?";

        $params = array($restoreid, $oldcontextid, $component, $filearea);
        $fs = get_file_storage();                      // Get moodle file storage.
        $basepath = $this->get_basepath() . '/files/'; // Get backup file pool base.
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $rec) {
            // Get mapped id.
            $rec->newitemid = $this->get_mappingid('dialogue_message', $rec->itemid);

            if (BACKUP::RELEASE >= '2.6') { // New line of code for 2.6 or breaks.
                $file = (object) backup_controller_dbops::decode_backup_temp_info($rec->info);
            } else {
                $file = (object) unserialize(base64_decode($rec->info));
            }

            // Ignore root dirs (they are created automatically).
            if ($file->filepath == '/' && $file->filename == '.') {
                continue;
            }

            // Set the best possible user.
            $mappeduser = restore_dbops::get_backup_ids_record($restoreid, 'user', $file->userid);
            $mappeduserid = !empty($mappeduser) ? $mappeduser->newitemid : $this->task->get_userid();

            // Dir found (and not root one), let's create it.
            if ($file->filename == '.') {
                $fs->create_directory($newcontextid, $component, $filearea, $rec->newitemid, $file->filepath, $mappeduserid);
                continue;
            }

            if (empty($file->repositoryid)) {
                // This is a regular file, it must be present in the backup pool.
                $backuppath = $basepath . backup_file_manager::get_backup_content_file_location($file->contenthash);

                // The file is not found in the backup.
                if (!file_exists($backuppath)) {
                    $result = new stdClass();
                    $result->code = 'file_missing_in_backup';
                    $result->message = sprintf('missing file %s%s in backup', $file->filepath, $file->filename);
                    $result->level = backup::LOG_WARNING;
                    $results[] = $result;
                    continue;
                }

                // Create the file in the filepool if it does not exist yet.
                if (!$fs->file_exists($newcontextid, $component, $filearea, $rec->newitemid, $file->filepath, $file->filename)) {

                    // If no license found, use default.
                    if ($file->license == null) {
                        $file->license = $CFG->sitedefaultlicense;
                    }

                    $filerecord = array (
                        'contextid'    => $newcontextid,
                        'component'    => $component,
                        'filearea'     => $newfilearea,
                        'itemid'       => $rec->newitemid,
                        'filepath'     => $file->filepath,
                        'filename'     => $file->filename,
                        'timecreated'  => $file->timecreated,
                        'timemodified' => $file->timemodified,
                        'userid'       => $mappeduserid,
                        'author'       => $file->author,
                        'license'      => $file->license,
                        'sortorder'    => $file->sortorder
                    );
                    $fs->create_file_from_pathname($filerecord, $backuppath);
                }

                // Store the the new contextid and the new itemid if we need to remap references to this file later.
                $DB->update_record('backup_files_temp', array(
                    'id' => $rec->bftid,
                    'newcontextid' => $newcontextid,
                    'newitemid' => $rec->newitemid), true);

            } else {
                /* This is an alias - we can't create it yet so we stash it in a temp
                 * table and will let the final task to deal with it.
                 */
                if (!$fs->file_exists($newcontextid, $component, $filearea, $rec->newitemid, $file->filepath, $file->filename)) {
                    $info = new stdClass();
                    // Oldfile holds the raw information stored in MBZ (including reference-related info).
                    $info->oldfile = $file;
                    // Newfile holds the info for the new file_record with the context, user and itemid mapped.
                    $info->newfile = (object) array(
                        'contextid'    => $newcontextid,
                        'component'    => $component,
                        'filearea'     => $newfilearea,
                        'itemid'       => $rec->newitemid,
                        'filepath'     => $file->filepath,
                        'filename'     => $file->filename,
                        'timecreated'  => $file->timecreated,
                        'timemodified' => $file->timemodified,
                        'userid'       => $mappeduserid,
                        'author'       => $file->author,
                        'license'      => $file->license,
                        'sortorder'    => $file->sortorder
                    );

                    restore_dbops::set_backup_ids_record($restoreid, 'file_aliases_queue', $file->id, 0, null, $info);
                }
            }
        }
        $rs->close();

        return $results;
    }

    protected function build_missing_conversation_index() {
        global $DB;
        $dialogueid = $this->get_new_parentid('dialogue');

        $conversations = $DB->get_records('dialogue_conversations', array('dialogueid' => $dialogueid));
        while ($conversations) {
            $conversation = array_shift($conversations);
            $conversationindex = 0;
            $messages = $DB->get_records('dialogue_messages',
                array('conversationid' => $conversation->id), 'timecreated', 'id');
            while ($messages) {
                $message = array_shift($messages);
                $DB->set_field('dialogue_messages', 'conversationindex', ++$conversationindex,
                    array('id' => $message->id));
            }
        }
    }

    protected function after_execute() {
        // Add entry related files.
        $this->add_related_files('mod_dialogue', 'intro', null);

        if ($this->task->get_old_moduleversion() < 2013050100) {
            $this->build_missing_conversation_index();
            $this->add_related_legacy_files('mod_dialogue', 'entry', 'entry');
            $this->add_related_legacy_files('mod_dialogue', 'attachment', 'entry');
        } else {
            $this->add_related_files('mod_dialogue', 'message', 'message');
            $this->add_related_files('mod_dialogue', 'attachment', 'message');
        }
    }
}

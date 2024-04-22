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

namespace mod_dialogue;

/**
 * Library of extra functions for the dialogue module not part of the standard add-on module API set
 * but used by scripts in the mod/dialogue folder
 *
 * @package   mod_dialogue
 * @copyright 2013 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class message
 * @package mod_dialogue
 */
class message implements \renderable {
    /** @var dialogue|null  */
    protected $_dialogue = null;
    /** @var conversation|null  */
    protected $_conversation = null;
    /** @var int  */
    protected $_conversationindex = 0;
    /** @var null  */
    protected $_messageid = null;
    /** @var null  */
    protected $_authorid = null;
    /** @var string  */
    protected $_body = '';
    /** @var int|null  */
    protected $_bodyformat = null;
    /** @var null  */
    protected $_bodydraftid = null;
    /** @var null  */
    protected $_attachmentsdraftid = null;
    /** @var null  */
    protected $_attachments = null;
    /** @var string  */
    protected $_state = dialogue::STATE_DRAFT;
    /** @var int|null  */
    protected $_timecreated = null;
    /** @var int|null  */
    protected $_timemodified = null;
    /** @var null  */
    protected $_form = null;
    /** @var bool  */
    protected $_formdatasaved = false;

    /**
     * Message constructor.
     * @param dialogue|null $dialogue
     * @param conversation|null $conversation
     */
    public function __construct(dialogue $dialogue = null, conversation $conversation = null) {
        global $USER;

        $this->_dialogue = $dialogue;
        $this->_conversation = $conversation;

        $this->_authorid = $USER->id;
        $this->_bodyformat = editors_get_preferred_format();
        $this->_timecreated = time();
        $this->_timemodified = time();
    }

    /**
     * PHP overloading magic to make the $dialogue->course syntax work by redirecting
     * it to the corresponding $dialogue->magic_get_course() method if there is one, and
     * throwing an exception if not. Taken from pagelib.php
     *
     * @param string $name property name
     * @return mixed
     */
    public function __get($name) {
        $getmethod = 'magic_get_' . $name;
        if (method_exists($this, $getmethod)) {
            return $this->$getmethod();
        } else {
            throw new \coding_exception('Unknown property: ' . $name);
        }
    }

    /**
     * Returns true/false if current user is the author
     *
     * @return boolean
     */
    public function is_author() {
        global $USER;
        return ($USER->id == $this->_authorid);
    }

    /**
     * Is participant
     * @return bool
     */
    public function is_participant() {
        global $USER;

        $participants = $this->conversation->participants;
        return in_array($USER->id, array_keys($participants));
    }

    /**
     * Delete
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function delete() {
        global $DB, $USER;

        $context = $this->dialogue->context;
        $fs = get_file_storage();
        // Hasn't been saved yet.
        if (is_null($this->_messageid)) {
            return true;
        }
        // Permission to delete conversation.
        $candelete = ((has_capability('mod/dialogue:delete', $context) && $USER->id == $this->_authorid) ||
            has_capability('mod/dialogue:deleteany', $context));

        if (!$candelete) {
            throw new \moodle_exception('nopermissiontodelete', 'dialogue');
        }
        // Delete message and attachment files for message.
        $fs->delete_area_files($context->id, false, false, $this->_messageid);
        // Delete message.
        $DB->delete_records('dialogue_messages', array('id' => $this->_messageid));

        return true;
    }

    /**
     * Get author.
     * @return \type
     */
    protected function magic_get_author() {
        $dialogue = $this->dialogue;
        return dialogue_get_user_details($dialogue, $this->_authorid);
    }

    /**
     * Get attachments.
     * @return array|\stored_file[]
     * @throws \coding_exception
     */
    protected function magic_get_attachments() {
        $fs = get_file_storage();
        $contextid = $this->dialogue->context->id;
        if ($this->_attachments) {
            return $fs->get_area_files($contextid, 'mod_dialogue', 'attachment', $this->messageid, "timemodified", false);
        }
        return array();
    }

    /**
     * Get messageid
     * @return null
     */
    protected function magic_get_messageid() {
        return $this->_messageid;
    }

    /**
     * Get conversation.
     * @return conversation|null
     * @throws \coding_exception
     */
    protected function magic_get_conversation() {
        if (is_null($this->_conversation)) {
            throw new \coding_exception('Parent conversation is not set');
        }
        return $this->_conversation;
    }

    /**
     * Get Dialogue
     * @return dialogue|null
     * @throws \coding_exception
     */
    protected function magic_get_dialogue() {
        if (is_null($this->_dialogue)) {
            throw new \coding_exception('Parent dialogue is not set');
        }
        return $this->_dialogue;
    }

    /**
     * Get body.
     * @return string
     */
    protected function magic_get_body() {
        return $this->_body;
    }

    /**
     * Get bodydraftid
     * @return null
     */
    protected function magic_get_bodydraftid() {
        return $this->_bodydraftid;
    }

    /**
     * Get body format
     * @return int|null
     */
    protected function magic_get_bodyformat() {
        return $this->_bodyformat;
    }

    /**
     * Get bodyhtml
     * @return string
     */
    protected function magic_get_bodyhtml() {
        $contextid = $this->dialogue->context->id;
        $ret = file_rewrite_pluginfile_urls($this->_body,
            'pluginfile.php', $contextid, 'mod_dialogue', 'message', $this->_messageid);
        return format_text($ret, $this->bodyformat);
    }

    /**
     * Get state.
     * @return string
     */
    protected function magic_get_state() {
        return $this->_state;
    }

    /**
     * Get timemodified
     * @return int|null
     */
    protected function magic_get_timemodified() {
        return $this->_timemodified;
    }

    /**
     * Set flag
     * @param string $flag
     * @param \stdClass $user
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function set_flag($flag, $user = null) {
        global $DB, $USER;

        if (is_null($this->_messageid)) {
            throw new \coding_exception('message must be saved before a user flag can be set');
        }

        if (is_null($user)) {
            $user = $USER;
        }

        $dialogueid = $this->dialogue->dialogueid;
        $conversationid = $this->conversation->conversationid;

        $params = array('messageid' => $this->_messageid, 'userid' => $user->id, 'flag' => $flag);

        if (!$DB->record_exists('dialogue_flags', $params)) {

            $messageflag = new \stdClass();
            $messageflag->dialogueid = $dialogueid;
            $messageflag->conversationid = $conversationid;
            $messageflag->messageid = $this->_messageid;
            $messageflag->userid = $user->id;
            $messageflag->flag = $flag;
            $messageflag->timemodified = time();

            $DB->insert_record('dialogue_flags', $messageflag);
        }

        return true;
    }

    /**
     * Mark read
     * @param \stdClass $user
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function mark_read($user = null) {
        // Only mark read if in a open or closed state.
        return $this->set_flag(dialogue::FLAG_READ, $user);
    }

    /**
     * Mark sent
     * @param \stdClass $user
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function mark_sent($user = null) {
        return $this->set_flag(dialogue::FLAG_SENT, $user);
    }

    /**
     * Set body
     * @param string $body
     * @param string $format
     * @param null $itemid
     */
    public function set_body($body, $format, $itemid = null) {
        $this->_body = $body;
        $this->_bodyformat = $format;

        if ($format == FORMAT_HTML && isset($itemid)) {
            $this->_bodydraftid = $itemid;
            $this->_body = file_rewrite_urls_to_pluginfile($this->_body, $this->_bodydraftid);
        }
    }

    /**
     * Set attachement draft id.
     * @param int $attachmentsdraftitemid
     */
    public function set_attachmentsdraftid($attachmentsdraftitemid) {
        $fileareainfo = file_get_draft_area_info($attachmentsdraftitemid);
        if ($fileareainfo['filecount']) {
            $this->_attachmentsdraftid = $attachmentsdraftitemid;
        }
    }

    /**
     * Set author
     * @param int|\stdClass $authorid
     */
    public function set_author($authorid) {
        if (is_object($authorid)) {
            $authorid = $authorid->id;
        }
        $this->_authorid = $authorid;
    }

    /**
     * Set state
     * @param string $state
     */
    public function set_state($state) {
        $this->_state = $state; // Check actual state - todo.
    }

    /**
     * Save
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function save() {
        global $DB, $USER;

        $admin = get_admin(); // Possible cronjob.
        if ($USER->id != $admin->id && $USER->id != $this->_authorid) {
            throw new \moodle_exception("This doesn't belong to you!");
        }

        $context = $this->dialogue->context; // Needed for filelib functions.
        $dialogueid = $this->dialogue->dialogueid;
        $conversationid = $this->conversation->conversationid;

        $record = new \stdClass();
        $record->id = $this->_messageid;
        $record->dialogueid = $dialogueid;
        $record->conversationid = $conversationid;
        $record->conversationindex = $this->_conversationindex;
        $record->authorid = $this->_authorid;
        // Rewrite body now if has embedded files.
        if (dialogue_contains_draft_files($this->_bodydraftid)) {
            $record->body = file_rewrite_urls_to_pluginfile($this->_body, $this->_bodydraftid);
        } else {
            $record->body = $this->_body;
        }
        $record->bodyformat = $this->_bodyformat;
        // Mark atttachments now if has them.
        if (dialogue_contains_draft_files($this->_attachmentsdraftid)) {
            $record->attachments = 1;
        } else {
            $record->attachments = 0;
        }
        $record->state = $this->_state;
        $record->timecreated = $this->_timecreated;
        $record->timemodified = $this->_timemodified;

        if (is_null($this->_messageid)) {
            // Create new record.
            $this->_messageid = $DB->insert_record('dialogue_messages', $record);
        } else {
            $record->timemodified = time();
            // Update existing record.
            $DB->update_record('dialogue_messages', $record);
        }
        // Deal with embedded files.
        if ($this->_bodydraftid) {

            file_save_draft_area_files($this->_bodydraftid, $context->id, 'mod_dialogue', 'message', $this->_messageid);
        }
        // Deal with attached files.
        if ($this->_attachmentsdraftid) {

            file_save_draft_area_files($this->_attachmentsdraftid, $context->id, 'mod_dialogue', 'attachment', $this->_messageid);
        }

        return true;
    }

    /**
     * Send
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function send() {
        global $DB;

        // Add author to participants and save.
        $this->conversation->add_participant($this->_authorid);
        $this->conversation->save_participants();

        // Update state to open.
        $this->_state = dialogue::STATE_OPEN;
        $DB->set_field('dialogue_messages', 'state', $this->_state, array('id' => $this->_messageid));

        // Setup information for messageapi object.
        $cm = $this->dialogue->cm;
        $conversationid = $this->conversation->conversationid;
        $course = $this->dialogue->course;
        $context = $this->dialogue->context;
        $userfrom = $DB->get_record('user', array('id' => $this->_authorid), '*', MUST_EXIST);
        $subject = format_string($this->conversation->subject, true, array('context' => $context));

        $a = new \stdClass();
        $a->userfrom = fullname($userfrom);
        $a->subject = $subject;
        $url = new \moodle_url('/mod/dialogue/view.php', array('id' => $cm->id));
        $a->url = $url->out(false);

        $posthtml = get_string('messageapibasicmessage', 'dialogue', $a);
        $posttext = html_to_text($posthtml);
        $smallmessage = get_string('messageapismallmessage', 'dialogue', fullname($userfrom));

        $contexturlparams = array('id' => $cm->id, 'conversationid' => $conversationid);
        $contexturl = new \moodle_url('/mod/dialogue/conversation.php', $contexturlparams);
        $contexturl->set_anchor('m' . $this->_messageid);

        // Flags and messaging.
        $participants = $this->conversation->participants;
        foreach ($participants as $participant) {
            if ($participant->id == $this->_authorid) {
                // So unread flag count displays properly for author, they wrote it, they should of read it.
                $this->set_flag(dialogue::FLAG_READ, $this->author);
                continue;
            }
            // Give participant a sent flag.
            $this->set_flag(dialogue::FLAG_SENT, $participant);

            $userto = $DB->get_record('user', array('id' => $participant->id), '*', MUST_EXIST);

            $eventdata = new \core\message\message();
            $eventdata->courseid = $course->id;
            $eventdata->component = 'mod_dialogue';
            $eventdata->name = 'post';
            $eventdata->userfrom = $userfrom;
            $eventdata->userto = $userto;
            $eventdata->subject = $subject;
            $eventdata->fullmessage = $posttext;
            $eventdata->fullmessageformat = FORMAT_HTML;
            $eventdata->fullmessagehtml = $posthtml;
            $eventdata->smallmessage = $smallmessage;
            $eventdata->notification = 1;
            $eventdata->contexturl = $contexturl->out(false);
            $eventdata->contexturlname = $subject;

            $result = message_send($eventdata);
        }

        return true;
    }

    /**
     * Message is marked as trash so can be deleted at a later time.
     *
     * @throws \moodle_exception
     */
    public function trash() {
        global $DB;

        // Can only only trash drafts.
        if ($this->state != dialogue::STATE_DRAFT) {
            throw new \moodle_exception('onlydraftscanbetrashed', 'dialogue');
        }

        // Update state to trashed.
        $this->_state = dialogue::STATE_TRASHED;
        $DB->set_field('dialogue_messages', 'state', $this->_state, array('id' => $this->_messageid));
    }
}

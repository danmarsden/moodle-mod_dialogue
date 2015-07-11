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

defined('MOODLE_INTERNAL') || die();

class conversation extends message {

    protected $_conversationid = null;

    protected $_conversationindex = 1;

    /** @var string  subject or topic of conversation */
    public $_subject = '';
    /** @var int userid of owner, can be changed */
    public $_owner = 0;
    /** @var int userid of person to started conversation */
    public $_instigator = 0;
    /** @var int userid of person who conversation was opened with */
    public $_recipient = 0;
    /** @var int count of messages belonging to open conversation */
    public $_messagecount = 0;
    /** @var int openrule is the conversation a template with open rule */
    public $_openrule = 0;

    /** @var rule  */
    protected $_rule = null;
    /** @var participants  */
    protected $_participants = null;

    protected $_replies = array();

    protected $_receivedby = null;

    /**
     *
     * @global type $DB
     * @param dialogue $dialogue
     * @param type $conversationid
     */
    public function __construct(dialogue $dialogue, $conversationid = null) {
        global $USER;
        parent::__construct($dialogue, $this);
        $this->_owner = $USER->id;
        $this->_instigator = $USER->id;
        if ($conversationid) {
            $this->_conversationid = $conversationid;
            $this->load();
        }
    }

    /**
     * Attaches rule class to conversation and loads from database if
     * data exists.
     *
     * @return rule
     */
    public function get_rule() {
        if (is_null($this->_rule)) {
            $this->_rule = new rule($this);
        }
        return $this->_rule;
    }

    public function get_participants() {
        if (is_null($this->_participants)) {
            $this->_participants = new participants($this);
        }
        return $this->_participants;
    }
    /**
     * Sets up a new conversation based on current conversation and dialogue, and
     * copies:
     *
     *  -   author
     *  -   subject
     *  -   body
     *  -   attachments
     *  -   state
     *
     * any associated files are moved to draft area
     *
     * @return conversation
     */
    public function copy() {
        // create new conversation,
        $copy = new conversation($this->dialogue);

        $copy->set_subject($this->_subject);

        $copy->set_owner($this->_owner);

        $copy->set_instigator($this->_instigator);

        $copy->set_author($this->_authorid);
        // prep html linked embedded if html, move to draft area
        if ($this->bodyformat == FORMAT_HTML) {
            // html
            $context = $this->dialogue->context;
            $body = file_prepare_draft_area($copy->_bodydraftid, $context->id, 'mod_dialogue', 'message', $this->messageid, null, $this->body);
        } else {
            // plaintext
            $body = $this->body;
        }
        // set the body up on the conversation
        $copy->set_body($body, $this->bodyformat);
        // prep attachments, move to draft area
        if ($this->attachments) {
            $copy->_attachments = true;
            $context = $this->dialogue->context;
            file_prepare_draft_area($copy->_attachmentsdraftid, $context->id, 'mod_dialogue', 'attachment', $this->messageid);
        }
        // must set state to draft as a copy
        $copy->set_state(dialogue::STATE_DRAFT);
        // return copied conversation
        return $copy;
    }

    public function close() {
        global $DB, $USER;

        $context = $this->dialogue->context;
        $cm      = $this->dialogue->cm;
        $course  = $this->dialogue->course;

        // is this a draft
        if (is_null($this->_conversationid)) {
            throw new \moodle_exception('cannotclosedraftconversation', 'dialogue');
        }
        // permission check
        $canclose = (($this->_authorid == $USER->id) or has_capability('mod/dialogue:closeany', $context));
        if (!$canclose) {
            throw new \moodle_exception('nopermissiontoclose', 'dialogue');
        }

        $openstate = dialogue::STATE_OPEN;
        $closedstate = dialogue::STATE_CLOSED;
        $params = array('conversationid' => $this->conversationid, 'state' => $openstate);

        // close all messages in conversation that have a open state, we don't worry about drafts etc
        $DB->set_field('dialogue_messages', 'state', $closedstate, $params);

        return true;
    }

    public function delete() {
        global $DB, $USER;

        $cm      = $this->dialogue->cm;
        $course  = $this->dialogue->course;
        $context = $this->dialogue->context;

        // hasn't been saved yet
        if (is_null($this->_conversationid)) {
            return true;
        }
        // permission to delete conversation
        $candelete = ((has_capability('mod/dialogue:delete', $context) and $USER->id == $this->_authorid) or
            has_capability('mod/dialogue:deleteany', $context));

        if (!$candelete) {
            throw new \moodle_exception('nopermissiontodelete', 'dialogue');
        }
        // delete flags
        $DB->delete_records('dialogue_flags', array('conversationid' => $this->_conversationid));
        // delete bulk open rules
        $DB->delete_records('dialogue_bulk_opener_rules', array('conversationid' => $this->_conversationid));
        // delete participants
        $DB->delete_records('dialogue_participants', array('conversationid' => $this->_conversationid));
        // delete replies
        foreach ($this->replies() as $reply) {
            // delete reply
            $reply->delete();
        }
        // delete conversation
        $DB->delete_records('dialogue_conversations', array('id' => $this->_conversationid));

        parent::delete();

    }

    /**
     * Load DB record data onto Class, conversationid needed.
     *
     * @global stdClass $DB
     * @throws coding_exception
     */
    protected function load() {
        global $DB;

        if (is_null($this->_conversationid)) {
            throw new \coding_exception('Conversation identifier not set so cannot load!');
        }

        $sql = "SELECT dc.subject, dc.owner, dc.instigator, dc.recipient, dc.messagecount, dc.openrule,
                       dm.*
                  FROM {dialogue_conversations} dc
                  JOIN {dialogue_messages} dm ON dm.conversationid = dc.id
                 WHERE dm.conversationindex = 1
                   AND dc.id = :conversationid";

        $record = $DB->get_record_sql($sql, array('conversationid' => $this->_conversationid), MUST_EXIST);

        $this->set_subject($record->subject);
        $this->set_owner($record->owner);
        $this->set_instigator($record->instigator);
        $this->set_recipient($record->recipient);
        $this->_messagecount    = $record->messagecount;
        $this->_openrule        = $record->openrule;
        $this->set_author($record->authorid);
        $this->_messageid       = $record->id;
        $this->_body            = $record->body;
        $this->_bodyformat      = $record->bodyformat;
        $this->set_body($record->body, $record->bodyformat);
        $this->_attachments     = $record->attachments;
        $this->_state           = $record->state;
        $this->_timemodified    = $record->timemodified;
    }

    public function load_form_data(\stdClass $data) {
        // Single recipient mode.
        if (isset($data->recipient)) {
            $this->_recipient = $data->recipient;
        }
        // A rule for bulk opening of conversations.
        if (isset($data->rule)) {
            $this->get_rule()->set($data->rule);
        }
        // Set the subject.
        $this->set_subject($data->subject);
        // Set body up.
        $this->set_body($data->body['text'], $data->body['format'], $data->body['itemid']);
        // Attachments.
        if (isset($data->attachments)) {
            $this->set_attachmentsdraftid($data->attachments['itemid']);
        }
    }

    protected function magic_get_conversationid() {
        return $this->_conversationid;
    }

    protected function magic_get_receivedby() {
        global $DB;

        if (is_null($this->_receivedby)) {
            $params = array('conversationid' => $this->conversationid,
                'flag' => dialogue::FLAG_SENT);

            $this->_receivedby = $DB->get_records('dialogue_flags', $params, null, 'userid, timemodified');
        }
        return $this->_receivedby;
    }
    /**
     * Do not call this method directly
     *
     * @return string subject
     */
    protected function magic_get_subject() {
        return $this->_subject;
    }

    public function prepare_form_data() {
        $data               = parent::prepare_form_data();
        $data['subject']    = $this->_subject;
        $data['recipient']  = null;
        if ($this->_recipient) {
            $data['recipient']  = dialogue_get_user_details($this->_dialogue, $this->_recipient);
        }
        if ($this->get_rule()) {
            $data['rule']   = $this->get_rule()->export();
        }
        return $data;
    }

    /**
     * Return a reply mapped to current dialogue.
     *
     * @return \dialogue_reply
     * @throws moodle_exception
     */
    public function reply() {
        if ($this->state != dialogue::STATE_OPEN) {
            throw new \moodle_exception('a reply can only be started when a conversation is open');
        }
        return new reply($this->_dialogue, $this);
    }

    public function replies($index = null) {
        global $DB;

        if (empty($this->_replies)) {
            // only all replies in an open or close state, a reply should never be automated
            // and drafts are no in the line of published conversation.
            $items = array(dialogue::STATE_OPEN, dialogue::STATE_CLOSED);

            list($insql, $inparams) = $DB->get_in_or_equal($items, SQL_PARAMS_NAMED, 'viewstate');

            $sql = "SELECT dm.*
                      FROM {dialogue_messages} dm
                     WHERE dm.conversationindex > 1
                       AND dm.state $insql
                       AND dm.conversationid = :conversationid
                  ORDER BY dm.conversationindex ASC";

            $params = array('conversationid' => $this->conversationid) + $inparams;

            $records = $DB->get_records_sql($sql, $params);
            foreach ($records as $record) {
                $reply = new reply($this->_dialogue, $this);
                $reply->load($record);
                $this->_replies[$record->id] = $reply;
            }
        }
        if ($index) {
            if (!isset($this->_replies[$index])) {
                throw new \coding_exception('index not defined');
            }
            return $this->_replies[$index];
        }
        return $this->_replies;
    }

    public function save() {
        global $DB, $USER;

        $admin = get_admin(); // possible cronjob
        if ($USER->id != $admin->id and $USER->id != $this->_authorid) {
            throw new \moodle_exception("This conversation doesn't belong to you!");
        }

        $course     = $this->dialogue->course;
        $dialogueid = $this->dialogue->dialogueid;

        // Conversation record.
        $record                 = new \stdClass();
        $record->id             = $this->_conversationid;
        $record->course         = $course->id;
        $record->dialogueid     = $dialogueid;
        $record->subject        = $this->_subject;
        $record->owner          = $this->_owner;
        $record->instigator     = $this->_instigator;
        $record->recipient      = $this->_recipient;
        $record->messagecount   = $this->_conversationindex; // @TODO this is a dirty hack until code created.

        $rule = $this->get_rule();
        if (!$rule->is_empty()) {
            $record->openrule = 1;
        }
        // We need a conversationid.
        if (is_null($this->_conversationid)) {
            // Create new record.
            $this->_conversationid = $DB->insert_record('dialogue_conversations', $record);
        } else {
            $record->timemodified = time();
            // Update existing record.
            $DB->update_record('dialogue_conversations', $record);
        }
        // Save rule, won't save if no type or source.
        if ($rule->save()) {
            $this->_openrule = 1;
        }
        // Re-use, let dialogue_message do it's thing.
        parent::save();
    }

    public function send() {
        global $USER, $DB, $CFG;

        $cm      = $this->dialogue->cm;
        $course  = $this->dialogue->course;

        if (empty($this->_subject)) {
            throw new \moodle_exception("No subject, won't send!");
        }

        if (empty($this->_body)) {
            throw new \moodle_exception("No body, won't send!");
        }

        self::set_state(dialogue::STATE_OPEN);
        self::save();

        // Has rule, should process
        if ($this->_openrule) {
            require_once($CFG->dirroot . '/mod/dialogue/lib.php');
            dialogue_run_open_rules($this->_conversationid, false);
            return;
        }

        parent::send();
    }

    public function set_owner($owner) {
        $this->_owner = $owner;
        $this->get_participants()->add($owner);
    }

    public function set_instigator($instigator) {
        $this->_instigator = $instigator;
        $this->get_participants()->add($instigator);
    }

    public function set_recipient($recipient) {
        $this->_recipient = $recipient;
        $this->get_participants()->add($recipient);
    }

    public function set_subject($subject) {
        $this->_subject = format_string($subject);
    }

}

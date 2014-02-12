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
 * Library of extra functions for the dialogue module not part of the standard add-on module API set
 * but used by scripts in the mod/dialogue folder
 *
 * @package   mod_dialogue
 * @copyright 2013 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 *
 * Provides access to dialogue module constants.
 *
 * Sets up and provides access to dialogue module application caches.
 */
class dialogue {
    /** The state to indicate a open conversation and replies **/
    const STATE_OPEN            = 'open';
    /** The state to indicate a conversation or reply that is a draft **/
    const STATE_DRAFT           = 'draft';
    /** The state to indicated a conversation used in bulk creation of other
        conversations  **/
    const STATE_BULK_AUTOMATED  = 'bulkautomated';
    /** The state to indicate a closed conversation and replies **/
    const STATE_CLOSED          = 'closed';
    /** The state to indicate a draft conversation or reply that has been
     *  discarded **/
    const STATE_TRASHED         = 'trashed';

    const FLAG_SENT = 'sent';
    const FLAG_READ = 'read';
    const SHOW_MINE = 'mine';
    const SHOW_EVERYONE = 'everyone';
    const PAGINATION_PAGE_SIZE = 20;
    const PAGINATION_MAX_RESULTS = 1000;
    const LEGACY_TYPE_TEACHER2STUDENT = 0;
    const LEGACY_TYPE_STUDENT2STUDENT = 1;
    const LEGACY_TYPE_EVERYONE = 2;

    
    protected $_course  = null;
    protected $_module  = null;
    protected $_config  = null;
    protected $_cm      = null;
    protected $_context = null;

    /**
     * Constructor for dialogue class, requires course module to load
     * context, passing optional course and activity record objects will
     * save extra database calls.
     *
     * @param type $cm
     * @param type $course
     * @param type $module
     */
    public function __construct($cm, $course = null, $module = null) {
        $this->set_cm($cm);

        $context = context_module::instance($cm->id, MUST_EXIST);
        $this->set_context($context);

        if (!is_null($course)) {
            $this->set_course($course);
        }

        if (!is_null($module)) {
            $this->set_activity_record($module);
        }
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
            throw new coding_exception('Unknown property: ' . $name);
        }
    }

    /**
     * Return message states that can be marked with a read flag
     * FLAG_READ
     *
     * @return array
     */
    public static function get_unread_states() {
        return array(dialogue::STATE_OPEN, dialogue::STATE_CLOSED);
    }

    /**
     * Set up a dialogue based on dialogue identifier
     *
     * @param int $dialogueid
     * @return dialogue
     */
    public static function instance($dialogueid) {
        $cm = get_coursemodule_from_instance('dialogue', $dialogueid, 0, false, MUST_EXIST);
        return new dialogue($cm);
    }

    /**
     * Return module visibility
     *
     * @return boolean
     */
    public function is_visible() {
        if ($this->_cm->visible == false) {
            return false;
        }
        if (is_null($this->_course)) {
            $this->load_course();
        }
        if ($this->_course->visible == false) {
            return false;
        }
        return true;
    }

    protected function load_activity_record() {
        global $DB;

        $this->_module = $DB->get_record($this->_cm->modname, array('id'=>$this->_cm->instance), '*', MUST_EXIST);
    }

    protected function load_course() {
        global $DB;

        $this->_course = $DB->get_record('course', array('id'=>$this->_cm->course), '*', MUST_EXIST);
    }

    protected function magic_get_config() {

        if (is_null($this->_config)) {
            $this->_config = get_config('dialogue');
        }
        return $this->_config;
    }

    protected function magic_get_cm() {
        return $this->_cm;
    }

    protected function magic_get_context() {
        return $this->_context;
    }

    protected function magic_get_activityrecord() {
        if (is_null($this->_module)) {
            $this->load_activity_record();
        }
        return $this->_module;
    }

    protected function magic_get_course() {
        if (is_null($this->_course)) {
            $this->load_course();
        }
        return $this->_course;
    }

    protected function magic_get_dialogueid() {
        if (is_null($this->_module)) {
            $this->load_activity_record();
        }
        return $this->_module->id;
    }

    protected function set_activity_record($module) {
        if (is_null($this->_course)) {
            $this->load_course();
        }
        if ($module->id != $this->_cm->instance || $module->course != $this->_course->id) {
            throw new coding_exception('The activity record you are trying to set does not seem to correspond to the cm that has been set.');
        }
        $this->_module = $module;
    }

    protected function set_cm($cm) {
        if (!isset($cm->id) || !isset($cm->course)) {
            throw new coding_exception('Invalid $cm parameter, it has to be record from the course_modules table.');
        }
        $this->_cm = $cm;
    }

    protected function set_context(context_module $context) {
        $this->_context = $context;
    }

    protected function set_course($course) {
        if ($course->id != $this->_cm->course) {
            throw new coding_exception('The course you are trying to set does not seem to correspond to the cm that has been set.');
        }
        $this->_course = $course;
    }

}

/**
 *
 */
class dialogue_message implements renderable {

    protected $_dialogue = null;
    protected $_conversation = null;
    protected $_conversationindex = 0;
    protected $_messageid = null;
    protected $_authorid = null;
    protected $_body = '';
    protected $_bodyformat = null;
    protected $_bodydraftid = null;
    protected $_attachmentsdraftid = null;
    protected $_attachments = null;
    protected $_state = dialogue::STATE_DRAFT;
    protected $_timecreated = null;
    protected $_timemodified = null;
    protected $_form = null;
    protected $_formdatasaved = false;

    public function __construct(dialogue $dialogue = null, dialogue_conversation $conversation = null) {
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
            throw new coding_exception('Unknown property: ' . $name);
        }
    }

    /**
     * Returns true/false if current user is the author
     * of this message;
     *
     * @global type $USER
     * @return boolean
     */
    public function is_author() {
        global $USER;
        return ($USER->id == $this->_authorid);
    }

    public function is_participant() {
        global $USER;

        $participants = $this->conversation->participants;
        return in_array($USER->id, array_keys($participants));
    }

    public function delete() {
        global $DB, $USER;

        $context = $this->dialogue->context;
        $fs = get_file_storage();
        // hasn't been saved yet
        if (is_null($this->_messageid)) {
            return true;
        }
        // permission to delete conversation
        $candelete = ((has_capability('mod/dialogue:delete', $context) and $USER->id == $this->_authorid) or
                       has_capability('mod/dialogue:deleteany', $context));

        if (!$candelete) {
            throw new moodle_exception('nopermissiontodelete', 'dialogue');
        }
        // delete message and attachment files for message
        $fs->delete_area_files($context->id, false, false, $this->_messageid);
        // delete message
        $DB->delete_records('dialogue_messages', array('id' => $this->_messageid));

        return true;
    }

    protected function magic_get_author() {
        $dialogue = $this->dialogue;
        //return $dialogue->get_user_brief_details($this->_authorid);
        return dialogue_get_user_details($dialogue, $this->_authorid);
    }

    protected function magic_get_attachments() {
        $fs = get_file_storage();
        $contextid = $this->dialogue->context->id;
        if ($this->_attachments) {
            return $fs->get_area_files($contextid, 'mod_dialogue', 'attachment', $this->messageid, "timemodified", false);
        }
        return array();
    }

    protected function magic_get_messageid() {
        return $this->_messageid;
    }

    protected function magic_get_conversation() {
        if (is_null($this->_conversation)) {
            throw new coding_exception('Parent conversation is not set');
        }
        return $this->_conversation;
    }

    protected function magic_get_dialogue() {
        if (is_null($this->_dialogue)) {
            throw new coding_exception('Parent dialogue is not set');
        }
        return $this->_dialogue;
    }

    protected function magic_get_body() {
        return $this->_body;
    }

    protected function magic_get_bodydraftid() {
        return $this->_bodydraftid;
    }

    protected function magic_get_bodyformat() {
        return $this->_bodyformat;
    }

    protected function magic_get_bodyhtml() {

        $contextid = $this->dialogue->context->id;
        return file_rewrite_pluginfile_urls($this->_body, 'pluginfile.php', $contextid, 'mod_dialogue', 'message', $this->_messageid);
    }

    protected function magic_get_state() {
        return $this->_state;
    }

    protected function magic_get_timemodified() {
        return $this->_timemodified;
    }

    public function set_flag($flag, $user = null) {
        global $DB, $USER;

        if (is_null($this->_messageid)) {
            throw new coding_exception('message must be saved before a user flag can be set');
        }

        if (is_null($user)) {
            $user = $USER;
        }

        $dialogueid = $this->dialogue->dialogueid;
        $conversationid = $this->conversation->conversationid;

        $params = array('messageid' => $this->_messageid, 'userid' => $user->id, 'flag' => $flag);

        if (!$DB->record_exists('dialogue_flags', $params)) {

            $messageflag = new stdClass();
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

    public function mark_read($user = null) {
        // only mark read if in a open or closed state
        //if ($this->state == dialogue::STATE_OPEN or $this->state == dialogue::STATE_CLOSED) {
            return $this->set_flag(dialogue::FLAG_READ, $user);
        //}
        //return false;
    }

    public function mark_sent($user = null) {
        return $this->set_flag(dialogue::FLAG_SENT, $user);
    }

    //public function set_conversation(dialogue_conversation $conversation) {}
    //public function set_dialogue(dialogue $dialogue) {$this->_dialogue = $dialogue;}

    public function set_body($body, $format, $itemid = null) {
        $this->_body = $body;
        $this->_bodyformat = $format;

        if ($format == FORMAT_HTML and isset($itemid)) {
            $this->_bodydraftid = $itemid;
            $this->_body = file_rewrite_urls_to_pluginfile($this->_body, $this->_bodydraftid);
        }
    }

    public function set_attachmentsdraftid($attachmentsdraftitemid) {
        $fileareainfo = file_get_draft_area_info($attachmentsdraftitemid);
        if ($fileareainfo['filecount']) {
            $this->_attachmentsdraftid = $attachmentsdraftitemid;
        }
    }

    public function set_author($authorid) {
        if (is_object($authorid)) {
            $authorid = $authorid->id;
        }
        $this->_authorid = $authorid;
    }

    public function set_state($state) {
        $this->_state = $state; //@todo check actual state
    }

    public function save() {
        global $DB, $USER;

        $admin = get_admin(); // possible cronjob
        if ($USER->id != $admin->id and $USER->id != $this->_authorid) {
            throw new moodle_exception("This doesn't belong to you!");
        }

        $context = $this->dialogue->context; // needed for filelib functions
        $dialogueid = $this->dialogue->dialogueid;
        $conversationid = $this->conversation->conversationid;

        $record = new stdClass();
        $record->id = $this->_messageid;
        $record->dialogueid = $dialogueid;
        $record->conversationid = $conversationid;
        $record->conversationindex = $this->_conversationindex;
        $record->authorid = $this->_authorid;
        // rewrite body now if has embedded files
        if (dialogue_contains_draft_files($this->_bodydraftid)) {
            $record->body = file_rewrite_urls_to_pluginfile($this->_body, $this->_bodydraftid);
        } else {
            $record->body = $this->_body;
        }
        $record->bodyformat = $this->_bodyformat;
        // mark atttachments now if has them
        if (dialogue_contains_draft_files($this->_attachmentsdraftid)) {
            $record->attachments = 1;
        } else {
            $record->attachments = 0;
        }
        $record->state = $this->_state;
        $record->timecreated = $this->_timecreated;
        $record->timemodified = $this->_timemodified;

        if (is_null($this->_messageid)) {
            // create new record
            $this->_messageid = $DB->insert_record('dialogue_messages', $record);
        } else {
            $record->timemodified = time();
            // update existing record
            $DB->update_record('dialogue_messages', $record);
        }
        // deal with embedded files
        if ($this->_bodydraftid) {

            file_save_draft_area_files($this->_bodydraftid, $context->id, 'mod_dialogue', 'message', $this->_messageid);
        }
        // deal with attached files
        if ($this->_attachmentsdraftid) {

            file_save_draft_area_files($this->_attachmentsdraftid, $context->id, 'mod_dialogue', 'attachment', $this->_messageid);
        }

        return true;
    }

    public function send() {
        global $DB;

        // add author to participants and save
        $this->conversation->add_participant($this->_authorid);
        $this->conversation->save_participants();
        
        // update state to open
        $this->_state = dialogue::STATE_OPEN;
        $DB->set_field('dialogue_messages', 'state', $this->_state, array('id' => $this->_messageid));

        // setup information for messageapi object
        $cm = $this->dialogue->cm;
        $conversationid = $this->conversation->conversationid;
        $course = $this->dialogue->course;
        $context = $this->dialogue->context;
        $userfrom = $DB->get_record('user', array('id' => $this->_authorid), '*', MUST_EXIST);
        $subject = format_string($this->conversation->subject, true, array('context' => $context));

        $a = new stdClass();
        $a->userfrom = fullname($userfrom);
        $a->subject = $subject;
        $url = new moodle_url('/mod/dialogue/view.php', array('id' => $cm->id));
        $a->url = $url->out(false);

        $posthtml = get_string('messageapibasicmessage', 'dialogue', $a);
        $posttext = html_to_text($posthtml);
        $smallmessage = get_string('messageapismallmessage', 'dialogue', fullname($userfrom));
        
        $contexturlparams = array('id' => $cm->id, 'conversationid' => $conversationid);
        $contexturl = new moodle_url('/mod/dialogue/conversation.php', $contexturlparams);
        $contexturl->set_anchor('m' . $this->_messageid);

        // flags and messaging
        $participants = $this->conversation->participants;
        foreach ($participants as $participant) {
            if ($participant->id == $this->_authorid) {
                // so unread flag count displays properly for author, they wrote it, they should of read it.
                $this->set_flag(dialogue::FLAG_READ, $this->author);
                continue;
            }
            // give participant a sent flag
            $this->set_flag(dialogue::FLAG_SENT, $participant);

            $userto = $DB->get_record('user', array('id' => $participant->id), '*', MUST_EXIST);

            $eventdata = new stdClass();
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

            if (!$result) {
                //throw new moodle_exception('message not saved');
            }
        }

        $logurl = new moodle_url('conversation.php', array('id' =>  $cm->id, 'conversationid' => $conversationid));
        add_to_log($course->id, 'dialogue', 'reply', $logurl->out(false), $subject, $cm->id);

        return true;
    }

    /**
     * Message is marked as trash so can be deleted at a later time.
     *
     * @global stdClass $DB
     * @throws moodle_exception
     */
    public function trash() {
        global $DB;

        // can only only trash drafts
        if ($this->state != dialogue::STATE_DRAFT) {
            throw new moodle_exception('onlydraftscanbetrashed', 'dialogue');
        }

        // update state to trashed
        $this->_state = dialogue::STATE_TRASHED;
        $DB->set_field('dialogue_messages', 'state', $this->_state, array('id' => $this->_messageid));
    }
}

/**
 *
 */
class dialogue_conversation extends dialogue_message {

    protected $_conversationid = null;
    protected $_subject = '';
    protected $_participants = null;
    protected $_replies = null;
    protected $_bulkopenrule = null;
    protected $_receivedby = null;

    /**
     *
     * @global type $DB
     * @param dialogue $dialogue
     * @param type $conversationid
     */
    public function __construct(dialogue $dialogue, $conversationid = null) {
        global $DB;

        parent::__construct($dialogue, $this);

        $this->_conversationindex = 1;

        if ($conversationid) {
            if (!is_int($conversationid)) {
                throw new coding_exception('$conversationid must be an interger');
            }
            $this->_conversationid = $conversationid;
            $this->load();
        }
    }

    public function add_participant($userid) {
        $dialogue = $this->dialogue;
        //$participant = $dialogue->get_user_brief_details($userid);
        $participant = dialogue_get_user_details($dialogue, $userid);
        return $this->_participants[$userid] = $participant;
    }

    public function clear_participants() {
        return $this->_participants = null;
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
     * @return \dialogue_conversation
     */
    public function copy() {
        // create new conversation,
        $copy = new dialogue_conversation($this->dialogue);
        $copy->set_author($this->_authorid);
        $copy->set_subject($this->_subject);
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
            throw new moodle_exception('cannotclosedraftconversation', 'dialogue');
        }
        // permission check
        $canclose = (($this->_authorid == $USER->id) or has_capability('mod/dialogue:closeany', $context));
        if (!$canclose) {
            throw new moodle_exception('nopermissiontoclose', 'dialogue');
        }

        $openstate = dialogue::STATE_OPEN;
        $closedstate = dialogue::STATE_CLOSED;
        $params = array('conversationid' => $this->conversationid, 'state' => $openstate);
        
        // close all messages in conversation that have a open state, we don't worry about drafts etc
        $DB->set_field('dialogue_messages', 'state', $closedstate, $params);

        $logurl = new moodle_url('conversation.php', array('id' =>  $cm->id, 'conversationid' => $this->_conversationid));
        add_to_log($course->id, 'dialogue', 'close conversation', $logurl->out(false), $this->subject, $cm->id);
        
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
            throw new moodle_exception('nopermissiontodelete', 'dialogue');
        }
        // delete flags
        $DB->delete_records('dialogue_flags', array('conversationid' => $this->_conversationid));
        // delete bulk open rules
        $DB->delete_records('dialogue_bulk_opener_rules', array('conversationid' => $this->_conversationid));
        // delete participants
        $DB->delete_records('dialogue_participants', array('conversationid' => $this->_conversationid));
        // delete conversations
        $DB->delete_records('dialogue_conversations', array('id' => $this->_conversationid));

        parent::delete();
        
        $logurl = new moodle_url('view.php', array('id' =>  $cm->id));
        add_to_log($course->id, 'dialogue', 'delete conversation', $logurl->out(false), $this->subject, $cm->id);
    }

    /**
     * Load DB record data onto Class, conversationid needed.
     *
     * @global stdClass $DB
     * @throws coding_exception
     */
    protected function load() {
        global $DB;

        if (is_null($this->conversationid)) {
            throw new coding_exception('conversationid not set so cannot load!');
        }

        $sql = "SELECT dc.subject, dm.*
                  FROM {dialogue_conversations} dc
                  JOIN {dialogue_messages} dm ON dm.conversationid = dc.id
                 WHERE dm.conversationindex = 1
                   AND dc.id = :conversationid";

        $record = $DB->get_record_sql($sql, array('conversationid' => $this->conversationid), MUST_EXIST);

        $this->_subject = $record->subject;
        $this->_authorid = $record->authorid;
        $this->_messageid = $record->id;
        $this->_body = $record->body;
        $this->_bodyformat = $record->bodyformat;
        $this->_attachments = $record->attachments;
        $this->_state = $record->state;
        $this->_timemodified = $record->timecreated;
        $this->_timemodified = $record->timemodified;
    }

    protected function load_bulkopenrule() {
        global $DB;

        $this->_bulkopenrule = array(); // reset to empty rule

        $rule = $DB->get_record('dialogue_bulk_opener_rules', array('conversationid' => $this->_conversationid));
        if ($rule) {
            $this->_bulkopenrule = (array) $rule;
        }
    }

    protected function load_participants() {
        global $DB;

        $this->_participants = array(); // clear participants array if previous loaded
        $dialogue = $this->dialogue;

        $params = array('conversationid' => $this->_conversationid);
        $records = $DB->get_records('dialogue_participants', $params);
        foreach ($records as $record) {
            // key up on userid and fetch brief details from cache as value (cut down user record)
            //$this->_participants[$record->userid] = $dialogue->get_user_brief_details($record->userid);
            $this->_participants[$record->userid] = dialogue_get_user_details($dialogue, $record->userid);
        }
        return $this->_participants;
    }

    /**
     *
     * @global type $CFG
     * @return \mod_dialogue_conversation_form
     * @throws moodle_exception
     */
    public function initialise_form() {
        global $CFG, $USER, $PAGE;
        require_once($CFG->dirroot . '/mod/dialogue/formlib.php');

        // form can only be initialise if in draft state
        if ($this->state != dialogue::STATE_DRAFT) {
            throw new moodle_exception('Oh! Ah, yes... I see that you know your judo well...');
        }

        $cm = $this->dialogue->cm;
        $context = $this->dialogue->context;
        $dialogueid = $this->dialogue->dialogueid;

        require_capability('mod/dialogue:open', $context);

        $form = new mod_dialogue_conversation_form();
        // setup important hiddens
        $form->set_data(array('id' => $cm->id));
        $form->set_data(array('dialogueid' => $dialogueid));
        $form->set_data(array('conversationid' => $this->_conversationid));
        $form->set_data(array('messageid' => $this->_messageid));
        if (is_null($this->_messageid)) {
            $form->set_data(array('action' => 'create'));
        } else {
            $form->set_data(array('action' => 'edit'));
        }
        // setup nonjs person selector
        $options = array();
        $selected = array();
        // get participants - @todo
        $participants = $this->participants; // insure loaded by using magic
        if ($participants) {
            foreach ($participants as $participant) {
                $options[$participant->id] = fullname($participant);
                $selected[] = $participant->id;
            }
            $optiongroup = array('' => $options); // cause formslib selectgroup is stupid.
        } else {
            $optiongroup = array(get_string('usesearch', 'dialogue') => array('' => '')); // cause formslib selectgroup is stupid.
        }

        $json = json_encode($participants);

        $PAGE->requires->yui_module('moodle-mod_dialogue-autocomplete',
                                    'M.mod_dialogue.autocomplete.init', array($cm->id, $json));

        $form->update_selectgroup('p_select', $optiongroup, $selected);

        // set bulk open bulk
        $bulkopenrule = $this->bulkopenrule; // insure loaded by using magic

        if (!empty($bulkopenrule) and has_capability('mod/dialogue:bulkopenrulecreate', $context)) {
            // format for option item e.g. course-1, group-1
            $groupinformation = $bulkopenrule['type'] . '-' . $bulkopenrule['sourceid'];
            $form->set_data(array('groupinformation' => $groupinformation));
            $form->set_data(array('includefuturemembers' => $bulkopenrule['includefuturemembers']));
            $form->set_data(array('cutoffdate' => $bulkopenrule['cutoffdate']));
        }
        // set subject
        $form->set_data(array('subject' => $this->_subject));
        // prep draft body
        $draftbody = file_prepare_draft_area($this->_bodydraftid, $context->id, 'mod_dialogue', 'message', $this->_messageid, mod_dialogue_conversation_form::editor_options(), $this->_body);
        // set body
        $form->set_data(array('body' =>
            array('text' => $draftbody,
                  'format' => $this->_bodyformat,
                  'itemid' => $this->_bodydraftid)));

        // prep draft attachments
        file_prepare_draft_area($this->_attachmentsdraftid, $context->id, 'mod_dialogue', 'attachment', $this->_messageid, mod_dialogue_conversation_form::attachment_options());
        // set attachments
        $form->set_data(array('attachments[itemid]' => $this->_attachmentsdraftid));

        // remove any unecessary buttons
        if (($USER->id != $this->author->id) or is_null($this->conversationid)) {
            $form->remove_from_group('trash', 'actionbuttongroup');
        }

        // attach initialised form to conversation class and return
        return $this->_form = $form;
    }

    /**
     * Do not call this method directly
     *
     * @global stdClass $DB
     * @return stdClass | boolean bulkopenrule record or false
     */
    protected function magic_get_bulkopenrule() {
        if (is_null($this->_bulkopenrule)) {
            $this->load_bulkopenrule();
        }
        return $this->_bulkopenrule;
    }

    protected function magic_get_conversationid() {
        return $this->_conversationid;
    }

    protected function magic_get_participants() {
        if (is_null($this->_participants)) {
            $this->load_participants();
        }
        return $this->_participants;
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

    /**
     * Return a reply mapped to current dialogue.
     *
     * @return \dialogue_reply
     * @throws moodle_exception
     */
    public function reply() {
        if ($this->state != dialogue::STATE_OPEN) {
            throw new moodle_exception('a reply can only be started when a conversation is open');
        }
        return new dialogue_reply($this->_dialogue, $this);
    }

    public function replies($index = null) {
        global $DB;

        if (is_null($this->_replies)) {
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
                $reply = new dialogue_reply($this->_dialogue, $this);
                $reply->load($record);
                $this->_replies[$record->id] = $reply;
            }
        }
        if ($index) {
            if (!isset($this->_replies[$index])) {
                throw new coding_exception('index not defined');
            }
            return $this->_replies[$index];
        }
        return $this->_replies;
    }

    public function save() {
        global $DB, $USER;

        $admin = get_admin(); // possible cronjob
        if ($USER->id != $admin->id and $USER->id != $this->_authorid) {
            throw new moodle_exception("This conversation doesn't belong to you!");
        }

        $course = $this->dialogue->course;
        $dialogueid = $this->dialogue->dialogueid;

        // conversation record
        $record = new stdClass();
        $record->id = $this->_conversationid;
        $record->course = $course->id;
        $record->dialogueid = $dialogueid;
        $record->subject = $this->_subject;

        // we need a conversationid
        if (is_null($this->_conversationid)) {
            // create new record
            $this->_conversationid = $DB->insert_record('dialogue_conversations', $record);
        } else {
            $record->timemodified = time();
            // update existing record
            $DB->update_record('dialogue_conversations', $record);
        }

        $this->save_participants();

        $this->save_bulk_open_rule();

        // now let dialogue_message do it's thing
        parent::save();
    }

    protected function save_bulk_open_rule() {
        global $DB;

        $dialogueid = $this->dialogue->dialogueid;
        $conversationid = $this->_conversationid;

        if (is_null($conversationid)) {
            throw new coding_exception("conversation must exist before bulk open rule can be saved!");
        }

        $rule = $this->_bulkopenrule;
        $params = array('dialogueid' => $dialogueid, 'conversationid' => $conversationid);
        $record = $DB->get_record('dialogue_bulk_opener_rules', $params);

        if (empty($rule)) {
            if ($record) {
                // get rid of it
                $DB->delete_records('dialogue_bulk_opener_rules', $params);
            }
        } else {
            if ($record) {
                // existing
                $record->type = $rule['type'];
                $record->sourceid = $rule['sourceid'];
                $record->includefuturemembers = $rule['includefuturemembers'];
                $record->cutoffdate = $rule['cutoffdate'];
                $DB->update_record('dialogue_bulk_opener_rules', $record);
            } else {
                // new
                $record = new stdClass();
                $record->dialogueid = $dialogueid;
                $record->conversationid = $conversationid;
                $record->type = $rule['type'];
                $record->sourceid = $rule['sourceid'];
                $record->includefuturemembers = $rule['includefuturemembers'];
                $record->cutoffdate = $rule['cutoffdate'];
                $DB->insert_record('dialogue_bulk_opener_rules', $record);
            }
        }
        $this->load_bulkopenrule(); // refresh
    }

    public function save_form_data() {
        // incoming form data
        $data = $this->_form->get_submitted_data();

        // shortcut set of participants for now @todo - make better
        if (!empty($data->people)) {
            $participants = (array) $data->people; // may be single value
            foreach ($participants as $userid) {
                $this->add_participant($userid);
            }
        } else {
            $this->clear_participants();
        }

        // set bulk open rule
        if (empty($data->bulkopenrule)) {
            $this->set_bulk_open_rule(); // pass no parameters will set to nothing
        } else {
            $type = $data->bulkopenrule['type'];
            $sourceid = $data->bulkopenrule['sourceid'];
            $includefuturemembers = (empty($data->bulkopenrule['includefuturemembers'])) ? false : $data->bulkopenrule['includefuturemembers'];
            $cutoffdate = (empty($data->bulkopenrule['cutoffdate'])) ? false : $data->bulkopenrule['cutoffdate'];
            $this->set_bulk_open_rule($type, $sourceid, $includefuturemembers, $cutoffdate);
        }


        $this->set_subject($data->subject);
        $this->set_body($data->body['text'], $data->body['format'], $data->body['itemid']);
        $this->set_attachmentsdraftid($data->attachments['itemid']);

        $this->save();

        $this->_formdatasaved = true;
    }

// @todo tidy up handle removes
    protected function save_participants() {
        global $DB;

        $dialogueid = $this->dialogue->dialogueid;
        $conversationid = $this->_conversationid;

        if (is_null($conversationid)) {
            throw new coding_exception("conversation must exist before participants can be saved!");
        }

        $participants = $this->_participants;
        if ($participants) {
            foreach ($participants as $userid => $participant) {
                $params = array('conversationid' => $conversationid, 'userid' => $userid);
                if (!$DB->record_exists('dialogue_participants', $params)) {
                    $record = new stdClass();
                    $record->dialogueid = $dialogueid;
                    $record->conversationid = $conversationid;
                    $record->userid = $userid;
                    $DB->insert_record('dialogue_participants', $record);
                }
            }
        } else {
            $DB->delete_records('dialogue_participants', array('conversationid' => $conversationid));
        }
        // refresh
        $this->load_participants();
    }

    public function send() {
        global $USER, $DB;

        $cm      = $this->dialogue->cm;
        $course  = $this->dialogue->course;

        $incomplete = ((empty($this->_bulkopenrule) and empty($this->_participants)) or
                empty($this->_subject) or empty($this->_body));

        if ($incomplete) {
            throw new moodle_exception("Incomplete conversation cannot send!");
        }

        if (!empty($this->_bulkopenrule)) {
            // clearout participants as this is now a template which will be copied
            $this->_state = dialogue::STATE_BULK_AUTOMATED;
            // update state to bulk automated
            $DB->set_field('dialogue_messages', 'state', $this->_state, array('id' => $this->_messageid));

            return true;
        }


        $logurl = new moodle_url('conversation.php', array('id' =>  $cm->id, 'conversationid' => $this->_conversationid));
        add_to_log($course->id, 'dialogue', 'open conversation', $logurl->out(false), $this->subject, $cm->id);

        parent::send();
    }

    protected function set_bulk_open_rule($type = null, $sourceid = null, $includefuturemembers = false, $cutoffdate = 0) {
        $rule = array();
        // must have type (course, group) and sourceid (course->id, group->id) to
        // be a rule, else is empty.
        if (!is_null($type) and !is_null($sourceid)) {
            $rule['type'] = (string) $type;
            $rule['sourceid'] = (int) $sourceid;
            $rule['includefuturemembers'] = (int) $includefuturemembers;
            $rule['cutoffdate'] = (int) $cutoffdate;
        }
        $this->_bulkopenrule = $rule;
    }

    public function set_subject($subject) {
        $this->_subject = format_string($subject);
    }

}

class dialogue_reply extends dialogue_message {

    public function __construct(dialogue $dialogue = null, dialogue_conversation $conversation = null, $messageid = null) {

        parent::__construct($dialogue, $conversation);

        if ($messageid) {
            if (!is_int($messageid)) {
                throw new coding_exception('$messageid must be an interger');
            }
            $this->_messageid = $messageid;
            $this->load();
        }
    }

    /**
     * Load DB record data onto Class, messageid needed.
     *
     * @global stdClass $DB
     * @throws coding_exception
     */
    public function load(stdClass $record = null) {
        global $DB;

        if (is_null($record)) {
            if (is_null($this->messageid)) {
                throw new coding_exception('messageid not set so cannot load!');
            }

            $sql = "SELECT dm.*
                      FROM {dialogue_messages} dm
                     WHERE dm.id = :id
                       AND dm.conversationid = :conversationid
                       AND dm.conversationindex != 1";

            $params = array('id' => $this->messageid, 'conversationid' => $this->conversation->conversationid);
            $record = $DB->get_record_sql($sql, $params, MUST_EXIST);
        }

        // @todo - check dialogueid and conversationid

        $this->_messageid = $record->id;
        $this->_authorid = $record->authorid;
        $this->_body = $record->body;
        $this->_bodyformat = $record->bodyformat;
        $this->_attachments = $record->attachments;
        $this->_state = $record->state;
        $this->_timecreated = $record->timecreated;
        $this->_timemodified = $record->timemodified;
    }

    public function initialise_form() {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/mod/dialogue/formlib.php');

        $cm = $this->dialogue->cm;
        $context = $this->dialogue->context;
        $dialogueid = $this->dialogue->dialogueid;
        $conversationid = $this->conversation->conversationid;

        $form = new mod_dialogue_reply_form('reply.php'); // point specifically
        // setup important hiddens
        $form->set_data(array('id' => $cm->id));
        $form->set_data(array('dialogueid' => $dialogueid));
        $form->set_data(array('conversationid' => $conversationid));
        $form->set_data(array('messageid' => $this->_messageid));
        if (is_null($this->_messageid)) {
            $form->set_data(array('action' => 'create'));
        } else {
            $form->set_data(array('action' => 'edit'));
        }
        // setup body, set new $draftitemid directly on _bodydraftid and rewritten
        // html on _body
        $this->_body = file_prepare_draft_area($this->_bodydraftid, $context->id, 'mod_dialogue', 'message', $this->_messageid, mod_dialogue_reply_form::editor_options(), $this->_body);

        $form->set_data(array('body' =>
            array('text' => $this->_body,
                'format' => $this->_bodyformat,
                'itemid' => $this->_bodydraftid)));

        // setup attachments, set new $draftitemid directly on _attachmentsdraftid
        file_prepare_draft_area($this->_attachmentsdraftid, $context->id, 'mod_dialogue', 'attachment', $this->_messageid, mod_dialogue_reply_form::attachment_options());

        // using a post array for attachments
        $form->set_data(array('attachments[itemid]' => $this->_attachmentsdraftid));


        // remove any form buttons the user shouldn't have
        if ($this->conversation->state == dialogue::STATE_CLOSED) {
            $form->remove_from_group('send', 'actionbuttongroup');
        }
        
         // remove any unecessary buttons
        if (($USER->id != $this->author->id) or is_null($this->messageid)) {
            $form->remove_from_group('delete', 'actionbuttongroup');
        }

        // remove any unecessary buttons
        if (($USER->id != $this->author->id) or is_null($this->messageid)) {
            $form->remove_from_group('trash', 'actionbuttongroup');
        }

        return $this->_form = $form;
    }

    public function save_form_data() {
        // get incoming form data
        $data = $this->_form->get_submitted_data();

        $this->set_body($data->body['text'], $data->body['format'], $data->body['itemid']);
        $this->set_attachmentsdraftid($data->attachments['itemid']);

        $this->save();

        $this->_formdatasaved = true;
    }

    public function send() {
        global $USER, $DB;

        $context = $this->dialogue->context;
        $conversationid = $this->conversation->conversationid;

        // check permission
        if ($USER->id != $this->_authorid or !has_capability('mod/dialogue:reply', $context)) {
            throw new moodle_exception("This doesn't belong to you!");
        }

        $sql = "SELECT MAX(dm.conversationindex)
                  FROM {dialogue_messages} dm
                 WHERE dm.conversationid = :conversationid";

        $params = array('conversationid' => $conversationid);
        // get last conversation index
        $index = $DB->get_field_sql($sql, $params);
        // increment index
        $index++;
        // set the conversation index, important for order of display
        $DB->set_field('dialogue_messages', 'conversationindex', $index, array('id' => $this->_messageid));

        parent::send();
    }

}

/**
 *
 * @global stdClass $DB
 * @global stdClass $USER
 * @global stdClass $PAGE
 * @param dialogue $dialogue
 * @param string $query
 * @return array()
 */
function dialogue_search_potentials(dialogue $dialogue, $query = '') {
    global $DB, $USER, $PAGE;

    $results    = array();
    $pagesize   = 10;

    $params = array();
    $wheres = array();
    $wheresql  = '';


    $userfields = user_picture::fields('u');

    $cm                 = $dialogue->cm;
    $context            = $dialogue->context;
    $course             = $dialogue->course;
    $usecoursegroups    = $dialogue->activityrecord->usecoursegroups;

    list($esql, $eparams) = get_enrolled_sql($context, 'mod/dialogue:receive', null, true);
    $params = array_merge($params, $eparams);

    $basesql = "FROM {user} u
                JOIN ($esql) je ON je.id = u.id";

    if ($usecoursegroups) {
        if (!has_capability('moodle/site:accessallgroups', $context)) { 
            $groupings = groups_get_user_groups($course->id, $USER->id);
            $allgroups = $groupings[0];
            if ($allgroups) {
                list($ingmsql, $ingmparams) = $DB->get_in_or_equal($allgroups, SQL_PARAMS_NAMED, 'gm');
                $groupsql = "u.id IN (SELECT userid FROM {groups_members} gm WHERE gm.groupid $ingmsql)";
                $params = array_merge($params, $ingmparams);
                $viewallgroupsusers = get_users_by_capability($context, 'moodle/site:accessallgroups', 'u.id');
                if (!empty($viewallgroupsusers)) {
                    list($agsql, $agparams) = $DB->get_in_or_equal(array_keys($viewallgroupsusers), SQL_PARAMS_NAMED, 'ag');
                    $params = array_merge($params, $agparams);
                    $wheres[] =  "($groupsql OR u.id $agsql)";
                } else {
                    $wheres[] =  "($groupsql)";
                }

            }
        }
    }

    // current user doesn't need to be in list
    $wheres[] = "u.id != $USER->id";

    $fullname = $DB->sql_concat('u.firstname', "' '", 'u.lastname');

    if (!empty($query)) {
        $wheres[] = $DB->sql_like($fullname, ':search1', false, false);
        $params['search1'] = "%$query%";
    }

    if ($wheres) {
        $wheresql = " WHERE " . implode(" AND ", $wheres);
    }

    $countsql = "SELECT COUNT(1) " . $basesql . $wheresql;

    $matches = $DB->count_records_sql($countsql, $params);

    $orderby = " ORDER BY $fullname ASC";

    $selectsql = "SELECT $userfields " . $basesql. $wheresql . $orderby;

    $rs = $DB->get_recordset_sql($selectsql, $params, 0, $pagesize);
    foreach ($rs as $user) {
        $user->fullname = fullname($user);
        $userpic = new user_picture($user);
        $imageurl = $userpic->get_url($PAGE);
        $user->imageurl = $imageurl->out();
        if (empty($user->imagealt)) {
            $user->imagealt = get_string('pictureof', '', $user->fullname);
        }
        $results[$user->id] = $user;
    }
    $rs->close();
    return array($results, $matches, $pagesize);
}

/**
 * Uses cache to eliminate multiple database calls when rendering listing pages
 * such as view. Currently using request type cache needs work.
 *
 * @todo move to application cache, rework code with invalid event or clear
 *  on reply in class.
 *
 * @global stdClass $DB
 * @staticvar null $cache
 * @param dialogue $dialogue
 * @param int $conversationid
 * @return array
 */
function dialogue_get_conversation_participants(dialogue $dialogue, $conversationid) {
    global $DB;

    static $cache = null;

    if (!isset($cache)) {
        $cache = cache::make('mod_dialogue', 'participants');
        $participants = $DB->get_records('dialogue_participants', array('dialogueid' => $dialogue->activityrecord->id), 'conversationid');
        while ($participants) {
            $participant = array_shift($participants);
            $group = $cache->get($participant->conversationid);
            if ($group) {
                $group[] = $participant->userid;
            } else {
                $group = array($participant->userid);
            }
            $cache->set($participant->conversationid, $group);
        }

    }

    return $cache->get($conversationid);
}

/**
 *
 * @global type $DB
 * @global type $PAGE
 * @staticvar type $cache
 * @param dialogue $dialogue
 * @param type $userid
 * @return type
 */
function dialogue_get_user_details(dialogue $dialogue, $userid) {
    global $DB, $PAGE;
    static $cache;

    $context        = $dialogue->context;
    $requiredfields = user_picture::fields('u');
    
    if (!isset($cache)) {
        $cache = cache::make('mod_dialogue', 'userdetails');
    }

    if (!$cache->get($context->id)) {
        $enrolledusers = get_enrolled_users($context, null, null, $requiredfields);
        foreach($enrolledusers as &$enrolleduser) {
            dialogue_add_user_picture_fields($enrolleduser);
        }
        $cache->set($context->id, $enrolledusers);
    }
    
    $cachedusers = $cache->get($context->id);
    
    if (!isset($cachedusers[$userid])) {
        $sql = "SELECT $requiredfields
                  FROM {user} u
                 WHERE u.id = ?";
        $user = $DB->get_record_sql($sql, array($userid), MUST_EXIST);
        dialogue_add_user_picture_fields($user);
        $cachedusers[$userid] = $user;
        $cache->set($context->id, $cachedusers);
    }

    return $cachedusers[$userid];
}

/**
 * Adds the extra fields to user object required for
 * displaying user avatar.
 *
 * @global moodle_page $PAGE
 * @param stdClass $user
 */
function dialogue_add_user_picture_fields(stdClass &$user) {
        global $PAGE;

        $user->fullname = fullname($user);
        $userpic = new user_picture($user);
        $imageurl = $userpic->get_url($PAGE);
        $user->imageurl = $imageurl->out();
        if (empty($user->imagealt)) {
            $user->imagealt = get_string('pictureof', '', $user->fullname);
        }
        return;
}

/**
 * Cache a param for course module instance. Keyed on combination of course module
 * id and name. Return default if null.
 *
 * User experience - display ui controls etc
 *
 * @global stdClass $PAGE
 * @param string $name
 * @param mixed $value
 * @param mixed $default
 * @return mixed
 */
function dialogue_get_cached_param($name, $value, $default) {
    global $PAGE;

    if (!isset($PAGE->cm->id)) {
        return $default;
    }

    $cache = cache::make('mod_dialogue', 'params');
    $cacheparam = $name . '-' . $PAGE->cm->id;

    if (is_null($value)) {
        $cachevalue = $cache->get($cacheparam);
        if ($cachevalue) {
            return $cachevalue;
        }
    }

    if ($value) {
        $cache->set($cacheparam, $value);
        return $value;
    }

    return $default;
}

/**
 * Get a users total unread message count for a dialogue course module.
 *
 * @global stdClass $USER
 * @global stdClass $DB
 * @param dialogue $dialogue
 * @return int
 */
function dialogue_cm_unread_total(dialogue $dialogue) {
    global $USER, $DB;

    $sql    = '';
    $params = array();

    $dialogueid = $dialogue->activityrecord->id;
    $userid     = $USER->id;

    $params['todialogueid'] = $dialogueid;
    $params['touserid']     = $userid;

    list($insql, $inparams) = $DB->get_in_or_equal(dialogue::get_unread_states(), SQL_PARAMS_NAMED, 'un');

    $params = array_merge($params, $inparams);

    $params['undialogueid'] = $dialogueid;
    $params['unuserid']     = $userid;
    $params['unflag']       = dialogue::FLAG_READ;

    if (!has_capability('mod/dialogue:viewany', $dialogue->context)) {

        $sql = "SELECT (SELECT COUNT(1)
                          FROM mdl_dialogue_messages dm
                          JOIN mdl_dialogue_participants dp ON dp.conversationid = dm.conversationid
                         WHERE dm.dialogueid = :todialogueid
                           AND dp.userid = :touserid
                           AND dm.state $insql) -
                       (SELECT COUNT(1)
                          FROM mdl_dialogue_flags df
                         WHERE df.dialogueid = :undialogueid
                           AND df.userid = :unuserid
                           AND df.flag = :unflag) AS unread";

    } else {

        $sql = "SELECT (SELECT COUNT(1)
                          FROM mdl_dialogue_messages dm
                         WHERE dm.dialogueid = :todialogueid
                           AND dm.state $insql) -
                       (SELECT COUNT(1)
                          FROM mdl_dialogue_flags df
                         WHERE df.dialogueid = :undialogueid
                           AND df.userid = :unuserid
                           AND df.flag = :unflag) AS unread";

    }

    // get user's total unread count for a dialogue
    $record = (array) $DB->get_record_sql($sql, $params);
    if (isset($record['unread']) and $record['unread'] > 0) {
        return (int) $record['unread'];
    }
    return 0;
}

function dialogue_get_draft_listing(dialogue $dialogue, &$total = null) {
    global $PAGE, $DB, $USER;

    $url = $PAGE->url;
    $page = $url->get_param('page');
    $page = isset($pages) ? $page : 0;

    // Base fields used in query
    $fields = "dm.id, dc.subject, dm.dialogueid, dm.conversationid, dm.conversationindex,
               dm.authorid, dm.body, dm.bodyformat, dm.attachments,
               dm.state, dm.timemodified";

    $basesql = "FROM {dialogue_messages} dm
                JOIN {dialogue_conversations} dc
                  ON dc.id = dm.conversationid
               WHERE dm.dialogueid = :dialogueid
                 AND dm.state = :state
                 AND dm.authorid = :userid";

    $orderby = "ORDER BY dm.timemodified DESC";

    $params = array('dialogueid' => $dialogue->activityrecord->id,
        'state' => dialogue::STATE_DRAFT,
        'userid' => $USER->id);

    $countsql = "SELECT COUNT(1) $basesql";

    $selectsql = "SELECT $fields $basesql $orderby";

    $total = $DB->count_records_sql($countsql, $params);

    $records = array();
    if ($total) { // don't bother running select if total zero
        $limit = dialogue::PAGINATION_PAGE_SIZE;
        $offset = $page * $limit;
        $records = $DB->get_records_sql($selectsql, $params, $offset, $limit);
    }

    return $records;
}

function dialogue_get_bulk_open_rule_listing(dialogue $dialogue, &$total = null) {
     global $PAGE, $DB, $USER;

    $url = $PAGE->url;
    $page = $url->get_param('page');
    $page = isset($pages) ? $page : 0;

    // Base fields used in query
    $fields = "dm.id, dc.subject, dm.dialogueid, dm.conversationid, dm.conversationindex,
               dm.authorid, dm.body, dm.bodyformat, dm.attachments,
               dm.state, dm.timemodified,
               dbor.type, dbor.sourceid, dbor.includefuturemembers, dbor.cutoffdate, dbor.lastrun";

    $basesql = "FROM {dialogue_messages} dm
                JOIN {dialogue_conversations} dc
                  ON dc.id = dm.conversationid
                JOIN {dialogue_bulk_opener_rules} dbor
                  ON dbor.conversationid = dc.id
               WHERE dm.dialogueid = :dialogueid
                 AND dm.state = :state";
              //   AND dm.authorid = :userid";

    $orderby = "ORDER BY dm.timemodified DESC";

    $params = array('dialogueid' => $dialogue->activityrecord->id,
        'state' => dialogue::STATE_BULK_AUTOMATED,
        'userid' => $USER->id);

    $countsql = "SELECT COUNT(1) $basesql";

    $selectsql = "SELECT $fields $basesql $orderby";

    $total = $DB->count_records_sql($countsql, $params);

    $records = array();
    if ($total) { // don't bother running select if total zero
        $limit = dialogue::PAGINATION_PAGE_SIZE;
        $offset = $page * $limit;
        $records = $DB->get_records_sql($selectsql, $params, $offset, $limit);
    }

    return $records;
}


/// EXTRA FUNCTIONS /// $PAGE->requires->css()

/**
 * Loads bootstrap javascript if theme not child of bootstrapbase
 * else if load when bootstrap theme in use javascript doesn't work,
 * unsure why no time to investigate.
 *
 * @global type $PAGE
 */
function dialogue_load_bootstrap_js() {
    global $PAGE;

    $parents = $PAGE->theme->parents;
    if (!in_array('bootstrapbase', $parents)) {
        $PAGE->requires->js('/mod/dialogue/bootstrap.js');
    }
}

/**
 * Generates a summary line for a conversation using subject and body, used in
 * conversation listing view.
 *
 * @param string $subject
 * @param string $body
 * @param int $length
 * @param string $separator
 * @return string html
 */
function dialogue_generate_summary_line($subject, $body, $length = 70, $separator = ' - ') {
    $subject = html_to_text($subject, 0, false);
    $body    = html_to_text($body, 0, false);

    $diff = $length - (strlen($subject) + strlen($separator));
    if (textlib::strlen($subject) > $length or ! $diff) {
        return html_writer::tag('strong', shorten_text($subject, $length));
    }

    return html_writer::tag('strong', $subject) . $separator .
           html_writer::tag('span', shorten_text($body, $diff));
}

/**
 * Overrides permissions for a dialogue based on legacy type
 * - teacher to student
 * - student to student
 * - everybody
 * 
 * @global type $DB
 * @param type $context
 * @param type $type
 * @throws moodle_exception
 */
function dialogue_apply_legacy_permissions($context, $type) {
    global $DB;

    $studentrole = $DB->get_record('role', array('shortname' => 'student'));
    $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));

    if (!$studentrole) {
        throw new moodle_exception('missing student role');
    }

    if (!$teacherrole) {
        throw new moodle_exception('missing editingteacher role');
    }

    switch ($type) {
        case dialogue::LEGACY_TYPE_TEACHER2STUDENT: // should already be setup like this - @todo
            role_change_permission($teacherrole->id, $context, 'mod/dialogue:open', CAP_INHERIT);
            role_change_permission($teacherrole->id, $context, 'mod/dialogue:receive', CAP_INHERIT);
            role_change_permission($studentrole->id, $context, 'mod/dialogue:open', CAP_INHERIT);
            role_change_permission($studentrole->id, $context, 'mod/dialogue:receive', CAP_INHERIT);
            break;
        case dialogue::LEGACY_TYPE_STUDENT2STUDENT:
            role_change_permission($studentrole->id, $context, 'mod/dialogue:open', CAP_ALLOW);
            role_change_permission($studentrole->id, $context, 'mod/dialogue:receive', CAP_ALLOW);
            break;
        case dialogue::LEGACY_TYPE_EVERYONE:
            role_change_permission($teacherrole->id, $context, 'mod/dialogue:open', CAP_ALLOW);
            role_change_permission($teacherrole->id, $context, 'mod/dialogue:receive', CAP_ALLOW);
            role_change_permission($studentrole->id, $context, 'mod/dialogue:open', CAP_ALLOW);
            role_change_permission($studentrole->id, $context, 'mod/dialogue:receive', CAP_ALLOW);
            break;
    }
}

/**
 * Check if a dialogue course module needs to be upgraded. Checks config flag
 * first as this will be in cache and save slight overhead of database calls.
 * 
 * @global stdClass $DB
 * @param int $cmid
 * @return boolean true | false
 */
function dialogue_cm_needs_upgrade($cmid) {
    global $DB;
    // check get_config first as should be in cache
    if (get_config('dialogue', 'upgraderequired')) {
        $dbmanager = $DB->get_manager();
        if ($dbmanager->table_exists('dialogue_old')) {
            $sql = "SELECT 1
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                      JOIN {dialogue_old} d ON d.id = cm.instance
                     WHERE m.name = 'dialogue'
                       AND cm.id = :cmid";
            return $needsupgrade = $DB->record_exists_sql($sql, array('cmid' => $cmid));
        }
    }
    return false;
}

/**
 * Counts conversations in a particular dialogue. Can optionally
 * accept a state e.g count open or count closed
 *
 * @global stdClass $USER
 * @global stdClass $DB
 * @param type $cm
 * @param type $state
 * @return int count
 * @throws coding_exception
 */
function dialogue_get_conversations_count($cm, $state = null) {
    global $USER, $DB;

    $joins = array();
    $join = '';
    $wheres = array();
    $where = '';
    $params = array();

    $states = array(dialogue::STATE_OPEN, dialogue::STATE_CLOSED);

    if ($state) {
        if (!in_array($state, $states)) {
            throw new coding_exception("This state is not supported for counting conversations");
        }
        $instates = array($state);
    } else {
        $instates  = $states;
    }
    
    $context = context_module::instance($cm->id, IGNORE_MISSING);

    // standard query stuff
    $wheres[] = "dc.course = :courseid";
    $params['courseid'] = $cm->course;
    $wheres[] = "dc.dialogueid = :dialogueid";
    $params['dialogueid'] = $cm->instance;
    $wheres[] = "dm.conversationindex = 1";
    $joins[] = "JOIN {dialogue_messages} dm ON dm.conversationid = dc.id";
    // state sql
    list($insql, $inparams) = $DB->get_in_or_equal($instates, SQL_PARAMS_NAMED);
    $wheres[] = "dm.state $insql";
    $params = $params + $inparams;

    if (!has_capability('mod/dialogue:viewany', $context)) {
        $joins[] = "JOIN {dialogue_participants} dp ON dp.conversationid = dc.id ";
        $wheres[] = "dp.userid = :userid";
        $params['userid'] = $USER->id;
    }

    $sqlbase = "SELECT COUNT(dc.dialogueid) AS count
                  FROM {dialogue_conversations} dc";

    if ($joins) {
        $join = ' ' . implode("\n", $joins);
    }
   
    if ($wheres) {
        $where = " WHERE " . implode(" AND ", $wheres);
    }

    return $DB->count_records_sql($sqlbase.$join.$where, $params);
}

/**
 * Helper function returns true or false if message is part of
 * opening conversation, conversation opener always has index
 * of 1.
 *
 * @param stdClass $message
 * @return boolean
 */
function dialogue_is_a_conversation(stdClass $message) {
    if ($message->conversationindex == 1) { // opener always has index of 1
        return true;
    }
    return false;
}

/**
 * Helper function to build certain human friendly datetime strings.
 *
 * @param int $epoch
 * @return array
 */
function dialogue_get_humanfriendly_dates($epoch) {
    $customdatetime = array();

    $timediff = time() - $epoch;
    $datetime = usergetdate($epoch);

    $periods = array(
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    );

    foreach ($periods as $unit => $text) {
        if ($timediff < $unit) {
            continue;
        }
        $numberofunits = floor($timediff / $unit);
        $customdatetime['timepast'] = $numberofunits . ' ' . (($numberofunits > 1) ? get_string($text . 's') : get_string($text));
        break; // leave on first, this will be largest unit
    }

    $customdatetime['datefull'] = $datetime['mday'] . ' ' . $datetime['month'] . ' ' . $datetime['year'];
    $customdatetime['dateshort'] = $datetime['mday'] . ' ' . $datetime['month'];
    $customdatetime['time'] = date("g:i a", $epoch);
    $customdatetime['today'] = ($epoch >= strtotime("today")) ? true : false;
    $customdatetime['currentyear'] = ($epoch >= strtotime("-1 year")) ? true : false;

    return $customdatetime;
}

/**
 * Helper function to build certain datetime strings needed
 *
 * @param type $epoch
 * @return type
 */
function dialogue_getdate($epoch) {
    $customdatetime = array();

    $timediff = time() - $epoch;
    $datetime = usergetdate($epoch);

    $periods = array(
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    );

    foreach ($periods as $unit => $text) {
        if ($timediff < $unit) {
            continue;
        }
        $numberofunits = floor($timediff / $unit);
        $customdatetime['unitstring'] = $numberofunits . ' ' . (($numberofunits > 1) ? new lang_string($text . 's') : new lang_string($text));
        break; // leave on first, this will be largest unit
    }

    $customdatetime['date'] = $datetime['mday'] . ' ' . $datetime['month'] . ' ' . $datetime['year'];
    $customdatetime['dateshort'] = $datetime['mday'] . ' ' . $datetime['month'];
    $customdatetime['time'] = date("g:i a", $epoch);
    $customdatetime['today'] = ($epoch >= strtotime("today")) ? true : false;
    $customdatetime['currentyear'] = ($epoch >= strtotime("-1 year")) ? true : false;

    return $customdatetime;
}

/**
 * Helper function, is a wrapper of shorten_text and html_to_text only
 * does not provide links
 *
 * @param string $html
 * @param int $ideal
 * @param boolean $exact
 * @param string $ending
 * @return string shortentext
 */
function dialogue_shorten_html($html, $ideal = 30, $exact = false, $ending = '...') {
    return shorten_text(html_to_text($html, 0, false), $ideal, $exact, $ending);
}

/**
 * Helper function, check if draftid contains any files
 * 
 * @global type $USER
 * @param type $draftid
 * @return boolean
 */
function dialogue_contains_draft_files($draftid) {
    global $USER;

    $usercontext = context_user::instance($USER->id);
    $fs = get_file_storage();

    $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'id');

    return (count($draftfiles) > 1) ? true : false;
}


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
 * Class reply
 * @package mod_dialogue
 */
class reply extends message {
    /**
     * Rply constructor.
     * @param dialogue|null $dialogue
     * @param conversation|null $conversation
     * @param integer $messageid
     * @throws \coding_exception
     */
    public function __construct(dialogue $dialogue = null, conversation $conversation = null, $messageid = null) {

        parent::__construct($dialogue, $conversation);

        if ($messageid) {
            if (!is_int($messageid)) {
                throw new \coding_exception('$messageid must be an interger');
            }
            $this->_messageid = $messageid;
            $this->load();
        }
    }

    /**
     * Load record.
     * @param \stdClass|null $record
     * @throws \dml_exception
     */
    public function load(\stdClass $record = null) {
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

        // Todo - check dialogueid and conversationid.

        $this->_messageid = $record->id;
        $this->_authorid = $record->authorid;
        $this->_body = $record->body;
        $this->_bodyformat = $record->bodyformat;
        $this->_attachments = $record->attachments;
        $this->_attachments = $record->attachments;
        $this->_state = $record->state;
        $this->_timecreated = $record->timecreated;
        $this->_timemodified = $record->timemodified;
    }

    /**
     * Initialise form.
     *
     * @return \mod_dialogue_reply_form
     */
    public function initialise_form() {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/mod/dialogue/formlib.php');

        $cm = $this->dialogue->cm;
        $context = $this->dialogue->context;
        $dialogueid = $this->dialogue->dialogueid;
        $conversationid = $this->conversation->conversationid;

        $form = new \mod_dialogue_reply_form('reply.php'); // Point specifically.
        // Setup important hiddens.
        $form->set_data(array('id' => $cm->id));
        $form->set_data(array('dialogueid' => $dialogueid));
        $form->set_data(array('conversationid' => $conversationid));
        $form->set_data(array('messageid' => $this->_messageid));
        if (is_null($this->_messageid)) {
            $form->set_data(array('action' => 'create'));
        } else {
            $form->set_data(array('action' => 'edit'));
        }
        // Setup body, set new $draftitemid directly on _bodydraftid and rewritten
        // html on _body.
        $this->_body = file_prepare_draft_area($this->_bodydraftid, $context->id, 'mod_dialogue',
            'message', $this->_messageid, \mod_dialogue_reply_form::editor_options(), $this->_body);

        $form->set_data(array('body' =>
            array('text' => $this->_body,
                'format' => $this->_bodyformat,
                'itemid' => $this->_bodydraftid)));

        // Setup attachments, set new $draftitemid directly on _attachmentsdraftid.
        file_prepare_draft_area($this->_attachmentsdraftid, $context->id, 'mod_dialogue',
            'attachment', $this->_messageid, \mod_dialogue_reply_form::attachment_options());

        // Using a post array for attachments.
        $form->set_data(array('attachments[itemid]' => $this->_attachmentsdraftid));

        // Remove any form buttons the user shouldn't have.
        if ($this->conversation->state == dialogue::STATE_CLOSED) {
            $form->remove_from_group('send', 'actionbuttongroup');
        }

        // Remove any unecessary buttons.
        if (($USER->id != $this->author->id) || is_null($this->messageid)) {
            $form->remove_from_group('delete', 'actionbuttongroup');
        }

        // Remove any unecessary buttons.
        if (($USER->id != $this->author->id) || is_null($this->messageid)) {
            $form->remove_from_group('trash', 'actionbuttongroup');
        }

        return $this->_form = $form;
    }

    /**
     * Save form data.
     * @throws \moodle_exception
     */
    public function save_form_data() {
        // Get incoming form data.
        $data = $this->_form->get_submitted_data();

        $this->set_body($data->body['text'], $data->body['format'], $data->body['itemid']);
        if (isset($data->attachments)) {
            $this->set_attachmentsdraftid($data->attachments['itemid']);
        }
        $this->save();

        $this->_formdatasaved = true;
    }

    /**
     * Send it.
     * @return bool|void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function send() {
        global $USER, $DB;

        $context = $this->dialogue->context;
        $conversationid = $this->conversation->conversationid;

        // Check permission.
        if ($USER->id != $this->_authorid || !has_capability('mod/dialogue:reply', $context)) {
            throw new \moodle_exception("This doesn't belong to you!");
        }

        $sql = "SELECT MAX(dm.conversationindex)
                  FROM {dialogue_messages} dm
                 WHERE dm.conversationid = :conversationid";

        $params = array('conversationid' => $conversationid);
        // Get last conversation index.
        $index = $DB->get_field_sql($sql, $params);
        // Increment index.
        $index++;
        // Set the conversation index, important for order of display.
        $DB->set_field('dialogue_messages', 'conversationindex', $index, array('id' => $this->_messageid));

        parent::send();
    }
}

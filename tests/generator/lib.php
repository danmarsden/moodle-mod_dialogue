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
 * Dialogue test generator.
 *
 * @package    mod_dialogue
 * @copyright  2021 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_dialogue_generator extends testing_module_generator {
    /**
     * Create a dialogue conversation including an opening message.
     *
     * Required fields:
     *  - dialogue  (course-module idnumber, activity name, or numeric id)
     *  - userfrom  (username or id of the author)
     *  - userto    (username or id of the other participant; may be a
     *               comma-separated list to add multiple recipients)
     *
     * Optional fields:
     *  - subject   (string, defaults to 'Test subject')
     *  - body      (string, defaults to 'Test message body')
     *  - state     (string 'open' or 'closed' – controls the opening *message*
     *               state, since dialogue_conversations has no state field;
     *               defaults to 'open')
     *
     * @param array|stdClass $record Data for the conversation.
     * @return stdClass Created conversation record.
     */
    public function create_conversation($record = null): stdClass {
        global $DB;

        $record = (array) $record;

        // Resolve dialogue instance.
        // Accept the activity's course-module idnumber, the dialogue name, or a numeric id.
        if (isset($record['dialogue'])) {
            // Try course_modules.idnumber first (behat table convention).
            $cm = $DB->get_record('course_modules', ['idnumber' => $record['dialogue']]);
            if ($cm) {
                $dialoguemodule = $DB->get_record('dialogue', ['id' => $cm->instance], '*', MUST_EXIST);
            } else {
                // Try the activity name.
                $dialoguemodule = $DB->get_record('dialogue', ['name' => $record['dialogue']]);
                if (!$dialoguemodule) {
                    // Fall back to numeric id.
                    $dialoguemodule = $DB->get_record('dialogue', ['id' => (int) $record['dialogue']], '*', MUST_EXIST);
                }
            }
        } else {
            throw new coding_exception('dialogue field is required when creating a mod_dialogue conversation');
        }

        // Resolve user-from.
        $userfrom = $DB->get_record('user', ['username' => $record['userfrom']], '*');
        if (!$userfrom) {
            $userfrom = $DB->get_record('user', ['id' => (int) $record['userfrom']], '*', MUST_EXIST);
        }

        // Resolve user-to. Supports a comma-separated list so a conversation can
        // have more than one recipient (e.g. a teacher messaging two students at once).
        $usertoids = [];
        foreach (explode(',', (string) $record['userto']) as $usertoref) {
            $usertoref = trim($usertoref);
            if ($usertoref === '') {
                continue;
            }
            $userto = $DB->get_record('user', ['username' => $usertoref], '*');
            if (!$userto) {
                $userto = $DB->get_record('user', ['id' => (int) $usertoref], '*', MUST_EXIST);
            }
            $usertoids[$userto->id] = $userto->id;
        }

        $subject = $record['subject'] ?? 'Test subject';
        $body    = $record['body'] ?? 'Test message body';
        $state   = $record['state'] ?? \mod_dialogue\dialogue::STATE_OPEN;

        // Insert the conversation record.
        $conversation = new stdClass();
        $conversation->course     = $dialoguemodule->course;
        $conversation->dialogueid = $dialoguemodule->id;
        $conversation->subject    = $subject;
        $conversation->id         = $DB->insert_record('dialogue_conversations', $conversation);

        // Insert the opening message.
        $message = new stdClass();
        $message->dialogueid         = $dialoguemodule->id;
        $message->conversationid     = $conversation->id;
        $message->conversationindex  = 1;
        $message->authorid           = $userfrom->id;
        $message->body               = $body;
        $message->bodyformat         = FORMAT_HTML;
        $message->bodytrust          = 0;
        $message->attachments        = 0;
        $message->state              = $state;
        $message->timecreated        = time();
        $message->timemodified       = time();
        $message->id                 = $DB->insert_record('dialogue_messages', $message);

        // Insert participant records. Author plus each resolved recipient,
        // de-duplicated in case the author was also listed as a recipient.
        $participantids = [$userfrom->id => $userfrom->id] + $usertoids;
        foreach ($participantids as $userid) {
            $participant = new stdClass();
            $participant->dialogueid     = $dialoguemodule->id;
            $participant->conversationid = $conversation->id;
            $participant->userid         = $userid;
            $DB->insert_record('dialogue_participants', $participant);
        }

        return $conversation;
    }
}

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
 * Search area for mod_dialogue conversatoins and messages.
 *
 * @package    mod_dialogue
 * @copyright  Catalyst IT Ltd
 * @author     Pramith dayananda <<pramithd@catalyst.net.nz>>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dialogue\search;

/**
 * Search area for mod_dialogue conversatoins and messages.
 *
 * @package    mod_dialogue
 * @copyright  Catalyst IT Ltd
 * @author     Pramith dayananda <<pramithd@catalyst.net.nz>>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversations extends \core_search\base_mod {

    /**
     * Returns a recordset with all required chapter information.
     *
     * @param int $modifiedfrom
     * @param \context|null $context Optional context to restrict scope of returned results
     * @return moodle_recordset|null Recordset (or null if no results)
     */
    public function get_document_recordset($modifiedfrom = 0, \context $context = null) {
        global $DB;

        list ($contextjoin, $contextparams) = $this->get_context_restriction_sql(
                $context, 'dialogue', 'd');
        if ($contextjoin === null) {
            return null;
        }

        $sql = "SELECT m.*, c.course, c.subject as subject, d.id as dialogueid, d.course as courseid
                  FROM
                  {dialogue_messages} m
                  LEFT JOIN {dialogue_conversations} c ON c.id = m.conversationid
                  LEFT JOIN {dialogue} d ON d.id = c.dialogueid
                $contextjoin ";

        return $DB->get_recordset_sql($sql, array_merge($contextparams, [$modifiedfrom]));
    }


    /**
     * Returns the document for a particular chapter.
     *
     * @param \stdClass $record A record containing, at least, the indexed document id and a modified timestamp
     * @param array     $options Options for document creation
     * @return \core_search\document
     */
    public function get_document($record, $options = array()) {

        try {
            $cm = $this->get_cm('dialogue', $record->dialogueid, $record->course);
            $context = \context_module::instance($cm->id);
        } catch (\dml_missing_record_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document, not all required data is available: ' .
            $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        } catch (\dml_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($record->id, $this->componentname, $this->areaname);
        $doc->set('title', content_to_text($record->subject, false));
        $doc->set('content', content_to_text($record->body, false));
        $doc->set('contextid', $context->id);
        $doc->set('courseid', $record->course);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $record->timemodified);

        // Check if this document should be considered new.
        if (isset($options['lastindexedtime']) && ($options['lastindexedtime'] < $record->timemodified)) {
            // If the document was created after the last index time, it must be new.
            $doc->set_is_new(true);
        }

        return $doc;
    }

    /**
     * Whether the user can access the document or not.
     *
     * @throws \dml_missing_record_exception
     * @throws \dml_exception
     * @param int $id dialogue conversation id
     * @return bool status of access
     */
    public function check_access($messageid) {
        global $USER;

        try {
            $conversation = $this->get_dialogue_conversations($messageid);
            $cminfo = $this->get_cm('dialogue', $conversation->dialogueid, $conversation->course);
        } catch (\dml_missing_record_exception $ex) {
            return \core_search\manager::ACCESS_DELETED;
        } catch (\dml_exception $ex) {
            return \core_search\manager::ACCESS_DENIED;
        }

        if (!$cminfo->uservisible) {
            return \core_search\manager::ACCESS_DENIED;
        }

        if (!has_capability('mod/dialogue:viewany', $cminfo->context)) {
            return \core_search\manager::ACCESS_DENIED;
        }

        return \core_search\manager::ACCESS_GRANTED;
    }

    /**
     * Returns a url to the chapter.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_doc_url(\core_search\document $doc) {
        $contextmodule = \context::instance_by_id($doc->get('contextid'));
        $params = array('id' => $contextmodule->instanceid, 'dialogueid' => $doc->get('itemid'));
        return new \moodle_url('/mod/dialogue/view.php', $params);
    }

    /**
     * Returns a url to the book.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_context_url(\core_search\document $doc) {
        $context = \context::instance_by_id($doc->get('contextid'));
        $entry = $this->get_dialogue_conversations($doc->get('itemid'));
        return new \moodle_url('/mod/dialogue/conversation.php',
            ['id' => $context->instanceid, 'action' => 'view', 'conversationid' => $entry->messageid]);
    }

    /**
     * Returns the conversation for a specific message id
     *
     * @throws \dml_exception
     * @param int $entryid
     * @return stdClass data row for conversation
     */
    protected function get_dialogue_conversations($messageid) {
        global $DB;

        return $DB->get_record_sql("SELECT dm.dialogueid, dc.course, dc.id as messageid FROM {dialogue_messages} dm
                                        LEFT JOIN {dialogue_conversations} dc ON dc.id = dm.conversationid
                                    WHERE dm.id = ?", array('id' => $messageid), MUST_EXIST);
    }

    /**
     * Returns true if this area uses file indexing
     *
     * @return bool
     */
    public function uses_file_indexing() {
        return true;
    }

    /**
     * Add the forum post attachments.
     *
     * @param document $document The current document
     * @return null
     */
    public function attach_files($doc) {
        $fs = get_file_storage();
        $entryid = $doc->get('itemid');

        try {
            $entry = $this->get_dialogue_conversations($entryid);
        } catch (\dml_missing_record_exception $e) {
            debugging('Could not get record to attach files to '.$doc->get('id'), DEBUG_DEVELOPER);
            return;
        }

        $cm = $this->get_cm('dialogue', $entry->dialogueid, $doc->get('courseid'));
        $context = \context_module::instance($cm->id);

        // Get the files and attach them.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_dialogue', 'attachment', $entryid, 'filename', false);

        foreach ($files as $file) {
            $doc->add_stored_file($file);
        }
    }

}

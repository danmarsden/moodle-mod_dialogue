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
 * mod_dialogue Data provider.
 *
 * @package    mod_dialogue
 * @copyright  2021 Dan Marsden <dan@danmarsden.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dialogue\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\{writer, transform, helper, contextlist, approved_contextlist, approved_userlist, userlist};
use stdClass;

/**
 * Data provider for mod_dialogue.
 *
 * @copyright  2021 Dan Marsden <dan@danmarsden.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\metadata\provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table(
            'dialogue_participants',
            [
                'dialogueid' => 'privacy:metadata:dialogueid',
                'conversationid' => 'privacy:metadata:conversationid',
                'userid' => 'privacy:metadata:userid',
            ],
            'privacy:metadata:dialogue_participants'
        );

        $collection->add_database_table(
            'dialogue_messages',
            [
                'dialogueid' => 'privacy:metadata:dialogueid',
                'conversationid' => 'privacy:metadata:conversationid',
                'conversationindex' => 'privacy:metadata:conversationindex',
                'authorid' => 'privacy:metadata:authorid',
                'body' => 'privacy:metadata:body',
                'state' => 'privacy:metadata:state',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified'
            ],
            'privacy:metadata:dialogue_messages'
        );

        $collection->add_database_table(
            'dialogue_flags',
            [
                'dialogueid' => 'privacy:metadata:dialogueid',
                'conversationid' => 'privacy:metadata:conversationid',
                'messageid' => 'privacy:metadata:messageid',
                'timemodified' => 'privacy:metadata:timemodified',
                'flag' => 'privacy:metadata:flag',
                'userid' => 'privacy:metadata:userid',
            ],
            'privacy:metadata:dialogueflags'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        return (new contextlist)->add_from_sql(
            "SELECT ctx.id
                 FROM {course_modules} cm
                 JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                 JOIN {dialogue} a ON cm.instance = a.id
                 JOIN {dialogue_participants} dp ON dp.dialogueid = a.id AND dp.userid = :userid
                 JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel",
            [
                'modulename' => 'dialogue',
                'contextlevel' => CONTEXT_MODULE,
                'userid' => $userid
            ]
        );
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $sql = "SELECT dp.userid
                 FROM {course_modules} cm
                 JOIN {modules} m ON cm.module = m.id AND m.name = 'dialogue'
                 JOIN {dialogue} a ON cm.instance = a.id
                 JOIN {dialogue_participants} dp ON dp.dialogueid = a.id
                 JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                 WHERE ctx.id = :contextid";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'contextid'    => $context->id,
        ];

        $userlist->add_from_sql('userid', $sql, $params);
    }
    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }

        if (!$cm = get_coursemodule_from_id('dialogue', $context->instanceid)) {
            return;
        }

        $DB->delete_records('dialogue_flags', ['dialogueid' => $cm->instance]);
        $DB->delete_records('dialogue_messages', ['dialogueid' => $cm->instance]);
        $DB->delete_records('dialogue_participants', ['dialogueid' => $cm->instance]);
        $DB->delete_records('dialogue_conversations', ['dialogueid' => $cm->instance]);

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_dialogue', 'attachment');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            if (!$cm = get_coursemodule_from_id('dialogue', $context->instanceid)) {
                continue;
            }
            $DB->delete_records('dialogue_flags', ['dialogueid' => $cm->instance, 'userid' => $userid]);
            // Find all messages and delete any attachments.
            $messages = $DB->get_records('dialogue_messages', ['dialogueid' => $cm->instance, 'authorid' => $userid]);
            $fs = get_file_storage();
            foreach ($messages as $message) {
                // Delete attachments.
                $fs->delete_area_files($context->id, 'mod_dialogue', 'attachment', $message->id);
            }

            $DB->delete_records('dialogue_messages', ['dialogueid' => $cm->instance, 'authorid' => $userid]);
            $DB->delete_records('dialogue_participants', ['dialogueid' => $cm->instance, 'userid' => $userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        // Prepare SQL to gather all completed IDs.
        $userids = $userlist->get_userids();
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $DB->delete_records_select(
            'dialogue_flags',
            "userid $insql",
            $inparams
        );

        // Delete attachements.
        $messages = $DB->get_records_select(
            'dialogue_messages',
            "authorid $insql",
            $inparams);
        $fs = get_file_storage();
        foreach ($messages as $message) {
            $fs->delete_area_files($context->id, 'mod_dialogue', 'attachment', $message->id);
        }

        $DB->delete_records_select(
            'dialogue_messages',
            "authorid $insql",
            $inparams
        );

        $DB->delete_records_select(
            'dialogue_participants',
            "userid $insql",
            $inparams
        );
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        list($contextsql, $params) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $params['userid'] = $contextlist->get_user()->id;
        $params['contextlevel'] = CONTEXT_MODULE;

        $sql = "SELECT dm.*, ctx.id as contextid , d.name as dialoguename, dc.subject
                  FROM {dialogue_messages} dm
                  JOIN {dialogue_conversations} dc ON dc.id = dm.conversationid
                  JOIN {dialogue} d ON d.id = dm.dialogueid
                  JOIN {modules} m ON m.name = 'dialogue'
                  JOIN {course_modules} cm ON cm.instance = d.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel

                  WHERE dm.authorid = :userid AND ctx.id {$contextsql}";

        $messages = $DB->get_recordset_sql($sql, $params);
        foreach ($messages as $message) {
            $context = \context::instance_by_id($message->contextid);
            $subcontext = [
                get_string('pluginname', 'mod_dialogue'),
                format_string($message->dialoguename),
                $message->id
            ];
            $messagedata = (object) [
                'subject' => format_string($message->subject, true),
                'body' => format_string($message->body, true),
                'timecreated' => transform::datetime($message->timecreated),
                'timemodified' => transform::datetime($message->timemodified),
            ];
            // Store the discussion content.
            writer::with_context($context)
                ->export_data($subcontext, $messagedata);
        }
        $messages->close();
    }
}

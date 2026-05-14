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

namespace mod_dialogue\reportbuilder\local\systemreports;

use core_reportbuilder\system_report;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use html_writer;
use lang_string;
use moodle_url;

/**
 * System report for searching dialogue messages.
 *
 * Provides searchable/filterable columns for From (author), To (other participants),
 * subject, message content, attachments, state, and date.
 *
 * @package   mod_dialogue
 * @copyright 2025 Andrew Rowatt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class messages extends system_report {
    /**
     * Initialise the report: set the main table, joins, base conditions,
     * columns, filters and default sort order.
     */
    protected function initialise(): void {
        global $USER;

        $dialogueid = $this->get_parameter('dialogueid', 0, PARAM_INT);
        $cmid       = $this->get_parameter('cmid', 0, PARAM_INT);

        // dialogue_messages is the main table – one row per message.
        $this->set_main_table('dialogue_messages', 'dm');

        // Register the entity names used by columns and filters in this report.
        // These map to the SQL table aliases set up by set_main_table() and add_join().
        $this->annotate_entity('dm', new lang_string('message', 'dialogue'));
        $this->annotate_entity('dc', new lang_string('conversation', 'dialogue'));
        $this->annotate_entity('u', new lang_string('user'));

        // Always restrict to the current dialogue instance.
        // add_base_condition_simple generates a reportbuilder-safe parameter name internally.
        $this->add_base_condition_simple('dm.dialogueid', $dialogueid);

        // Restrict to user-visible message states. Drafts, bulk-automated and
        // trashed rows must never surface in this report — only published (open)
        // and closed conversation messages are searchable.
        $stateopenparam = database::generate_param_name();
        $stateclosedparam = database::generate_param_name();
        $this->add_base_condition_sql(
            "dm.state IN (:{$stateopenparam}, :{$stateclosedparam})",
            [
                $stateopenparam   => \mod_dialogue\dialogue::STATE_OPEN,
                $stateclosedparam => \mod_dialogue\dialogue::STATE_CLOSED,
            ]
        );

        // Join the conversation record (needed for subject and the conversation link).
        $this->add_join("JOIN {dialogue_conversations} dc ON dc.id = dm.conversationid");

        // Join the author of the message.
        $this->add_join("JOIN {user} u ON u.id = dm.authorid");

        // Non-privileged users only see conversations they participate in.
        if (!has_capability('mod/dialogue:viewany', $this->get_context())) {
            // database::generate_param_name() produces the rbparam-prefixed names
            // required by the reportbuilder's validate_params() check.
            $visuserparam = database::generate_param_name();
            $this->add_base_condition_sql(
                "dm.conversationid IN (
                    SELECT dp.conversationid
                      FROM {dialogue_participants} dp
                     WHERE dp.userid = :{$visuserparam})",
                [$visuserparam => $USER->id]
            );
        }

        // Course context is needed by add_columns() for per-user role lookups.
        $coursecontext = $this->get_context()->get_parent_context();

        $this->add_columns($cmid, $coursecontext);
        $this->add_filters();

        $this->set_initial_sort_column('dm:timemodified', SORT_DESC);
    }

    /**
     * Define the report columns.
     *
     * @param int $cmid Course-module ID used to build conversation links.
     * @param \context $coursecontext Course context used for role lookups.
     */
    protected function add_columns(int $cmid, \context $coursecontext): void {
        global $DB;

        // Username is part of user identity, so only viewers with
        // moodle/site:viewuseridentity see the username badge. Computed once and
        // captured into the closure to avoid a per-row capability check.
        $canviewidentity = has_capability('moodle/site:viewuseridentity', $coursecontext);

        // Shared closure: returns "Name <span class="username-indicator">username</span>"
        // for pure students, or "Name <span class="role-indicator">Role</span>..." for
        // users with any other course role.
        // Note: get_user_roles() is called once per displayed user. On a paginated report
        // (default 25 rows) this is acceptable for an admin/teacher use case.
        $formatuserfn = static function (
            string $name,
            string $username,
            int $userid,
            \context $ctx
        ) use ($canviewidentity): string {
            // get_user_roles() returns role_assignment records (ra.*) joined with role (r.*).
            // $ra->id  = role_assignment.id (used as array key)
            // $ra->roleid = role.id          (the role's own primary key)
            // $ra->shortname = role.shortname
            $userroles = get_user_roles($ctx, $userid, false);
            if (empty($userroles)) {
                // No course role at all – show username badge if allowed.
                return $canviewidentity
                    ? $name . html_writer::tag('span', $username, ['class' => 'username-indicator'])
                    : $name;
            }
            // Deduplicate by roleid – a user may have the same role assigned multiple times
            // (e.g. via different enrolment instances) but we only want one badge per role.
            $seen = [];
            $distinctroles = [];
            foreach ($userroles as $ra) {
                if (!isset($seen[$ra->roleid])) {
                    $seen[$ra->roleid] = true;
                    $distinctroles[] = $ra;
                }
            }
            $shortnames = array_column($distinctroles, 'shortname');
            if (count($shortnames) === 1 && $shortnames[0] === 'student') {
                // Pure student – show username badge if allowed, otherwise just the name.
                return $canviewidentity
                    ? $name . html_writer::tag('span', $username, ['class' => 'username-indicator'])
                    : $name;
            }
            // Non-student (or student + other roles): render a badge per distinct role.
            // role_get_name() expects a role object with 'id' = role.id (not ra.id),
            // so we supply a minimal object with the correct primary-key value.
            // role_get_name() calls format_string() internally, so output is XSS-safe.
            $html = $name;
            foreach ($distinctroles as $ra) {
                $rolerec = (object)['id' => $ra->roleid, 'shortname' => $ra->shortname, 'name' => $ra->name ?? ''];
                $html .= html_writer::tag('span', role_get_name($rolerec, $ctx), ['class' => 'role-indicator']);
            }
            return $html;
        };

        // "From" column: author's name with role/username suffix.
        // Add all the user fields fullname() needs so the column callback can produce
        // a name that respects the site's fullnamedisplay / alternativefullnameformat
        // settings (and the moodle/site:viewfullnames capability).
        $authorfromcolumn = (new column('from', new lang_string('from', 'dialogue'), 'u'))
            ->set_type(column::TYPE_TEXT)
            ->add_field('u.id', 'authorid')
            ->add_field('u.username', 'authorusername')
            ->set_is_sortable(true, [$DB->sql_fullname('u.firstname', 'u.lastname'), 'u.username']);
        foreach (\core_user\fields::for_name()->get_required_fields() as $namefield) {
            $authorfromcolumn->add_field("u.{$namefield}", "author{$namefield}");
        }
        $authorfromcolumn->add_callback(
            static function ($value, \stdClass $row) use ($coursecontext, $formatuserfn): string {
                $author = new \stdClass();
                foreach (\core_user\fields::for_name()->get_required_fields() as $namefield) {
                    $author->{$namefield} = $row->{"author{$namefield}"} ?? null;
                }
                $name = fullname($author, has_capability('moodle/site:viewfullnames', $coursecontext));
                return $formatuserfn($name, $row->authorusername, (int)$row->authorid, $coursecontext);
            }
        );
        $this->add_column($authorfromcolumn);

        // "To" column: other participants, each with role/username suffix.
        // One DB query per report row to fetch participants for that conversation;
        // acceptable for a paginated teacher/admin report (default 25 rows per page).
        // SELECT pulls every field fullname() may need so the callback respects
        // the site's fullnamedisplay / alternativefullnameformat settings.
        $namefields = \core_user\fields::for_name()->get_required_fields();
        $tonamefieldssql = implode(', ', array_map(static fn(string $f): string => "u.{$f}", $namefields));
        $this->add_column(
            (new column('to', new lang_string('to', 'dialogue'), 'dm'))
                ->set_type(column::TYPE_TEXT)
                ->add_field('dm.conversationid', 'toconvid')
                ->add_field('dm.authorid', 'toauthorid')
                ->set_is_sortable(false)
                ->add_callback(
                    static function ($value, \stdClass $row) use ($coursecontext, $formatuserfn, $tonamefieldssql): string {
                        global $DB;
                        if (empty($row->toconvid)) {
                            return '';
                        }
                        $participants = $DB->get_records_sql(
                            "SELECT u.id, u.username, {$tonamefieldssql}
                               FROM {dialogue_participants} dp
                               JOIN {user} u ON u.id = dp.userid
                              WHERE dp.conversationid = :convid
                                AND dp.userid != :authorid",
                            ['convid' => (int)$row->toconvid, 'authorid' => (int)$row->toauthorid]
                        );
                        $viewfullnames = has_capability('moodle/site:viewfullnames', $coursecontext);
                        $parts = [];
                        foreach ($participants as $user) {
                            $name = fullname($user, $viewfullnames);
                            $parts[] = $formatuserfn($name, $user->username, (int)$user->id, $coursecontext);
                        }
                        return implode(', ', $parts);
                    }
                )
        );

        // Conversation subject (linked to the conversation view).
        $this->add_column(
            (new column('subject', new lang_string('subject', 'dialogue'), 'dc'))
                ->set_type(column::TYPE_TEXT)
                ->add_field('dc.subject', 'subject')
                ->add_field('dc.id', 'convid')
                ->set_is_sortable(true, ['dc.subject'])
                ->add_callback(
                    static function (?string $subject, \stdClass $row) use ($cmid): string {
                        $url = new moodle_url('/mod/dialogue/conversation.php', [
                            'id'             => $cmid,
                            'conversationid' => $row->convid,
                        ]);
                        $label = $subject !== null && $subject !== ''
                            ? $subject
                            : get_string('nosubject', 'dialogue');
                        return html_writer::link($url, $label);
                    }
                )
        );

        // Latest message body (plain-text excerpt, 100 chars).
        $this->add_column(
            (new column('body', new lang_string('message', 'dialogue'), 'dm'))
                ->set_type(column::TYPE_TEXT)
                ->add_field('dm.body', 'body')
                ->add_field('dm.bodyformat', 'bodyformat')
                ->set_is_sortable(false)
                ->add_callback(
                    static function (?string $body, \stdClass $row): string {
                        if (empty($body)) {
                            return '';
                        }
                        return shorten_text(
                            strip_tags(format_text($body, $row->bodyformat)),
                            100
                        );
                    }
                )
        );

        // Has attachments (boolean).
        $this->add_column(
            (new column('attachments', new lang_string('attachmentscolumnheader', 'dialogue'), 'dm'))
                ->set_type(column::TYPE_BOOLEAN)
                ->add_field('dm.attachments', 'attachments')
                ->set_is_sortable(true)
                ->add_callback(static function ($value): bool {
                    return (bool) $value;
                })
        );

        // Conversation state (open / closed).
        $this->add_column(
            (new column('state', new lang_string('state', 'dialogue'), 'dm'))
                ->set_type(column::TYPE_TEXT)
                ->add_field('dm.state', 'state')
                ->set_is_sortable(true)
                ->add_callback(static function (?string $state): string {
                    if (empty($state)) {
                        return '';
                    }
                    $cssclass = 'state-indicator state-' . $state;
                    return html_writer::tag('span', get_string($state, 'dialogue'), ['class' => $cssclass]);
                })
        );

        // Date of last activity.
        $this->add_column(
            (new column('timemodified', new lang_string('date'), 'dm'))
                ->set_type(column::TYPE_TIMESTAMP)
                ->add_field('dm.timemodified', 'timemodified')
                ->set_is_sortable(true)
                ->add_callback(static function (?int $value): string {
                    if ($value === null || $value === 0) {
                        return '';
                    }
                    return userdate($value);
                })
        );
    }

    /**
     * Define the report filters.
     */
    protected function add_filters(): void {
        global $DB;

        // Filter by "From" (author full name).
        $this->add_filter(
            new filter(
                text::class,
                'from',
                new lang_string('from', 'dialogue'),
                'u',
                $DB->sql_fullname('u.firstname', 'u.lastname')
            )
        );

        // Filter by "To" (other participants' full names).
        // Uses the same correlated GROUP_CONCAT subquery as the column so the text
        // filter can apply LIKE/CONTAINS conditions against the concatenated string.
        // Username is part of user identity: only include it in the filterable text
        // for viewers with moodle/site:viewuseridentity, otherwise the filter could
        // be used as an oracle to confirm/discover usernames the viewer can't see.
        $coursecontext = $this->get_context()->get_parent_context();
        $recipientexpr = has_capability('moodle/site:viewuseridentity', $coursecontext)
            ? $DB->sql_concat('uto.firstname', "' '", 'uto.lastname', "' ('", 'uto.username', "')'")
            : $DB->sql_concat('uto.firstname', "' '", 'uto.lastname');
        $tofilterexpr = "(SELECT " . $DB->sql_group_concat($recipientexpr, ', ') .
                        " FROM {dialogue_participants} dp2" .
                        " JOIN {user} uto ON uto.id = dp2.userid" .
                        " WHERE dp2.conversationid = dm.conversationid" .
                        " AND dp2.userid != dm.authorid)";
        $this->add_filter(
            new filter(
                text::class,
                'to',
                new lang_string('to', 'dialogue'),
                'dm',
                $tofilterexpr
            )
        );

        // Filter by subject / topic.
        $this->add_filter(
            new filter(
                text::class,
                'subject',
                new lang_string('subject', 'dialogue'),
                'dc',
                'dc.subject'
            )
        );

        // Filter by message content.
        $this->add_filter(
            new filter(
                text::class,
                'body',
                new lang_string('message', 'dialogue'),
                'dm',
                'dm.body'
            )
        );

        // Filter by whether the conversation has attachments.
        $this->add_filter(
            new filter(
                boolean_select::class,
                'attachments',
                new lang_string('attachments', 'dialogue'),
                'dm',
                'CASE WHEN dm.attachments > 0 THEN 1 ELSE 0 END'
            )
        );

        // Filter by conversation state (open / closed).
        $this->add_filter(
            (new filter(
                select::class,
                'state',
                new lang_string('state', 'dialogue'),
                'dm',
                'dm.state'
            ))->set_options([
                \mod_dialogue\dialogue::STATE_OPEN   => get_string('open', 'dialogue'),
                \mod_dialogue\dialogue::STATE_CLOSED => get_string('closed', 'dialogue'),
            ])
        );

        // Filter by date of last modification.
        $this->add_filter(
            new filter(
                date::class,
                'timemodified',
                new lang_string('date'),
                'dm',
                'dm.timemodified'
            )
        );
    }

    /**
     * Users with mod/dialogue:searchmessages can access this report.
     * Row-level visibility for non-privileged users is handled in initialise() via
     * SQL conditions on dialogue_participants; can_view() provides the page-level gate.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('mod/dialogue:searchmessages', $this->get_context());
    }
}

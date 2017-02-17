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

/**
 * Class to build a list of conversations grouped by role in course
 *
 */
class conversations_by_role extends conversations {

    protected $params  = array();

    protected $basesql = null;

    protected $wheresql = array();

    protected $orderbysql = '';

    public function __construct(dialogue $dialogue, $roleid, $page = 0, $limit = dialogue::PAGINATION_MAX_RESULTS) {
        parent::__construct($dialogue, $page, $limit);
        $this->roleid   = $roleid;
    }

    public function setup() {
        global $DB, $USER;

        $this->params['roleid'] = $this->roleid;

        list($relatedctxsql, $relatedctxparams) = $DB->get_in_or_equal($this->dialogue->context->get_parent_context_ids(true),
                                                                       SQL_PARAMS_NAMED,
                                                                       'relatedctx');

        foreach ($relatedctxparams as $key => $value) {
            $this->params[$key] = $value;
        }

        list($instatesql, $instateparams) = $DB->get_in_or_equal(
            array(dialogue::STATE_OPEN, dialogue::STATE_CLOSED), SQL_PARAMS_NAMED, 'lastmessagestate');

        foreach ($instateparams as $key => $value) {
            $this->params[$key] = $value;
        }

        $this->basesql  = "FROM {user} u
                           JOIN {role_assignments} ra
                             ON ra.userid = u.id AND ra.roleid = :roleid AND ra.contextid $relatedctxsql
                           JOIN {dialogue_participants} dp ON dp.userid = u.id
                           JOIN {dialogue_conversations} dc ON dc.id = dp.conversationid
                           JOIN {dialogue_messages} dm ON dm.conversationid = dp.conversationid
                           JOIN (SELECT dm.conversationid, MAX(dm.conversationindex) AS conversationindex
                                   FROM {dialogue_messages} dm
                                  WHERE dm.dialogueid = :lastmessagedialogueid
                                    AND dm.state $instatesql
                               GROUP BY dm.conversationid) lastmessage
                             ON dm.conversationid = lastmessage.conversationid
                            AND dm.conversationindex = lastmessage.conversationindex";

        $this->params['lastmessagedialogueid'] = $this->dialogue->activityrecord->id;

        if (!has_capability('mod/dialogue:viewany', $this->dialogue->context)) {

            $this->basesql .= " JOIN (SELECT dp.conversationid
                                        FROM {dialogue_participants} dp
                                       WHERE dp.userid = :userid) isparticipant
                                          ON isparticipant.conversationid = dc.id";

            $this->params['userid'] = $USER->id;
        }

        $this->fields = array('userid' => 'u.id AS userid',
                              'subject' => 'dc.subject',
                              'dialogueid' => 'dc.dialogueid',
                              'conversationid' => 'dm.conversationid',
                              'conversationindex' => 'dm.conversationindex',
                              'authorid' => 'dm.authorid',
                              'body' => 'dm.body',
                              'bodyformat' => 'dm.bodyformat',
                              'attachments' => 'dm.attachments',
                              'state' => 'dm.state',
                              'timemodified' => 'dm.timemodified');

        $this->set_unread_field();
    }

    protected function set_unread_field() {
        global $USER, $DB;

        list($insql, $inparams) = $DB->get_in_or_equal(dialogue::get_unread_states(), SQL_PARAMS_NAMED, 'unreadstate');

        $this->fields['unread'] = "(SELECT COUNT(dm.id)
                                      FROM {dialogue_messages} dm
                                     WHERE dm.conversationid = dc.id
                                       AND dm.state IN ('open', 'closed')) -
                                   (SELECT COUNT(df.id)
                                      FROM {dialogue_flags} df
                                     WHERE df.conversationid = dc.id
                                       AND df.flag = :unreadflagread
                                       AND df.userid = :unreaduserid) AS unread";

        $this->params['unreadflagread'] = dialogue::FLAG_READ;
        $this->params['unreaduserid'] = $USER->id;
    }

    public function records() {
        global $DB;

        $records = array();

        $this->setup();

        $fields = implode(",\n", $this->fields);

        $select = "SELECT $fields $this->basesql $this->orderbysql";

        $offset = $this->page * $this->limit;

        $recordset = $DB->get_recordset_sql($select, $this->params, $offset, $this->limit);

        if ($recordset->valid()) {
            foreach ($recordset as $record) {
                $records[] = $record;
            }
        }
        $recordset->close();

        return $records;
    }

    public function rows_matched() {
        global $DB;

        $this->setup();

        return $DB->count_records_sql("SELECT COUNT(1) " . $this->basesql, $this->params);
    }

    public static function get_sort_options() {

        $options = array(
                         'latest' => array(
                             'directional' => false,
                                          ),
                         'oldest' => array(
                             'directional' => false,
                                          ),
                         'fullname' => array(
                             'directional' => true,
                             'type' => PARAM_ALPHA,
                             'default' => 'asc',
                                          ),
                         'firstname' => array(
                             'directional' => true,
                             'type' => PARAM_ALPHA,
                             'default' => 'asc',
                                          ),
                         'lastname' => array(
                             'directional' => true,
                             'type' => PARAM_ALPHA,
                             'default' => 'asc',
                                          ),
                        );

        return $options;
    }

    public function set_order($name, $direction = 'asc') {
        global $DB;

        $directionsql = ($direction == 'asc') ? 'ASC' : 'DESC';
        switch ($name) {
            case 'latest':
                $orderby = "ORDER BY dm.timemodified DESC";
                break;
            case 'oldest':
                $orderby = "ORDER BY dm.timemodified ASC";
                break;
            case 'fullname':
                $fullname = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
                $orderby = "ORDER BY $fullname $directionsql";
                break;
            case 'lastname':
                $orderby = "ORDER BY u.lastname $directionsql";
                break;
            case 'firstname':
                $orderby = "ORDER BY u.firstname $directionsql";
                break;
            default:
                throw new moodle_exception("Cannot sort on $name");
        }
        return $this->orderbysql = $orderby;
    }
}

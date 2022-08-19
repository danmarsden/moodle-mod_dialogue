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
 * Class to build a list of conversations grouped by author of last message
 * @package mod_dialogue
 */
class conversations_by_author extends conversations {
    /**
     * @var array
     */
    protected $params  = array();
    /**
     * @var array
     */
    protected $fields  = array();
    /**
     * @var null
     */
    protected $basesql = null;
    /**
     * @var array
     */
    protected $wheresql = array();
    /**
     * @var string
     */
    protected $orderbysql = '';
    /**
     * @var null
     */
    protected $recordset = null;
    /**
     * @var array
     */
    protected $states = array();

    /**
     * Setup
     * @return mixed|void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function setup() {
        global $DB, $USER;

        if (empty($this->states)) {
            throw new \moodle_exception("At least one state must be set");
        }
        list($instatesql, $instateparams) = $DB->get_in_or_equal($this->states, SQL_PARAMS_NAMED, 'lastmessagestate');
        foreach ($instateparams as $key => $value) {
            $this->params[$key] = $value;
        }
        $this->basesql  = "FROM {user} u
                           JOIN {dialogue_participants} dp ON dp.userid = u.id
                           JOIN {dialogue_conversations} dc ON dc.id = dp.conversationid
                           JOIN {dialogue_messages} dm ON dm.conversationid = dp.conversationid AND u.id = dm.authorid
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
                                       WHERE dp.userid = :userid AND dp.dialogueid=:dialogueid) isparticipant
                                          ON isparticipant.conversationid = dc.id";

            $this->params['userid'] = $USER->id;
            $this->params['dialogueid'] = $this->dialogue->activityrecord->id;
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

    /**
     * Set state
     * @param string $state
     */
    public function set_state($state) {
        $validstates = array(dialogue::STATE_OPEN, dialogue::STATE_CLOSED);
        if (!in_array($state, $validstates)) {
            throw new moodle_exception("Invalid state");
        }
        $this->states = $state;
    }

    /**
     * Set unread field
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function set_unread_field() {
        global $USER, $DB;

        list($insql, $inparams) = $DB->get_in_or_equal(dialogue::get_unread_states(), SQL_PARAMS_NAMED, 'unreadstate');

        $this->fields['unread'] = "(SELECT COUNT(dm.id)
                                      FROM {dialogue_messages} dm
                                     WHERE dm.conversationid = dc.id
                                       AND dm.state $insql) -
                                   (SELECT COUNT(df.id)
                                      FROM {dialogue_flags} df
                                     WHERE df.conversationid = dc.id
                                       AND df.flag = :unreadflagread
                                       AND df.userid = :unreaduserid) AS unread";

        foreach ($inparams as $key => $param) {
            $this->params[$key] = $param;
        }

        $this->params['unreadflagread'] = dialogue::FLAG_READ;
        $this->params['unreaduserid'] = $USER->id;
    }

    /**
     * Records
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
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

    /**
     * Rows matched
     * @return int|mixed
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function rows_matched() {
        global $DB;

        $this->setup();
        return $DB->count_records_sql("SELECT COUNT(1) " . $this->basesql, $this->params);
    }

    /**
     * Return a structure of possible options that can be used to order the built
     * query on.
     *
     * @return array $options
     */
    public static function get_sort_options() {

        $options = array('unread' => array(
                             'directional' => false,
                                          ),
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

    /**
     * Sets up the ORDER BY SQL on the passed in field option and and direction.
     *
     * @param string $name
     * @param string $direction
     * @return string $orderbysql
     * @throws \moodle_exception
     */
    public function set_order($name, $direction = 'asc') {
        global $DB;

        $directionsql = ($direction == 'asc') ? 'ASC' : 'DESC';
        switch ($name) {
            case 'unread':
                $orderby = "ORDER BY unread DESC";
                break;
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
                throw new \moodle_exception("Cannot sort on $name");
        }
        return $this->orderbysql = $orderby;
    }

}

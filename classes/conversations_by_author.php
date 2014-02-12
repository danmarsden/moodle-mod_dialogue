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
 * Class to build a list of conversations grouped by author of last message
 *
 */
class mod_dialogue_conversations_by_author extends mod_dialogue_conversations {

    protected $params  = array();

    protected $fields  = array();

    protected $basesql = null;

    protected $wheresql = array();

    protected $orderbysql = '';

    protected $recordset = null;

    protected $states = array();

    public function setup() {
        global $DB, $USER;

        if (empty($this->states)) {
            throw new moodle_exception("At least one state must be set");
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

    public function set_state($state) {
        $validstates = array(dialogue::STATE_OPEN, dialogue::STATE_CLOSED);
        if (!in_array($state, $validstates)) {
            throw new moodle_exception("Invalid state");
        }
        $this->states = $state;
    }


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

    public function records() {
        global $DB;

        $records = array();

        $this->setup();

        $fields = implode(",\n", $this->fields);

        $select = "SELECT $fields $this->basesql $this->orderbysql";

        $offset = $this->page * $this->limit;

        $recordset = $DB->get_recordset_sql($select, $this->params, $offset, $this->limit);

        if ($recordset->valid()) {
            foreach($recordset as $record) {
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

    //public function rows_returned(){}

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
     * This is used in fetch_page method.
     *
     * @global stdClass $DB
     * @param string $name
     * @param string $direction
     * @return string $orderbysql
     * @throws moodle_exception
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
                throw new moodle_exception("Cannot sort on $name");
        }
        return $this->orderbysql = $orderby;
    }

} // end of class

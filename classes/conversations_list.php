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

//@TODO cached ajax style
class conversations_list {
    public $params = array();
    public $fields = '';
    public $basesql = '';
    public $query = '';
    public $orderby = '';



    /** @var The dialogue this list of conversations belongs **/
    public $dialogue = null;

    public $page = 0;

    public $limit = dialogue::PAGINATION_PAGE_SIZE;

    public function __construct(dialogue $dialogue, $page = 0, $limit = dialogue::PAGINATION_MAX_RESULTS) {

        $this->dialogue    = $dialogue;
        $this->page        = $page;

        if ($limit > dialogue::PAGINATION_MAX_RESULTS) {
            $this->limit = dialogue::PAGINATION_MAX_RESULTS;
        } else {
            $this->limit = $limit;
        }

        $this->setup();

    }

    private function setup() {
        global $USER, $DB;

        $params = array();
        $params['dialogueid'] = $this->dialogue->activityrecord->id;

        $recipientfields    = \user_picture::fields('recipient', null, 'recipient_id', 'recipient_');
        $organizerfields    = \user_picture::fields('organizer', null, 'organizer_id', 'organizer_');
        $authorfields       = \user_picture::fields('author', null, 'lastmessage_author_id', 'lastmessage_author_');

        $seencount = "
                     (SELECT COUNT(df.id)
                         FROM {dialogue_flags} df
                        WHERE df.conversationid = dc.id
                          AND df.flag = 'read'
                          AND df.userid = :currentviewerid1) AS seencount
                     ";
        $params['currentviewerid1'] = $USER->id;

        $fields = "
                   dc.id AS id,
                   dc.subject AS subject,
                   $recipientfields,
                   $organizerfields,
                   $authorfields,
                   dm.body AS lastmessage_body,
                   dm.bodyformat AS lastmessage_bodyformat,
                   dm.attachments AS lastmessage_attachments,
                   dm.state AS lastmessage_state,
                   dm.timemodified AS lastmessage_postdate,
                   dc.messagecount AS messagecount,
                   $seencount
                  ";

        $states = array(dialogue::STATE_OPEN);
        if (get_user_preferences('dialogue_hide_closed', 1) == 0) {
            $states[] = dialogue::STATE_CLOSED;
        }
        list($instatesql, $instateparams) = $DB->get_in_or_equal($states, SQL_PARAMS_NAMED, 'lastmessagestate');
        $params = array_merge($params, $instateparams);

        $basesql = "
                    FROM mdl_dialogue_conversations dc
                    JOIN mdl_user AS organizer ON dc.owner = organizer.id
                    JOIN mdl_user AS recipient ON dc.recipient = recipient.id
                    JOIN mdl_dialogue_messages dm ON dm.conversationid = dc.id
                    JOIN (SELECT dm.conversationid, MAX(dm.conversationindex) AS conversationindex
                            FROM mdl_dialogue_messages dm
                           WHERE dm.dialogueid = :dialogueid
                             AND dm.state $instatesql
                        GROUP BY dm.conversationid) lastmessage
                    ON dm.conversationid = lastmessage.conversationid
                    AND dm.conversationindex = lastmessage.conversationindex
                    JOIN {user} AS author ON dm.authorid = author.id
                   ";

        if (!has_capability('mod/dialogue:viewany', $this->dialogue->context)) {
            $basesql .= "WHERE
                         :currentviewerid2 IN (SELECT dp.userid
                                                 FROM {dialogue_participants} dp
                                                WHERE dp.conversationid = dc.id)
                         ";
            $params['currentviewerid2'] = $USER->id;
        }

        // Build search.
        if (!empty($this->query)) {
            $searchconditions = array();
            $searchparams = array();
            foreach(array('recipient', 'organizer', 'author') as $alias) {
                list($s, $p) = self::users_search_sql($this->query, $alias);
                $searchconditions[] = $s;
                $searchparams = array_merge($searchparams, $p);
            }
            $basesql .= " AND (" . implode(" OR ", $searchconditions) . ") ";
            $params = array_merge($params, $searchparams);
        }

        // Build order by
        $sort = get_user_preferences('dialogue_sort_by', 'latest');
        switch ($sort) {
            case 'recipient':
                $fullname = $DB->sql_concat('recipient.firstname', "' '", 'recipient.lastname');
                $orderby = "ORDER BY $fullname ASC";
                break;
            case 'organizer':
                $fullname = $DB->sql_concat('organizer.firstname', "' '", 'organizer.lastname');
                $orderby = "ORDER BY $fullname ";
                break;
            case 'lastmessageauthor':
                $fullname = $DB->sql_concat('author.firstname', "' '", 'author.lastname');
                $orderby = "ORDER BY $fullname ";
                break;
            case 'oldest':
                $orderby = "ORDER BY dm.timemodified ASC";
                break;
            case 'latest':
            default:
                $orderby = "ORDER BY dm.timemodified DESC";
                break;

        }

        $this->fields       = $fields;
        $this->basesql      = $basesql;
        $this->orderby      = $orderby;
        $this->params       = $params;

    }

    public function set_user_search($query) {
        $this->query = $query;
    }
    public static function users_search_sql($search, $u = 'u', $searchanywhere = true) {
        global $DB, $CFG;
        $params = array();
        $tests = array();

        if ($u) {
            $p = $u . '.';
        }

        // If we have a $search string, put a field LIKE '$search%' condition on each field.
        if ($search) {
            $conditions = array(
                $DB->sql_fullname($p . 'firstname', $p . 'lastname'),
                $conditions[] = $p . 'lastname'
            );
            if ($searchanywhere) {
                $searchparam = '%' . $search . '%';
            } else {
                $searchparam = $search . '%';
            }
            $i = 0;
            foreach ($conditions as $key => $condition) {
                $conditions[$key] = $DB->sql_like($condition, ":{$u}con{$i}00", false, false);
                $params["{$u}con{$i}00"] = $searchparam;
                $i++;
            }
            $tests[] = '(' . implode(' OR ', $conditions) . ')';
        }

        // Add some additional sensible conditions.
        $tests[] = $p . "id <> :{$u}guestid";
        $params["{$u}guestid"] = $CFG->siteguest;
        $tests[] = $p . 'deleted = 0';
        $tests[] = $p . 'confirmed = 1';


        // In case there are no tests, add one result (this makes it easier to combine
        // this with an existing query as you can always add AND $sql).
        if (empty($tests)) {
            $tests[] = '1 = 1';
        }

        // Combing the conditions and return.
        return array(implode(' AND ', $tests), $params);
    }

    public static function get_sort_options() {
        return array('latest', 'recipient', 'organizer', 'lastmessageauthor', 'oldest');
    }

    public function records() {
        global $DB;

        $records = array();

        $this->setup();

        $select = "SELECT $this->fields $this->basesql $this->orderby";

       // print_object($select);

        $offset = $this->page * $this->limit;

        $recordset = $DB->get_recordset_sql($select, $this->params, $offset, $this->limit);

        if ($recordset->valid()) {
            foreach($recordset as $record) {
                $records[] = self::unalias($record);;
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

    /**
     * Takes a record and builds hierarchical structure based on
     * occurrences of delimiter in key.
     *
     * message_author_email
     * becomes:
     * array['message']['author']['email'] = 'nobody@nowhere'
     *
     * @param \stdClass $record
     * @param string $delimiter
     * @return array
     */
    public static function unalias(\stdClass $record, $delimiter = "_") {
        $collect = array();
        foreach (get_object_vars($record) as $key => $value) {
            $tip = &$collect;
            $branches = explode($delimiter, $key);
            while ($branches) {
                $field = array_shift($branches);
                if (count($branches) === 0) {
                    break;
                }
                if (isset($tip[$field])) {
                    $tip = &$tip[$field];
                } else {
                    $tip[$field] = null;
                    $tip = &$tip[$field];
                }
            }
            $tip[$field] = $value;
        }
        return $collect;
    }

    /**
     * PHP overloading magic to make the $dialogue->course syntax work by redirecting
     * it to the corresponding $dialogue->magic_get_course() method if there is one, and
     * throwing an exception if not. Taken from pagelib.php
     *
     * @param string $name property name
     * @return mixed
     * @throws \coding_exception
     */
    public function __get($name) {
        $getmethod = 'magic_get_' . $name;
        if (method_exists($this, $getmethod)) {
            return $this->$getmethod();
        } else {
            throw new \coding_exception('Unknown property: ' . $name);
        }
    }

}


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


class open_rules_list {
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
        global $DB, $USER;

        $params = array();
        $params['dialogueid'] = $this->dialogue->activityrecord->id;

        $fields = "
                   dm.id,
                   dc.subject,
                   dm.dialogueid,
                   dm.conversationid,
                   dm.conversationindex,
                   dm.authorid,
                   dm.body,
                   dm.bodyformat,
                   dm.attachments,
                   dm.state,
                   dm.timemodified,
                   r.type,
                   r.sourceid,
                   r.includefuturemembers,
                   r.cutoffdate,
                   r.lastrun
                  ";

        $states = array(dialogue::STATE_OPEN, dialogue::STATE_CLOSED);
        list($instatesql, $instateparams) = $DB->get_in_or_equal($states, SQL_PARAMS_NAMED, 's');
        $params = array_merge($params, $instateparams);

        $basesql = "
                    FROM {dialogue_messages} dm
                    JOIN {dialogue_conversations} dc
                      ON dc.id = dm.conversationid
                    JOIN {dialogue_bulk_opener_rules} r
                      ON r.conversationid = dc.id
                   WHERE dc.openrule = 1
                     AND dm.dialogueid = :dialogueid
                     AND dm.state $instatesql
                   ";

        if (! has_capability('mod/dialogue:bulkopenruleeditany', $this->dialogue->context)) {
            $basesql .= " AND dm.authorid = :userid";
            $params['userid'] = $USER->id;
        }

        $orderby = "ORDER BY dm.timemodified DESC";

        $this->fields       = $fields;
        $this->basesql      = $basesql;
        $this->orderby      = $orderby;
        $this->params       = $params;
    }

    public function records() {
        global $DB;

        $records = array();

        $this->setup();

        $select = "SELECT $this->fields $this->basesql $this->orderby";

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


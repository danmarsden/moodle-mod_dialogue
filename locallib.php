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
 * Library of extra functions for the dialogue module not part of the standard add-on module API set
 * but used by scripts in the mod/dialogue folder
 *
 * @package   mod_dialogue
 * @copyright 2013 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 *
 */


/**
 *
 * @global stdClass $DB
 * @global stdClass $USER
 * @global stdClass $PAGE
 * @param \mod_dialogue\dialogue $dialogue
 * @param string $query
 * @return array()
 */
function dialogue_search_potentials(\mod_dialogue\dialogue $dialogue, $query = '') {
    global $DB, $USER, $PAGE;

    $results    = array();
    $pagesize   = 10;

    $params = array();
    $wheres = array();
    $wheresql  = '';


    $userfields = user_picture::fields('u');

    $cm                 = $dialogue->cm;
    $context            = $dialogue->context;
    $course             = $dialogue->course;
    $usecoursegroups    = $dialogue->activityrecord->usecoursegroups;

    list($esql, $eparams) = get_enrolled_sql($context, 'mod/dialogue:receive', null, true);
    $params = array_merge($params, $eparams);

    $basesql = "FROM {user} u
                JOIN ($esql) je ON je.id = u.id";

    if ($usecoursegroups) {
        if (!has_capability('moodle/site:accessallgroups', $context)) { 
            $groupings = groups_get_user_groups($course->id, $USER->id);
            $allgroups = $groupings[0];
            if ($allgroups) {
                list($ingmsql, $ingmparams) = $DB->get_in_or_equal($allgroups, SQL_PARAMS_NAMED, 'gm');
                $groupsql = "u.id IN (SELECT userid FROM {groups_members} gm WHERE gm.groupid $ingmsql)";
                $params = array_merge($params, $ingmparams);
                $viewallgroupsusers = get_users_by_capability($context, 'moodle/site:accessallgroups', 'u.id');
                if (!empty($viewallgroupsusers)) {
                    list($agsql, $agparams) = $DB->get_in_or_equal(array_keys($viewallgroupsusers), SQL_PARAMS_NAMED, 'ag');
                    $params = array_merge($params, $agparams);
                    $wheres[] =  "($groupsql OR u.id $agsql)";
                } else {
                    $wheres[] =  "($groupsql)";
                }

            }
        }
    }

    // current user doesn't need to be in list
    $wheres[] = "u.id != $USER->id";

    $fullname = $DB->sql_concat('u.firstname', "' '", 'u.lastname');

    if (!empty($query)) {
        $wheres[] = $DB->sql_like($fullname, ':search1', false, false);
        $params['search1'] = "%$query%";
    }

    if ($wheres) {
        $wheresql = " WHERE " . implode(" AND ", $wheres);
    }

    $countsql = "SELECT COUNT(1) " . $basesql . $wheresql;

    $matches = $DB->count_records_sql($countsql, $params);

    $orderby = " ORDER BY $fullname ASC";

    $selectsql = "SELECT $userfields " . $basesql. $wheresql . $orderby;

    $rs = $DB->get_recordset_sql($selectsql, $params, 0, $pagesize);
    foreach ($rs as $user) {
        $user->fullname = fullname($user);
        $userpic = new user_picture($user);
        $imageurl = $userpic->get_url($PAGE);
        $user->imageurl = $imageurl->out();
        if (empty($user->imagealt)) {
            $user->imagealt = get_string('pictureof', '', $user->fullname);
        }
        $results[$user->id] = $user;
    }
    $rs->close();
    return array($results, $matches, $pagesize);
}

/**
 * Uses cache to eliminate multiple database calls when rendering listing pages
 * such as view. Currently using request type cache needs work.
 *
 * @todo move to application cache, rework code with invalid event or clear
 *  on reply in class.
 *
 * @global stdClass $DB
 * @staticvar null $cache
 * @param \mod_dialogue\dialogue $dialogue
 * @param int $conversationid
 * @return array
 */
function dialogue_get_conversation_participants(\mod_dialogue\dialogue $dialogue, $conversationid) {
    global $DB;

    static $cache = null;

    if (!isset($cache)) {
        $cache = cache::make('mod_dialogue', 'participants');
        $participants = $DB->get_records('dialogue_participants', array('dialogueid' => $dialogue->activityrecord->id), 'conversationid');
        while ($participants) {
            $participant = array_shift($participants);
            $group = $cache->get($participant->conversationid);
            if ($group) {
                $group[] = $participant->userid;
            } else {
                $group = array($participant->userid);
            }
            $cache->set($participant->conversationid, $group);
        }

    }

    return $cache->get($conversationid);
}

/**
 *
 * @global type $DB
 * @global type $PAGE
 * @staticvar type $cache
 * @param \mod_dialogue\dialogue $dialogue
 * @param type $userid
 * @return type
 */
function dialogue_get_user_details(\mod_dialogue\dialogue $dialogue, $userid) {
    global $DB, $PAGE;
    static $cache;

    $context        = $dialogue->context;
    $requiredfields = user_picture::fields('u');
    
    if (!isset($cache)) {
        $cache = cache::make('mod_dialogue', 'userdetails');
    }

    if (!$cache->get($context->id)) {
        $enrolledusers = get_enrolled_users($context, null, null, $requiredfields);
        foreach($enrolledusers as &$enrolleduser) {
            dialogue_add_user_picture_fields($enrolleduser);
        }
        $cache->set($context->id, $enrolledusers);
    }
    
    $cachedusers = $cache->get($context->id);
    
    if (!isset($cachedusers[$userid])) {
        $sql = "SELECT $requiredfields
                  FROM {user} u
                 WHERE u.id = ?";
        $user = $DB->get_record_sql($sql, array($userid), MUST_EXIST);
        dialogue_add_user_picture_fields($user);
        $cachedusers[$userid] = $user;
        $cache->set($context->id, $cachedusers);
    }

    return $cachedusers[$userid];
}

/**
 * Adds the extra fields to user object required for
 * displaying user avatar.
 *
 * @global moodle_page $PAGE
 * @param stdClass $user
 */
function dialogue_add_user_picture_fields(stdClass &$user) {
        global $PAGE;

        $user->fullname = fullname($user);
        $userpic = new user_picture($user);
        $imageurl = $userpic->get_url($PAGE);
        $user->imageurl = $imageurl->out();
        if (empty($user->imagealt)) {
            $user->imagealt = get_string('pictureof', '', $user->fullname);
        }
        return;
}

/**
 * Cache a param for course module instance. Keyed on combination of course module
 * id and name. Return default if null.
 *
 * User experience - display ui controls etc
 *
 * @global stdClass $PAGE
 * @param string $name
 * @param mixed $value
 * @param mixed $default
 * @return mixed
 */
function dialogue_get_cached_param($name, $value, $default) {
    global $PAGE;

    if (!isset($PAGE->cm->id)) {
        return $default;
    }

    $cache = cache::make('mod_dialogue', 'params');
    $cacheparam = $name . '-' . $PAGE->cm->id;

    if (is_null($value)) {
        $cachevalue = $cache->get($cacheparam);
        if ($cachevalue) {
            return $cachevalue;
        }
    }

    if ($value) {
        $cache->set($cacheparam, $value);
        return $value;
    }

    return $default;
}

/**
 * Get a users total unread message count for a dialogue course module.
 *
 * @global stdClass $USER
 * @global stdClass $DB
 * @param \mod_dialogue\dialogue $dialogue
 * @return int
 */
function dialogue_cm_unread_total(\mod_dialogue\dialogue $dialogue) {
    global $USER, $DB;

    $sql    = '';
    $params = array();

    $dialogueid = $dialogue->activityrecord->id;
    $userid     = $USER->id;

    $params['todialogueid'] = $dialogueid;
    $params['touserid']     = $userid;

    list($insql, $inparams) = $DB->get_in_or_equal(\mod_dialogue\dialogue::get_unread_states(), SQL_PARAMS_NAMED, 'un');

    $params = array_merge($params, $inparams);

    $params['undialogueid'] = $dialogueid;
    $params['unuserid']     = $userid;
    $params['unflag']       = \mod_dialogue\dialogue::FLAG_READ;

    // Most restrictive: view own
    $sql = "SELECT
                 (SELECT COUNT(1)
                    FROM {dialogue_messages} dm
                    JOIN {dialogue_participants} dp ON dp.conversationid = dm.conversationid
                   WHERE dm.dialogueid = :todialogueid
                     AND dp.userid = :touserid
                     AND dm.state $insql) -
                 (SELECT COUNT(1)
                    FROM {dialogue_flags} df
                    JOIN {dialogue_participants} dp ON dp.conversationid = df.conversationid
                     AND dp.userid = df.userid
                   WHERE df.dialogueid = :undialogueid
                     AND df.userid = :unuserid
                     AND df.flag = :unflag) AS unread";

    // Least restrictive: view any
    if (has_capability('mod/dialogue:viewany', $dialogue->context)) {
        $sql = "SELECT
                     (SELECT COUNT(1)
                        FROM {dialogue_messages} dm
                       WHERE dm.dialogueid = :todialogueid
                         AND dm.state $insql) -
                     (SELECT COUNT(1)
                        FROM {dialogue_flags} df
                       WHERE df.dialogueid = :undialogueid
                         AND df.userid = :unuserid
                         AND df.flag = :unflag) AS unread";
    }

    // get user's total unread count for a dialogue
    $record = (array) $DB->get_record_sql($sql, $params);
    if (isset($record['unread']) and $record['unread'] > 0) {
        return (int) $record['unread'];
    }
    return 0;
}

function dialogue_get_draft_listing(\mod_dialogue\dialogue $dialogue, &$total = null) {
    global $PAGE, $DB, $USER;

    $url = $PAGE->url;
    $page = $url->get_param('page');
    $page = isset($pages) ? $page : 0;

    // Base fields used in query
    $fields = "dm.id, dc.subject, dm.dialogueid, dm.conversationid, dm.conversationindex,
               dm.authorid, dm.body, dm.bodyformat, dm.attachments,
               dm.state, dm.timemodified";

    $basesql = "FROM {dialogue_messages} dm
                JOIN {dialogue_conversations} dc
                  ON dc.id = dm.conversationid
               WHERE dm.dialogueid = :dialogueid
                 AND dm.state = :state
                 AND dm.authorid = :userid";

    $orderby = "ORDER BY dm.timemodified DESC";

    $params = array('dialogueid' => $dialogue->activityrecord->id,
        'state' => \mod_dialogue\dialogue::STATE_DRAFT,
        'userid' => $USER->id);

    $countsql = "SELECT COUNT(1) $basesql";

    $selectsql = "SELECT $fields $basesql $orderby";

    $total = $DB->count_records_sql($countsql, $params);

    $records = array();
    if ($total) { // don't bother running select if total zero
        $limit = \mod_dialogue\dialogue::PAGINATION_PAGE_SIZE;
        $offset = $page * $limit;
        $records = $DB->get_records_sql($selectsql, $params, $offset, $limit);
    }

    return $records;
}

function dialogue_get_bulk_open_rule_listing(\mod_dialogue\dialogue $dialogue, &$total = null) {
     global $PAGE, $DB, $USER;

    $url = $PAGE->url;
    $page = $url->get_param('page');
    $page = isset($pages) ? $page : 0;

    // Base fields used in query
    $fields = "dm.id, dc.subject, dm.dialogueid, dm.conversationid, dm.conversationindex,
               dm.authorid, dm.body, dm.bodyformat, dm.attachments,
               dm.state, dm.timemodified,
               dbor.type, dbor.sourceid, dbor.includefuturemembers, dbor.cutoffdate, dbor.lastrun";

    $basesql = "FROM {dialogue_messages} dm
                JOIN {dialogue_conversations} dc
                  ON dc.id = dm.conversationid
                JOIN {dialogue_bulk_opener_rules} dbor
                  ON dbor.conversationid = dc.id
               WHERE dm.dialogueid = :dialogueid
                 AND dm.state = :state";
              //   AND dm.authorid = :userid";

    $orderby = "ORDER BY dm.timemodified DESC";

    $params = array('dialogueid' => $dialogue->activityrecord->id,
        'state' => \mod_dialogue\dialogue::STATE_BULK_AUTOMATED,
        'userid' => $USER->id);

    $countsql = "SELECT COUNT(1) $basesql";

    $selectsql = "SELECT $fields $basesql $orderby";

    $total = $DB->count_records_sql($countsql, $params);

    $records = array();
    if ($total) { // don't bother running select if total zero
        $limit = \mod_dialogue\dialogue::PAGINATION_PAGE_SIZE;
        $offset = $page * $limit;
        $records = $DB->get_records_sql($selectsql, $params, $offset, $limit);
    }

    return $records;
}


/// EXTRA FUNCTIONS ///

/**
 * Generates a summary line for a conversation using subject and body, used in
 * conversation listing view.
 *
 * @param string $subject
 * @param string $body
 * @param int $length
 * @param string $separator
 * @return string html
 */
function dialogue_generate_summary_line($subject, $body, $bodyformat, $length = 70, $separator = ' - ') {
    $subject = html_to_text($subject, 0, false);
    $body    = htmlspecialchars_decode($body);
    $body    = html_to_text($body, 0, false);

    $diff = $length - (strlen($subject) + strlen($separator));
    if (\core_text::strlen($subject) > $length or ! $diff) {
        return html_writer::tag('strong', shorten_text($subject, $length));
    }

    return html_writer::tag('strong', $subject) . $separator .
           html_writer::tag('span', shorten_text($body, $diff));
}


/**
 * Counts conversations in a particular dialogue. Can optionally
 * accept a state e.g count open or count closed
 *
 * @global stdClass $USER
 * @global stdClass $DB
 * @param type $cm
 * @param type $state
 * @return int count
 * @throws coding_exception
 */
function dialogue_get_conversations_count($cm, $state = null) {
    global $USER, $DB;

    $joins = array();
    $join = '';
    $wheres = array();
    $where = '';
    $params = array();

    $states = array(\mod_dialogue\dialogue::STATE_OPEN, \mod_dialogue\dialogue::STATE_CLOSED);

    if ($state) {
        if (!in_array($state, $states)) {
            throw new coding_exception("This state is not supported for counting conversations");
        }
        $instates = array($state);
    } else {
        $instates  = $states;
    }
    
    $context = \context_module::instance($cm->id, IGNORE_MISSING);

    // standard query stuff
    $wheres[] = "dc.course = :courseid";
    $params['courseid'] = $cm->course;
    $wheres[] = "dc.dialogueid = :dialogueid";
    $params['dialogueid'] = $cm->instance;
    $wheres[] = "dm.conversationindex = 1";
    $joins[] = "JOIN {dialogue_messages} dm ON dm.conversationid = dc.id";
    // state sql
    list($insql, $inparams) = $DB->get_in_or_equal($instates, SQL_PARAMS_NAMED);
    $wheres[] = "dm.state $insql";
    $params = $params + $inparams;

    if (!has_capability('mod/dialogue:viewany', $context)) {
        $joins[] = "JOIN {dialogue_participants} dp ON dp.conversationid = dc.id ";
        $wheres[] = "dp.userid = :userid";
        $params['userid'] = $USER->id;
    }

    $sqlbase = "SELECT COUNT(dc.dialogueid) AS count
                  FROM {dialogue_conversations} dc";

    if ($joins) {
        $join = ' ' . implode("\n", $joins);
    }
   
    if ($wheres) {
        $where = " WHERE " . implode(" AND ", $wheres);
    }

    return $DB->count_records_sql($sqlbase.$join.$where, $params);
}

/**
 * Helper function returns true or false if message is part of
 * opening conversation, conversation opener always has index
 * of 1.
 *
 * @param stdClass $message
 * @return boolean
 */
function dialogue_is_a_conversation(stdClass $message) {
    if ($message->conversationindex == 1) { // opener always has index of 1
        return true;
    }
    return false;
}

/**
 * Helper function to build certain human friendly datetime strings.
 *
 * @param int $epoch
 * @return array
 */
function dialogue_get_humanfriendly_dates($epoch) {
    $customdatetime = array();

    $timediff = time() - $epoch;
    $datetime = usergetdate($epoch);

    $periods = array(
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    );
    $customdatetime['timepast'] = get_string('now');
    foreach ($periods as $unit => $text) {
        if ($timediff < $unit) {
            continue;
        }
        $numberofunits = floor($timediff / $unit);
        $customdatetime['timepast'] = $numberofunits . ' ' . (($numberofunits > 1) ? new lang_string($text . 's', 'dialogue') : new lang_string($text, 'dialogue'));
        break; // leave on first, this will be largest unit
    }

    $customdatetime['datefull'] = $datetime['mday'] . ' ' . $datetime['month'] . ' ' . $datetime['year'];
    $customdatetime['dateshort'] = $datetime['mday'] . ' ' . $datetime['month'];
    $customdatetime['time'] = date("g:i a", $epoch);
    $customdatetime['today'] = ($epoch >= strtotime("today")) ? true : false;
    $customdatetime['currentyear'] = ($epoch >= strtotime("-1 year")) ? true : false;

    return $customdatetime;
}

/**
 * Helper function, is a wrapper of shorten_text and html_to_text only
 * does not provide links
 *
 * @param string $html
 * @param int $ideal
 * @param boolean $exact
 * @param string $ending
 * @return string shortentext
 */
function dialogue_shorten_html($html, $ideal = 30, $exact = false, $ending = '...') {
    return shorten_text(html_to_text($html, 0, false), $ideal, $exact, $ending);
}

/**
 * Helper function, check if draftid contains any files
 * 
 * @global type $USER
 * @param type $draftid
 * @return boolean
 */
function dialogue_contains_draft_files($draftid) {
    global $USER;

    $usercontext = \context_user::instance($USER->id);
    $fs = get_file_storage();

    $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'id');

    return (count($draftfiles) > 1) ? true : false;
}


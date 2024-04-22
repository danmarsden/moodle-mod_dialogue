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

namespace mod_dialogue\local;

/**
 * This class provides a custom search function with groups.
 *
 * @package mod_dialogue
 * @copyright 2022 Dan Marsden
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_enrolment_manager extends \course_enrolment_manager {
    /**
     * Searches through the enrolled users in this course based on search_users but with groups..
     *
     * @param string $search The search term.
     * @param bool $searchanywhere Can the search term be anywhere, or must it be at the start.
     * @param int $page Starting at 0.
     * @param int $perpage Number of users returned per page.
     * @param array $groups - list of groups user is in.
     * @return array with two or three elements:
     *      int totalusers Number users matching the search. (This element only exist if $returnexactcount was set to true)
     *      array users List of user objects returned by the query.
     *      boolean moreusers True if there are still more users, otherwise is False.
     */
    public function search_users_with_groups(string $search = '', bool $searchanywhere = false, int $page = 0, int $perpage = 25,
            array $groups = []) {
        global $DB;

        [$ufields, $joins, $params, $wherecondition] = $this->get_basic_search_conditions($search, $searchanywhere);

        $fields      = 'SELECT ' . $ufields;
        $countfields = 'SELECT COUNT(u.id)';
        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($groups), SQL_PARAMS_NAMED);

        $sql = " FROM {user} u
                      $joins
                 JOIN {user_enrolments} ue ON ue.userid = u.id
                 JOIN {enrol} e ON ue.enrolid = e.id
                 JOIN ({groups_members} gm JOIN {groups} g ON (g.id = gm.groupid))
                      ON (u.id = gm.userid AND g.courseid = e.courseid)
                WHERE $wherecondition
                  AND e.courseid = :courseid
                  AND g.id $insql";
        $params['courseid'] = $this->course->id;
        $params = array_merge($params, $inparams);
        return $this->execute_search_queries($search, $fields, $countfields, $sql, $params, $page, $perpage, 0, false);
    }
}

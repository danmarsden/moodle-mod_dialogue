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
 * This is the external API for this tool.
 *
 * @package    mod_dialogue
 * @copyright  2021 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_dialogue;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

use core\session\exception;
use external_api;
use external_function_parameters;
use external_value;
use external_multiple_structure;
use core_user\external\user_summary_exporter;

/**
 * This is the external API for this tool.
 *
 * @copyright  2021 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    /**
     * Returns the description of external function parameters.
     *
     * @return external_function_parameters.
     */
    public static function search_users_parameters() {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'course id'),
                'search' => new external_value(PARAM_RAW, 'query'),
                'searchanywhere' => new external_value(PARAM_BOOL, 'find a match anywhere, or only at the beginning'),
                'page' => new external_value(PARAM_INT, 'Page number'),
                'perpage' => new external_value(PARAM_INT, 'Number per page'),
            ]
        );

    }

    /**
     * Search users.
     *
     * @param int $courseid Course id
     * @param string $search The query
     * @param bool $searchanywhere Match anywhere in the string
     * @param int $page Page number
     * @param int $perpage Max per page
     * @return array report data
     */
    public static function search_users(int $courseid, string $search, bool $searchanywhere, int $page, int $perpage) {
        global $PAGE, $CFG, $USER;

        require_once($CFG->dirroot.'/enrol/locallib.php');
        require_once($CFG->dirroot.'/user/lib.php');

        $params = self::validate_parameters(
            self::search_users_parameters(),
            [
                'courseid'       => $courseid,
                'search'         => $search,
                'searchanywhere' => $searchanywhere,
                'page'           => $page,
                'perpage'        => $perpage
            ]
        );
        $context = \context_course::instance($params['courseid']);
        try {
            self::validate_context($context);
        } catch (Exception $e) {
            $exceptionparam = new stdClass();
            $exceptionparam->message = $e->getMessage();
            $exceptionparam->courseid = $params['courseid'];
            throw new \moodle_exception('errorcoursecontextnotvalid' , 'webservice', '', $exceptionparam);
        }
        course_require_view_participants($context);

        $course = get_course($params['courseid']);
        $manager = new \course_enrolment_manager($PAGE, $course);

        $users = $manager->search_users($params['search'],
            $params['searchanywhere'],
            $params['page'],
            $params['perpage']);

        $results = [];
        // Add also extra user fields.
        $requiredfields = array_merge(
            ['id', 'fullname', 'profileimageurl', 'profileimageurlsmall'],
            get_extra_user_fields($context)
        );
        foreach ($users['users'] as $user) {
            // Don't include logged in user as a possible user.
            if ($user->id == $USER->id) {
                continue;
            }
            if (!has_capability('mod/dialogue:receive', $context, $user)) {
                // User does not have the ability to receive so remove them.
                continue;
            }
            if ($userdetails = user_get_user_details($user, $course, $requiredfields)) {
                $results[] = $userdetails;
            }
        }
        return $results;
    }

    /**
     * Returns description of external function result value.
     *
     * @return \external_single_structure
     */
    public static function search_users_returns()  {
        global $CFG;
        require_once($CFG->dirroot . '/user/externallib.php');
        return new external_multiple_structure(\core_user_external::user_description());
    }
}

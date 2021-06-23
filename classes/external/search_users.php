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
 * This is the external method for getting the list of users for the dialogue conversation form.
 *
 * @package    mod_dialogue
 * @copyright  2021 Dan Marsden <dan@danmarsden.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_dialogue\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use stdClass;
use external_api;
use external_function_parameters;
use external_value;
use external_multiple_structure;
use core_user_external;
use context_course;
use moodle_exception;
use course_enrolment_manager;
use core\session\exception;

/**
 * This is the external method for getting the information needed to present an attempts report.
 *
 * @copyright  2021 Dan Marsden <dan@danmarsden.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_users extends external_api {
    /**
     * Webservice parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
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
     * Return user attempts information in a h5p activity.
     *
     * @param int $courseid Course id
     * @param string $search The query
     * @param bool $searchanywhere Match anywhere in the string
     * @param int $page Page number
     * @param int $perpage Max per page
     * @return array report data
     */
    public static function execute(int $courseid, string $search, bool $searchanywhere, int $page, int $perpage): array {
        global $PAGE, $CFG, $USER;

        require_once($CFG->dirroot.'/enrol/locallib.php');
        require_once($CFG->dirroot.'/user/lib.php');

        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'courseid'       => $courseid,
                'search'         => $search,
                'searchanywhere' => $searchanywhere,
                'page'           => $page,
                'perpage'        => $perpage
            ]
        );
        $context = context_course::instance($params['courseid']);
        try {
            self::validate_context($context);
        } catch (Exception $e) {
            $exceptionparam = new stdClass();
            $exceptionparam->message = $e->getMessage();
            $exceptionparam->courseid = $params['courseid'];
            throw new moodle_exception('errorcoursecontextnotvalid' , 'webservice', '', $exceptionparam);
        }
        course_require_view_participants($context);

        $course = get_course($params['courseid']);
        $manager = new course_enrolment_manager($PAGE, $course);

        $users = $manager->search_users($params['search'],
            $params['searchanywhere'],
            $params['page'],
            $params['perpage']);

        $results = [];
        // Add also extra user fields.
        $requiredfields = array_merge(
            ['id', 'fullname', 'profileimageurl', 'profileimageurlsmall'],
            // TODO Does not support custom user profile fields (MDL-70456).
            \core_user\fields::get_identity_fields($context, false)
        );
        foreach ($users['users'] as $user) {
            // Don't include logged in user as a possible user.
            if ($user->id == $USER->id) {
                continue;
            }
            if ($userdetails = user_get_user_details($user, $course, $requiredfields)) {
                $results[] = $userdetails;
            }
        }
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        global $CFG;
        require_once($CFG->dirroot . '/user/externallib.php');
        return new external_multiple_structure(core_user_external::user_description());
    }
}
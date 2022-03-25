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

namespace mod_dialogue\event;

/**
 * The mod_dialogue instance list viewed event. Everything is handled by the parent class.
 *
 * @package    mod_dialogue
 * @since      Moodle 2.7
 * @copyright  2014 Troy Williams
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_module_instance_list_viewed extends \core\event\course_module_instance_list_viewed {
    /**
     * Create the event from course record.
     *
     * @param \stdClass $course
     * @return course_module_instance_list_viewed
     */
    public static function create_from_course(\stdClass $course) {
        $params = array(
            'context' => \context_course::instance($course->id)
        );
        $event = self::create($params);
        $event->add_record_snapshot('course', $course);
        return $event;
    }
}

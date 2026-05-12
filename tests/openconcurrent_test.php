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

global $CFG;
require_once($CFG->dirroot . '/mod/dialogue/locallib.php');

/**
 * Tests the capability that restricts concurrent conversations.
 *
 * @package    mod_dialogue
 * @copyright  2026 Escola Nacional de Saúde Pública Sergio Arouca
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openconcurrent_test extends \advanced_testcase {
    public function test_open_conversations_from_user() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $instance = $this->getDataGenerator()->create_module('dialogue', array('course' => $course->id));
        $cm = get_coursemodule_from_instance('dialogue', $instance->id, $course->id, false, MUST_EXIST);
        $dialogue = new \mod_dialogue\dialogue($cm, $course, $instance);

        // Initially there should be no open conversations for the student.
        $this->assertFalse(dialogue_has_open_conversations_from_user($cm->instance, $student->id));

        $this->setUser($student);
        $conversation = new \mod_dialogue\conversation($dialogue);
        $conversation->add_participant($teacher->id);
        $conversation->set_subject('Test conversation');
        $conversation->set_body('Test message', FORMAT_PLAIN);
        $conversation->save();
        $conversation->send();

        // After sending the conversation there should be an open conversation for the student.
        $this->assertTrue(dialogue_has_open_conversations_from_user($cm->instance, $student->id));

        $conversation->close();

        // After closing the conversation there should be no open conversations for the student.
        $this->assertFalse(dialogue_has_open_conversations_from_user($cm->instance, $student->id));
    }
}

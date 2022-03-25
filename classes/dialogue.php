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
 * Library of extra functions for the dialogue module not part of the standard add-on module API set
 * but used by scripts in the mod/dialogue folder
 *
 * @package   mod_dialogue
 * @copyright 2013 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 *
 * Provides access to dialogue module constants.
 *
 * Sets up and provides access to dialogue module application caches.
 * @package mod_dialogue
 */
class dialogue {
    /** The state to indicate a open conversation and replies **/
    const STATE_OPEN            = 'open';
    /** The state to indicate a conversation or reply that is a draft **/
    const STATE_DRAFT           = 'draft';
    /** The state to indicated a conversation used in bulk creation of other
    conversations  **/
    const STATE_BULK_AUTOMATED  = 'bulkautomated';
    /** The state to indicate a closed conversation and replies **/
    const STATE_CLOSED          = 'closed';
    /** @var string  The state to indicate a draft conversation or reply that has been discarded */
    const STATE_TRASHED         = 'trashed';
    /** Flag state - sent.   */
    const FLAG_SENT = 'sent';
    /** Flag state - read. */
    const FLAG_READ = 'read';
    /** @var int Page size */
    const PAGINATION_PAGE_SIZE = 20;
    /** @var int Max restults to show */
    const PAGINATION_MAX_RESULTS = 1000;
    /** @var \stdClass Course record */
    protected $_course  = null;
    /** @var \stdClass Module record  */
    protected $_module  = null;
    /** @var \stdClass Config  */
    protected $_config  = null;
    /** @var \stdClass Course module  */
    protected $_cm      = null;
    /** @var \stdClass context  */
    protected $_context = null;

    /**
     * Constructor for dialogue class, requires course module to load
     * context, passing optional course and activity record objects will
     * save extra database calls.
     *
     * @param \stdClass $cm
     * @param \stdClass $course
     * @param \stdClass $module
     */
    public function __construct($cm, $course = null, $module = null) {
        $this->set_cm($cm);

        $context = \context_module::instance($cm->id, MUST_EXIST);
        $this->set_context($context);

        if (!is_null($course)) {
            $this->set_course($course);
        }

        if (!is_null($module)) {
            $this->set_activity_record($module);
        }
    }

    /**
     * PHP overloading magic to make the $dialogue->course syntax work by redirecting
     * it to the corresponding $dialogue->magic_get_course() method if there is one, and
     * throwing an exception if not. Taken from pagelib.php
     *
     * @param string $name property name
     * @return mixed
     */
    public function __get($name) {
        $getmethod = 'magic_get_' . $name;
        if (method_exists($this, $getmethod)) {
            return $this->$getmethod();
        } else {
            throw new coding_exception('Unknown property: ' . $name);
        }
    }

    /**
     * Return message states that can be marked with a read flag
     * FLAG_READ
     *
     * @return array
     */
    public static function get_unread_states() {
        return array(self::STATE_OPEN, self::STATE_CLOSED);
    }

    /**
     * Set up a dialogue based on dialogue identifier
     *
     * @param int $dialogueid
     * @return dialogue
     */
    public static function instance($dialogueid) {
        $cm = get_coursemodule_from_instance('dialogue', $dialogueid, 0, false, MUST_EXIST);
        return new dialogue($cm);
    }

    /**
     * Return module visibility
     *
     * @return boolean
     */
    public function is_visible() {
        if ($this->_cm->visible == false) {
            return false;
        }
        if (is_null($this->_course)) {
            $this->load_course();
        }
        if ($this->_course->visible == false) {
            return false;
        }
        return true;
    }

    /**
     * Load module value into class.
     * @throws \dml_exception
     */
    protected function load_activity_record() {
        global $DB;

        $this->_module = $DB->get_record($this->_cm->modname, array('id' => $this->_cm->instance), '*', MUST_EXIST);
    }

    /**
     * Load course value into class.
     * @throws \dml_exception
     */
    protected function load_course() {
        global $DB;

        $this->_course = $DB->get_record('course', array('id' => $this->_cm->course), '*', MUST_EXIST);
    }

    /**
     * Load config into class.
     * @return false|mixed|object|\stdClass|string|null
     * @throws \dml_exception
     */
    protected function magic_get_config() {

        if (is_null($this->_config)) {
            $this->_config = get_config('dialogue');
        }
        return $this->_config;
    }

    /**
     * Get cm
     * @return \stdClass|null
     */
    protected function magic_get_cm() {
        return $this->_cm;
    }

    /**
     * Get context from class.
     * @return \stdClass|null
     */
    protected function magic_get_context() {
        return $this->_context;
    }

    /**
     * Get activity record from class.
     * @return \stdClass|null
     * @throws \dml_exception
     */
    protected function magic_get_activityrecord() {
        if (is_null($this->_module)) {
            $this->load_activity_record();
        }
        return $this->_module;
    }

    /**
     * Get course record from class.
     * @return \stdClass|null
     * @throws \dml_exception
     */
    protected function magic_get_course() {
        if (is_null($this->_course)) {
            $this->load_course();
        }
        return $this->_course;
    }

    /**
     * Get dialogue->id
     * @return mixed
     * @throws \dml_exception
     */
    protected function magic_get_dialogueid() {
        if (is_null($this->_module)) {
            $this->load_activity_record();
        }
        return $this->_module->id;
    }

    /**
     * Set activity record in class.
     * @param \stdClass $module
     * @throws \dml_exception
     */
    protected function set_activity_record($module) {
        if (is_null($this->_course)) {
            $this->load_course();
        }
        if ($module->id != $this->_cm->instance || $module->course != $this->_course->id) {
            throw new coding_exception('The activity record does correspond to the cm that has been set.');
        }
        $this->_module = $module;
    }

    /**
     * Set CM in class.
     * @param \stdClass $cm
     */
    protected function set_cm($cm) {
        if (!isset($cm->id) || !isset($cm->course)) {
            throw new coding_exception('Invalid $cm parameter, it has to be record from the course_modules table.');
        }
        $this->_cm = $cm;
    }

    /**
     * Set context in class.
     * @param \context_module $context
     */
    protected function set_context(\context_module $context) {
        $this->_context = $context;
    }

    /**
     * Set course in class.
     * @param \stdClass $course
     */
    protected function set_course($course) {
        if ($course->id != $this->_cm->course) {
            throw new coding_exception('The course you are trying to set does not seem to correspond to the cm that has been set.');
        }
        $this->_course = $course;
    }
}

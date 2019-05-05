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

namespace mod_dialogue\local\persistent;

use core\persistent;
use mod_dialogue\local\course_participants_cache;
use mod_dialogue\local\plugin_config;
use context_module;
use cache;
use cache_store;

defined('MOODLE_INTERNAL') || die();

class dialogue_persistent extends persistent {

    /** Table name. */
    const TABLE = 'dialogue';
    
    protected $context;
    protected $course;
    protected $coursemodule;
    
    protected static function define_properties() {
        global $COURSE;
        return [
            'course' => [
                'type' => PARAM_INT,
                'default' => $COURSE->id,
                'description' => 'Foreign key reference to the course'
            ],
            'name' => [
                'type' => PARAM_RAW,
                'description' => 'Dialogue name'
            ],
            'intro' => [
                'type' => PARAM_RAW,
                'description' => 'Dialogue introduction text',
                'optional' => true,
            ],
            'introformat' => [
                'type' => PARAM_INT,
                'choices' => [
                    FORMAT_HTML,
                    FORMAT_MOODLE,
                    FORMAT_PLAIN,
                    FORMAT_MARKDOWN
                ],
                'default' => FORMAT_MOODLE
            ],
            'maxattachments' => [
                'type' => PARAM_INT,
                'choices' => static::get_max_attachments_choices(true),
                'default' => plugin_config::get_property_default('maxattachments'),
            ],
            'maxbytes' => [
                'type' => PARAM_RAW,
                'choices' => static::get_max_bytes_choices(true),
                'default' => plugin_config::get_property_default('maxbytes')
            ],
            'usecoursegroups' => [
                'type' => PARAM_INT,
                'choices' => [0, 1],
                'default' => 0
            ]
        ];
    }
    
    /**
     * Associated course module record.
     *
     * @return mixed
     * @throws \coding_exception
     */
    public function get_course_module() {
        if (is_null($this->coursemodule)) {
            $this->load_course_module_and_context();
        }
        return $this->coursemodule;
    }
    
    /**
     * Associated course record.
     *
     * @return bool|\stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function get_course() {
        if (is_null($this->course)) {
            if ($this->raw_get('course') > 0) {
                $this->course = get_course($this->raw_get('course'));
            } else {
                $this->course = false;
            }
        }
        return $this->course;
    }
    
    /**
     * Associated context.
     *
     * @return mixed
     * @throws \coding_exception
     */
    public function get_context() {
        if (is_null($this->context)) {
            $this->load_course_module_and_context();
        }
        return $this->context;
    }
    
    /**
     * Load course module and context from course identifier.
     *
     * @throws \coding_exception
     */
    protected function load_course_module_and_context() {
        if ($this->raw_get('id') > 0) {
            $this->coursemodule = get_coursemodule_from_instance(
                'dialogue',
                $this->raw_get('id'),
                $this->raw_get('course'),
                false,
                MUST_EXIST
            );
            $this->context = context_module::instance($this->coursemodule->id, MUST_EXIST);
        } else {
            $this->coursemodule = false;
            $this->context = false;
        }
    }
    
    /**
     * Maximum allowed message attachments options.
     *
     * @param bool $keys
     * @return array
     * @throws \dml_exception
     */
    public static function get_max_attachments_choices($keys = false) {
        $choices = [];
        $maxattachments = plugin_config::get('maxattachments');
        foreach (plugin_config::get_property_choices('maxattachments') as $choice) {
            if ($choice > $maxattachments) {
                break;
            }
            array_push($choices, $choice);
        }
        return $keys ? array_keys($choices) : $choices;
    }
    
    /**
     * Maximum allowed message attachment size options.
     *
     * @param bool $keys
     * @return array
     * @throws \dml_exception
     */
    public static function get_max_bytes_choices($keys = false) {
        global $CFG, $COURSE;
        $maxmodulebytes = plugin_config::get('maxbytes');
        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, $maxmodulebytes);
        return $keys ? array_keys($choices) : $choices;
    }
}

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

defined('MOODLE_INTERNAL') || die();

use coding_exception;

/**
 * Class plugin_config.
 *
 * @package mod_dialogue
 */
class plugin_config {

    /** The franken name of COMPONENT. */
    const COMPONENT = 'mod_dialogue';

    /**
     * Plugin settings definition.
     *
     * @return array
     */
    protected static function define_properties() {
        global $CFG;
        return [
            'trackunread' => [
                'type' => PARAM_INT,
                'default' => 0,
                'choices' => [0,1]
            ],
            'maxbytes' => [
                'type' => PARAM_INT,
                'default' => 512000,
                'choices' => get_max_upload_sizes($CFG->maxbytes),
            ],
            'maxattachments' => [
                'type' => PARAM_INT,
                'default' => 5,
                'choices' => [0,1,2,3,4,5,6,7,8,9,10,20]
            ]
        ];
    }

    /**
     * Basic plugin config getter, if value not set use defined default.
     *
     * @param $property
     * @return mixed|null
     * @throws \dml_exception
     */
    public static function get($property) {
        if (!static::has_property($property)) {
            throw new coding_exception("Unexpected property {$property} requested.");
        }
        $value = get_config(static::COMPONENT, $property);
        if ($value === false) {
            return static::get_property_default($property);
        }
        return $value;
    }

    /**
     * Gets the choices for a property.
     *
     * @param $property
     * @return array
     */
    public static function get_property_choices($property) {
        $properties = static::properties_definition();
        if (!isset($properties[$property]['choices'])) {
            return array();
        }
        $choices = $properties[$property]['choices'];
        if ($choices instanceof \Closure) {
            return $choices();
        }
        return $choices;
    }

    /**
     * Gets the default value for a property.
     *
     * @param $property
     * @return mixed|null
     */
    public static function get_property_default($property) {
        $properties = static::properties_definition();
        if (!isset($properties[$property]['default'])) {
            return null;
        }
        $value = $properties[$property]['default'];
        if ($value instanceof \Closure) {
            return $value();
        }
        return $value;
    }

    /**
     * Returns whether or not a property was defined.
     *
     * @param $property
     * @return bool
     */
    public static function has_property($property) {
        $properties = static::properties_definition();
        return isset($properties[$property]);
    }

    /**
     * @TODO validation.
     *
     * @return array|null
     */
    public static function properties_definition() {
        static $definition = null;
        if (is_null($definition)) {
            $definition = static::define_properties();
        }
        return $definition;
    }

    /**
     * Basic plugin config setter.
     *
     * @param $property
     * @param $value
     * @return bool
     */
    public static function set($property, $value) {
        if (!static::has_property($property)) {
            throw new coding_exception("Unexpected property {$property} requested.");
        }
        return set_config($property, $value, static::COMPONENT);
    }
}

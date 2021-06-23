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
 * Dialogue external functions
 *
 * @package    mod_dialogue
 * @copyright  2021 Dan Marsden <dan@danmarsden.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = [
    'mod_dialogue_search_users' => [
        'classname' => 'mod_dialogue\external\search_users',
        'classpath' => '',
        'description' => 'Search within the list of course participants',
        'ajax' => true,
        'type' => 'read',
        'capabilities' => 'moodle/course:viewparticipants',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ],
];
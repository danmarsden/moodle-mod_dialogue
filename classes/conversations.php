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
 * Provide core functions to any conversation listing classes.
 *
 * @package   mod_dialogue
 * @copyright 2013 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * The following properties are alphabetical. Please keep it that way so that its
 * easy to maintain.
 *
 * @property-read dialogue $dialogue The dialogue this list of conversations belongs.
 * @property-read dialogue $page The page number used to create offset for fetching recordset.
 * @property-read dialogue $limit The number of rows to be returned in recordset.
 */
abstract class conversations implements \renderable {
    /** @var The dialogue this list of conversations belongs **/
    protected $_dialogue = null;
    /**
     * @var int|mixed
     */
    protected $_page = 0;
    /**
     * @var int|mixed
     */
    protected $_limit = dialogue::PAGINATION_PAGE_SIZE;

    /**
     * Construct
     * conversations constructor.
     * @param dialogue $dialogue
     * @param int $page
     * @param int $limit
     */
    public function __construct(dialogue $dialogue, $page = 0, $limit = dialogue::PAGINATION_MAX_RESULTS) {

        $this->_dialogue = $dialogue;
        $this->_page    = $page;

        if ($limit > dialogue::PAGINATION_MAX_RESULTS) {
            $this->_limit = dialogue::PAGINATION_MAX_RESULTS;
        } else {
            $this->_limit = $limit;
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
            throw new \coding_exception('Unknown property: ' . $name);
        }
    }

    /**
     * Please do not call this method directly
     *
     * @return /dialogue
     * @throws coding_exception
     */
    protected function magic_get_dialogue() {
        if (is_null($this->_dialogue)) {
            throw new \coding_exception('parent dialogue is not set');
        }
        return $this->_dialogue;
    }

    /**
     * Please do not call this method directly
     *
     * @return int
     */
    protected function magic_get_page() {
        return $this->_page;
    }

    /**
     * Please do not call this method directly
     *
     * @return int
     */
    protected function magic_get_limit() {
        return $this->_limit;
    }

    /**
     * Abstract setup
     * @return mixed
     */
    abstract public function setup();

    /**
     * Abstract records
     * @return mixed
     */
    abstract public function records();

    /**
     * Abstract rows matched.
     * @return mixed
     */
    abstract public function rows_matched();
}


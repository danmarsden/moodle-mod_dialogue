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

defined('MOODLE_INTERNAL') || die();

$definitions = array(
    // Used to store basic user information need by dialogue to avoid repetitive DB queries within one request.
    'userdetails' => array(
        'mode' => cache_store::MODE_REQUEST,
        'persistent' => true,
    ),
    // Used to store information to avoid repetitive DB queries within one request.
    'participants' => array(
        'mode' => cache_store::MODE_REQUEST,
        'persistent' => true,
    ),
    // Session UI params to application state.
    'params' => array(
        'mode' => cache_store::MODE_SESSION,
        'persistent' => true,
    ),
);

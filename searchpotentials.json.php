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

define('AJAX_SCRIPT', true);

/**
 *
 * @package mod-dialogue
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot.'/mod/dialogue/locallib.php');

// course module identifier
$id = required_param('id', PARAM_INT);
// search text
$q = required_param('q', PARAM_RAW);
// check access.
if (!isloggedin()) {;
    print_error('mustbeloggedin');
}
// check session
if (!confirm_sesskey()) {
    print_error('invalidsesskey');
}

$cm      = get_coursemodule_from_id('dialogue', $id, 0, false, MUST_EXIST);
$PAGE->set_cm($cm);

list($receivers, $matches, $pagesize) = dialogue_search_potentials(new dialogue($cm), $q);

$return = array();
$return['results']  = array_values($receivers);
$return['matches']  = $matches;
$return['pagesize'] = $pagesize;
header('Content-type: application/json; charset=utf-8');
echo json_encode($return);
exit;

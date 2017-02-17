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

$logs = array(
    // Dialogue instance log actions.
    array('module' => 'dialogue', 'action' => 'add',
        'mtable' => 'dialogue', 'field' => 'name'),
    array('module' => 'dialogue', 'action' => 'update',
        'mtable' => 'dialogue', 'field' => 'name'),
    array('module' => 'dialogue', 'action' => 'view',
        'mtable' => 'dialogue', 'field' => 'name'),
    array('module' => 'dialogue', 'action' => 'view by role',
        'mtable' => 'dialogue', 'field' => 'name'),
    array('module' => 'dialogue', 'action' => 'view all',
        'mtable' => 'dialogue', 'field' => 'name'),
    // Conversation log actions.
    array('module' => 'dialogue', 'action' => 'close conversation',
        'mtable' => 'dialogue_conversations', 'field' => 'subject'),
    array('module' => 'dialogue', 'action' => 'delete conversation',
        'mtable' => 'dialogue_conversations', 'field' => 'subject'),
    array('module' => 'dialogue', 'action' => 'open conversation',
        'mtable' => 'dialogue_conversations', 'field' => 'subject'),
    array('module' => 'dialogue', 'action' => 'view conversation',
        'mtable' => 'dialogue_conversations', 'field' => 'subject'),
    // Reply log actions.
    array('module' => 'dialogue', 'action' => 'reply',
        'mtable' => 'dialogue_conversations', 'field' => 'subject'),
);

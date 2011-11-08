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
 * Code fragment to define the module version etc.
 * This fragment is called by /admin/index.php
 *
 * @package mod-dialogue
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$module->version  = 2011110900;
$module->requires = 2010112400;  // Requires this Moodle version 2.x
$module->release  = '2.0.4+';  // Human-friendly version name
$module->maturity = MATURITY_STABLE;            // this version's maturity level
$module->cron     = 60;
?>

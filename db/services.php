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
 * External services for AI Grade
 *
 * @package    local_assignmentaifeedback
 * @copyright  2025 Brian A. Pool, National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_assignmentaifeedback_grade_bulk' => [
        'classname'   => 'local_assignmentaifeedback\external\grade_bulk',
        'methodname'  => 'execute',
        'description' => 'Queue AI grading for all ungraded submissions',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'local/assignmentaifeedback:grade',
    ],
    'local_assignmentaifeedback_grade_single' => [
        'classname'   => 'local_assignmentaifeedback\external\grade_single',
        'methodname'  => 'execute',
        'description' => 'Queue AI grading for a single submission',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'local/assignmentaifeedback:grade',
    ],
];

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
 * Capability definitions for local_assignmentaifeedback
 *
 * @package    local_assignmentaifeedback
 * @copyright  2025 Brian A. Pool, National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Use the AI on an assignment that already has it switched on: the grading buttons,
    // the feedback panel and the automatic grading on submission.
    'local/assignmentaifeedback:grade' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Switch the AI on for an assignment and edit its settings: the "KI-Bewertung" section
    // in the assignment form (enable, automatic grading, both prompts, the rubric file).
    //
    // Deliberately NOT granted to the teacher archetypes. Whoever edits these settings
    // decides which submissions are sent to the AI provider and under which prompt, so
    // this is a rollout decision rather than a teaching one. Grant it where the plugin has
    // been approved -- typically per course category, see README -- or, to hand it to
    // every editing teacher site-wide, add 'editingteacher' => CAP_ALLOW below.
    'local/assignmentaifeedback:configure' => [
        'riskbitmask' => RISK_CONFIG | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];

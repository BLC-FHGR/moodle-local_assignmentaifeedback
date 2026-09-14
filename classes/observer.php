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
 * Event observer for local_assignmentaifeedback
 *
 * @package    local_assignmentaifeedback
 * @copyright  2025 Brian A. Pool, National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assignmentaifeedback;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer class
 */
class observer {

    /**
     * Handle an assignment submission: if auto-grading is enabled for the
     * assignment, queue a background (ad-hoc) task that generates the AI
     * feedback suggestion. The AI call itself never runs inside this event
     * (it must not block or slow down the student's submission).
     *
     * @param \mod_assign\event\assessable_submitted $event
     */
    public static function submission_submitted(\core\event\base $event) {
        global $DB;

        try {
            $context = $event->get_context();
            if (!$context || $context->contextlevel != CONTEXT_MODULE) {
                return;
            }
            $cmid = $context->instanceid;

            $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                return;
            }

            $config = $DB->get_record('local_assignmentaifeedback_config', ['assignmentid' => $cm->instance]);
            if (!$config || empty($config->enabled) || empty($config->auto_grade)) {
                return;
            }

            // The student who owns the submission (relateduserid when a teacher
            // submits on behalf, otherwise the acting user).
            $userid = !empty($event->relateduserid) ? $event->relateduserid : $event->userid;
            if (empty($userid)) {
                return;
            }

            \local_assignmentaifeedback\task\autograde_task::queue($cmid, $userid);

        } catch (\Throwable $e) {
            // Never let AI grading interfere with the submission itself.
            debugging('local_assignmentaifeedback submission observer error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

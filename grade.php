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
 * AI Grading interface for bulk grading - AJAX endpoint
 *
 * @package    local_assignmentaifeedback
 * @copyright  2025 Brian A. Pool, National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

$cmid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_sesskey();
require_capability('local/assignmentaifeedback:grade', $context);

$assignment = new assign($context, $cm, $course);

// Check if AI grading is enabled for this assignment
$aiconfig = $DB->get_record('local_assignmentaifeedback_config', ['assignmentid' => $assignment->get_instance()->id]);

if (!$aiconfig || !$aiconfig->enabled) {
    echo json_encode(['success' => false, 'error' => get_string('assignmentaifeedback_disabled', 'local_assignmentaifeedback')]);
    die();
}

// Handle the grading action
if ($action === 'grade') {

    // Release the PHP session lock now that all access checks are done. The AI
    // request can take a long time; without this, other requests from the same
    // user (e.g. other browser tabs) would block until grading finishes.
    \core\session\manager::write_close();

    // No AI request is performed here. Grading every submission inside this one
    // request would take (number of submissions x model latency) and die at the web
    // server request timeout, losing everything after the point of failure. One
    // background task per submission is queued instead: each is retried on its own
    // if the AI service is unavailable, and the panel shows each suggestion as it
    // arrives.
    $grader  = new \local_assignmentaifeedback\grader($assignment, $context, $aiconfig);
    $pending = $grader->get_pending_submissions();

    if (empty($pending)) {
        echo json_encode(['success' => false, 'count' => 0, 'error' => get_string('no_ungraded', 'local_assignmentaifeedback')]);
        die();
    }

    $queued = 0;
    foreach (array_keys($pending) as $pendinguserid) {
        if (\local_assignmentaifeedback\task\autograde_task::queue($cm->id, $pendinguserid, true, false, (int) $USER->id)) {
            $queued++;
        }
    }

    if ($queued === 0) {
        echo json_encode(['success' => false, 'count' => 0, 'error' => get_string('error_queue_failed', 'local_assignmentaifeedback')]);
        die();
    }

    echo json_encode(['success' => true, 'count' => $queued, 'queued' => true]);
    die();
}

echo json_encode(['success' => false, 'error' => get_string('error_invalid_action', 'local_assignmentaifeedback')]);

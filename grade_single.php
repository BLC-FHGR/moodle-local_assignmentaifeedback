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
 * AI Grading interface for single student - AJAX endpoint
 *
 * @package    local_assignmentaifeedback
 * @copyright  2025 Brian A. Pool, National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

$cmid = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$force_regrade = optional_param('force_regrade', 0, PARAM_INT);

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

    // Release the PHP session lock now that all access checks are done.
    \core\session\manager::write_close();

    $assignmentid = $assignment->get_instance()->id;

    // The AI request itself is NOT performed here. It regularly takes longer than
    // the web server request timeout allows, and holding a web server process for
    // minutes per click does not scale. Instead the same ad-hoc task the automatic
    // grading uses is queued, and the feedback panel polls for the result.
    $existing = \local_assignmentaifeedback\grader::get_stored_feedback_record($assignmentid, $userid);

    if (!$force_regrade && $existing) {
        echo json_encode([
            'success'        => false,
            'already_graded' => true,
            'error'          => get_string('already_has_feedback', 'local_assignmentaifeedback'),
        ]);
        die();
    }

    // Fail fast on a submission there is nothing to grade in, rather than queueing
    // a task that can only fail out of the teacher's sight.
    if (!$assignment->get_user_submission($userid, false)) {
        echo json_encode(['success' => false, 'error' => get_string('error_no_submission', 'local_assignmentaifeedback')]);
        die();
    }

    $queued = \local_assignmentaifeedback\task\autograde_task::queue(
        $cm->id,
        $userid,
        true,
        (bool) $force_regrade,
        (int) $USER->id
    );

    if (!$queued) {
        echo json_encode(['success' => false, 'error' => get_string('error_queue_failed', 'local_assignmentaifeedback')]);
        die();
    }

    echo json_encode([
        'success'  => true,
        'queued'   => true,
        'regraded' => (bool) $force_regrade,
        // The panel polls until a suggestion newer than this appears, so a regrade
        // is not mistaken for the feedback that is already on screen.
        'since'    => $existing ? (int) $existing->timemodified : 0,
    ]);
    die();
}

echo json_encode(['success' => false, 'error' => get_string('error_invalid_action', 'local_assignmentaifeedback')]);

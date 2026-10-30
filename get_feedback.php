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
 * AJAX endpoint: return the stored AI feedback suggestion for a submission.
 *
 * @package    local_assignmentaifeedback
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

$cmid   = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$cm      = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_sesskey();
require_capability('local/assignmentaifeedback:grade', $context);

$assignment = new assign($context, $cm, $course);

$record = \local_assignmentaifeedback\grader::get_stored_feedback_record($assignment->get_instance()->id, $userid);

// Whether the suggestion was generated from an earlier version of the submission.
$stale = false;
if ($record) {
    $aiconfig = $DB->get_record('local_assignmentaifeedback_config', ['assignmentid' => $assignment->get_instance()->id]);
    $grader = new \local_assignmentaifeedback\grader($assignment, $context, $aiconfig ?: new stdClass());
    $stale = $grader->is_feedback_stale($record, $userid);
}

// The last grading run, if it failed. A successful run removes it.
$error = \local_assignmentaifeedback\grader::get_error_record($assignment->get_instance()->id, $userid);

echo json_encode([
    'success'      => true,
    'feedback'     => $record ? (string) $record->feedback : '',
    'timemodified' => $record ? (int) $record->timemodified : 0,
    'stale'        => $stale,
    'error'        => $error ? (string) $error->errormessage : '',
    'errortime'    => $error ? (int) $error->timemodified : 0,
    'retrying'     => $error && !empty($error->retrying),
]);
die();

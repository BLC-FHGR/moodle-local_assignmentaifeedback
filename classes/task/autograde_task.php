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
 * Ad-hoc task: generate an AI feedback suggestion for a submission in the background.
 *
 * @package    local_assignmentaifeedback
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assignmentaifeedback\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Background grading task.
 */
class autograde_task extends \core\task\adhoc_task {

    /**
     * @return string
     */
    public function get_name() {
        return get_string('task_autograde', 'local_assignmentaifeedback');
    }

    /**
     * Queue a grading run for one submission.
     *
     * Used by the event observer (automatic grading) and by the grading buttons,
     * which do not perform the AI request in the web request itself: it can take
     * minutes, which would hold a web server process and run into the web server
     * request timeout.
     *
     * @param int $cmid Course module ID of the assignment
     * @param int $userid The student whose submission is graded
     * @param bool $manual True for a teacher-triggered run: it ignores the
     *  per-assignment "grade automatically on submission" flag and runs under the
     *  triggering teacher's account
     * @param bool $forceregrade Whether to replace feedback that already exists
     * @param int $graderid Account the AI request runs under; 0 resolves the
     *  configured service account
     * @return bool Whether the task was queued
     */
    public static function queue(int $cmid, int $userid, bool $manual = false,
            bool $forceregrade = false, int $graderid = 0): bool {
        $task = new self();
        $task->set_custom_data((object) [
            'cmid'         => $cmid,
            'userid'       => $userid,
            'manual'       => $manual ? 1 : 0,
            'forceregrade' => $forceregrade ? 1 : 0,
            'graderid'     => $graderid,
        ]);

        // Second argument dedupes identical queued tasks. A regrade carries
        // different custom data, so it is never swallowed by a pending plain run.
        return \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Run the background grading.
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $data = $this->get_custom_data();
        if (empty($data->cmid) || empty($data->userid)) {
            return;
        }

        $cm = get_coursemodule_from_id('assign', $data->cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $course = $DB->get_record('course', ['id' => $cm->course]);
        if (!$course) {
            return;
        }
        $context = \context_module::instance($cm->id);

        $manual = !empty($data->manual);

        $config = $DB->get_record('local_assignmentaifeedback_config', ['assignmentid' => $cm->instance]);
        if (!$config || empty($config->enabled)) {
            return;
        }
        // The auto_grade flag only governs grading on submission. A run a teacher
        // triggered explicitly must go ahead regardless.
        if (!$manual && empty($config->auto_grade)) {
            return;
        }

        // Resolve the user under which the AI request runs. This user needs the
        // ai_manager capability, confirmed AI usage and quota. A manual run carries
        // the teacher who triggered it, so capability and quota stay attributed to
        // the person who asked for the grading.
        $graderuser = self::resolve_grader_user($context, (int) ($data->graderid ?? 0));
        if (!$graderuser) {
            mtrace('local_assignmentaifeedback autograde: no valid grading account configured/found - skipped.');
            return;
        }

        // Run as that user (so capability/quota checks apply to a real account).
        self::setup_user($graderuser, $course);

        // A background request may legitimately take minutes: the site-wide
        // local_ai_manager timeout is dimensioned for interactive requests and
        // aborts long rubric prompts ("The API took too long to process your
        // request"). Raise it for this task only.
        $restoretimeout = \local_assignmentaifeedback\local\request_timeout::for_background();

        try {
            $assignment = new \assign($context, $cm, $course);
            $grader = new \local_assignmentaifeedback\grader($assignment, $context, $config);
            $result = $grader->grade_single_submission($data->userid, !empty($data->forceregrade));

            if (empty($result['success'])) {
                $err = !empty($result['error']) ? $result['error'] : 'unknown error';

                if (!empty($result['already_graded'])) {
                    mtrace('local_assignmentaifeedback autograde: userid=' . $data->userid . ' already has feedback - skipped.');
                } else if (!empty($result['retryable'])) {
                    // The AI service failed (timeout, gateway error, quota). Throwing
                    // makes Moodle mark the task as failed and retry it with its usual
                    // backoff, instead of silently dropping the submission: previously
                    // the task completed successfully here and the queued entry was
                    // deleted, so the suggestion was lost for good.
                    mtrace('local_assignmentaifeedback autograde: userid=' . $data->userid
                        . ' failed, will be retried - ' . $err);
                    throw new \moodle_exception('error_autograde_failed', 'local_assignmentaifeedback', '', $err);
                } else {
                    // Nothing to retry (e.g. the submission carries no readable text).
                    mtrace('local_assignmentaifeedback autograde: userid=' . $data->userid . ' failed - ' . $err);
                }
            } else {
                mtrace('local_assignmentaifeedback autograde: userid=' . $data->userid . ' done.');
            }
        } finally {
            $restoretimeout();
            self::setup_user(null, null);
        }
    }

    /**
     * Set the current user for the duration of the task, tolerant to Moodle
     * versions (\core\cron::setup_user in 4.4+, global cron_setup_user before).
     *
     * @param \stdClass|null $user
     * @param \stdClass|null $course
     */
    private static function setup_user($user, $course) {
        if (class_exists('\\core\\cron') && method_exists('\\core\\cron', 'setup_user')) {
            \core\cron::setup_user($user, $course);
        } else if (function_exists('cron_setup_user')) {
            cron_setup_user($user, $course);
        }
    }

    /**
     * Resolve the account under which the AI request runs.
     *
     * @param \context_module $context
     * @param int $graderid Account requested by the caller, 0 for the service account
     * @return \stdClass|null
     */
    private static function resolve_grader_user(\context_module $context, int $graderid = 0) {
        // 0) Account handed over by the caller (the teacher who pressed the button).
        if ($graderid > 0) {
            $user = \core_user::get_user($graderid, '*', IGNORE_MISSING);
            if ($user && $user->confirmed && empty($user->deleted) && empty($user->suspended)
                    && has_capability('local/assignmentaifeedback:grade', $context, $user)) {
                return $user;
            }
            // Named but no longer usable: do not silently grade as someone else.
            return null;
        }

        // 1) Configured service account (username).
        $username = trim((string) get_config('local_assignmentaifeedback', 'autograde_user'));
        if ($username !== '') {
            $user = \core_user::get_user_by_username($username, '*', null, IGNORE_MISSING);
            if ($user && $user->confirmed && empty($user->deleted) && empty($user->suspended)) {
                return $user;
            }
            // Configured but invalid: do not silently fall back to someone else.
            return null;
        }

        // 2) Fallback: an enrolled user with the grading capability in this context.
        $users = get_enrolled_users($context, 'local/assignmentaifeedback:grade', 0, 'u.*', null, 0, 1);
        if (!empty($users)) {
            return reset($users);
        }

        return null;
    }
}

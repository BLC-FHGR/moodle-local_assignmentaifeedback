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
 * @package    local_aigrade
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrade\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Background grading task.
 */
class autograde_task extends \core\task\adhoc_task {

    /**
     * @return string
     */
    public function get_name() {
        return get_string('task_autograde', 'local_aigrade');
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

        $config = $DB->get_record('local_aigrade_config', ['assignmentid' => $cm->instance]);
        if (!$config || empty($config->enabled) || empty($config->auto_grade)) {
            return;
        }

        // Resolve the user under which the AI request runs. This user needs the
        // ai_manager capability, confirmed AI usage and quota.
        $graderuser = self::resolve_grader_user($context);
        if (!$graderuser) {
            mtrace('local_aigrade autograde: no valid grading account configured/found - skipped.');
            return;
        }

        // Run as that user (so capability/quota checks apply to a real account).
        self::setup_user($graderuser, $course);

        try {
            $assignment = new \assign($context, $cm, $course);
            $grader = new \local_aigrade\grader($assignment, $context, $config);
            $result = $grader->grade_single_submission($data->userid, false);

            if (empty($result['success'])) {
                if (!empty($result['already_graded'])) {
                    mtrace('local_aigrade autograde: userid=' . $data->userid . ' already has feedback - skipped.');
                } else {
                    $err = !empty($result['error']) ? $result['error'] : 'unknown error';
                    mtrace('local_aigrade autograde: userid=' . $data->userid . ' failed - ' . $err);
                }
            } else {
                mtrace('local_aigrade autograde: userid=' . $data->userid . ' done.');
            }
        } finally {
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
     * @return \stdClass|null
     */
    private static function resolve_grader_user(\context_module $context) {
        // 1) Configured service account (username).
        $username = trim((string) get_config('local_aigrade', 'autograde_user'));
        if ($username !== '') {
            $user = \core_user::get_user_by_username($username, '*', null, IGNORE_MISSING);
            if ($user && $user->confirmed && empty($user->deleted) && empty($user->suspended)) {
                return $user;
            }
            // Configured but invalid: do not silently fall back to someone else.
            return null;
        }

        // 2) Fallback: an enrolled user with the grading capability in this context.
        $users = get_enrolled_users($context, 'local/aigrade:grade', 0, 'u.*', null, 0, 1);
        if (!empty($users)) {
            return reset($users);
        }

        return null;
    }
}

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
 * Privacy Subsystem implementation for local_assignmentaifeedback
 *
 * @package    local_assignmentaifeedback
 * @copyright  2025 Brian A. Pool, National Trail Local Schools
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assignmentaifeedback\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for local_assignmentaifeedback.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /** @var string[] Tables holding per-student data, each keyed by assignmentid and userid. */
    private const USER_TABLES = [
        'local_assignmentaifeedback_feedback',
        'local_assignmentaifeedback_error',
    ];

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {

        // Data sent to external AI providers.
        $collection->add_external_location_link('ai_provider', [
            'submissiontext' => 'privacy:metadata:ai_provider:submissiontext',
            'assignmentname' => 'privacy:metadata:ai_provider:assignmentname',
            'assignmentinstructions' => 'privacy:metadata:ai_provider:assignmentinstructions',
            'rubric' => 'privacy:metadata:ai_provider:rubric',
        ], 'privacy:metadata:ai_provider');

        // Stored AI feedback suggestions.
        $collection->add_database_table('local_assignmentaifeedback_feedback', [
            'userid' => 'privacy:metadata:local_assignmentaifeedback_feedback:userid',
            'feedback' => 'privacy:metadata:local_assignmentaifeedback_feedback:feedback',
            'submissionhash' => 'privacy:metadata:local_assignmentaifeedback_feedback:submissionhash',
            'timecreated' => 'privacy:metadata:local_assignmentaifeedback_feedback:timecreated',
            'timemodified' => 'privacy:metadata:local_assignmentaifeedback_feedback:timemodified',
        ], 'privacy:metadata:local_assignmentaifeedback_feedback');

        // Last failed grading run per submission.
        $collection->add_database_table('local_assignmentaifeedback_error', [
            'userid' => 'privacy:metadata:local_assignmentaifeedback_error:userid',
            'errormessage' => 'privacy:metadata:local_assignmentaifeedback_error:errormessage',
            'timemodified' => 'privacy:metadata:local_assignmentaifeedback_error:timemodified',
        ], 'privacy:metadata:local_assignmentaifeedback_error');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $params = [
            'modid' => self::get_assign_moduleid(),
            'modlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];
        foreach (self::USER_TABLES as $table) {
            $sql = "SELECT ctx.id
                      FROM {{$table}} f
                      JOIN {course_modules} cm ON cm.instance = f.assignmentid AND cm.module = :modid
                      JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                     WHERE f.userid = :userid";
            $contextlist->add_from_sql($sql, $params);
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $params = ['modid' => self::get_assign_moduleid(), 'cmid' => $context->instanceid];
        foreach (self::USER_TABLES as $table) {
            $sql = "SELECT f.userid
                      FROM {{$table}} f
                      JOIN {course_modules} cm ON cm.instance = f.assignmentid AND cm.module = :modid
                     WHERE cm.id = :cmid";
            $userlist->add_from_sql('userid', $sql, $params);
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $assignmentid = self::get_assignmentid_from_context($context);
            if (!$assignmentid) {
                continue;
            }
            $record = $DB->get_record('local_assignmentaifeedback_feedback',
                ['assignmentid' => $assignmentid, 'userid' => $userid]);
            if ($record) {
                $data = (object) [
                    'feedback' => format_text($record->feedback, FORMAT_HTML),
                    'submissionhash' => $record->submissionhash,
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
                writer::with_context($context)->export_data(
                    [get_string('privacy:aifeedbackpath', 'local_assignmentaifeedback')], $data);
            }

            $error = $DB->get_record('local_assignmentaifeedback_error',
                ['assignmentid' => $assignmentid, 'userid' => $userid]);
            if ($error) {
                $data = (object) [
                    'errormessage' => $error->errormessage,
                    'timemodified' => transform::datetime($error->timemodified),
                ];
                writer::with_context($context)->export_data(
                    [get_string('privacy:aierrorpath', 'local_assignmentaifeedback')], $data);
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }
        $assignmentid = self::get_assignmentid_from_context($context);
        if ($assignmentid) {
            foreach (self::USER_TABLES as $table) {
                $DB->delete_records($table, ['assignmentid' => $assignmentid]);
            }
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $assignmentid = self::get_assignmentid_from_context($context);
            if ($assignmentid) {
                foreach (self::USER_TABLES as $table) {
                    $DB->delete_records($table, ['assignmentid' => $assignmentid, 'userid' => $userid]);
                }
            }
        }
    }

    /**
     * Delete multiple users' data within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $assignmentid = self::get_assignmentid_from_context($context);
        if (!$assignmentid) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge(['assignmentid' => $assignmentid], $inparams);
        foreach (self::USER_TABLES as $table) {
            $DB->delete_records_select($table, "assignmentid = :assignmentid AND userid $insql", $params);
        }
    }

    /**
     * Module id of the assign module.
     *
     * @return int
     */
    private static function get_assign_moduleid(): int {
        global $DB;
        return (int) $DB->get_field('modules', 'id', ['name' => 'assign']);
    }

    /**
     * Resolve the assign instance id from a module context.
     *
     * @param context_module $context
     * @return int|null
     */
    private static function get_assignmentid_from_context(context_module $context): ?int {
        global $DB;
        $instance = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid]);
        return $instance ? (int) $instance : null;
    }
}

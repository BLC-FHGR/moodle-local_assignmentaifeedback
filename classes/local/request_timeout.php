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
 * Per-request override of the local_ai_manager request timeout.
 *
 * @package    local_aigrade
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrade\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Raises the AI request timeout for the current process only.
 *
 * local_ai_manager builds its HTTP client with the site-wide 'requesttimeout'
 * setting, read via get_config() on every single request
 * (see local/ai_manager/classes/base_connector.php::make_request()). That value
 * defaults to 60 seconds and is dimensioned for short interactive requests; the
 * long rubric prompts of this plugin regularly need more and then abort with
 * "The API took too long to process your request or could not be reached in a
 * reasonable time" (cURL error 28), without any feedback being stored.
 *
 * Since get_config() gives $CFG->forced_plugin_settings precedence over the
 * stored value, the timeout can be widened for one process without touching the
 * site-wide setting or affecting any other plugin using local_ai_manager.
 */
class request_timeout {

    /**
     * @var int Default timeout in seconds for AI requests of a background (cron) run.
     *
     * Note: settings.php and db/upgrade.php intentionally repeat these numbers as
     * literals instead of referencing the constants, because they run at points
     * where a class of a not-yet-installed version may still be missing from the
     * cached classmap. Keep both places in sync when changing a default.
     */
    const DEFAULT_BACKGROUND_TIMEOUT = 300;

    /**
     * @var int Default timeout in seconds for interactive AI requests (grading buttons).
     *
     * Deliberately below the default Apache 'Timeout' of 300 seconds: once the web
     * server gives up, the browser receives a gateway error instead of the readable
     * message this plugin would render.
     */
    const DEFAULT_MANUAL_TIMEOUT = 240;

    /**
     * Raise the timeout for a background (ad-hoc task) run.
     *
     * @return callable Restores the previous state; always call it when done.
     */
    public static function for_background(): callable {
        return self::apply(self::resolve('autograde_timeout', self::DEFAULT_BACKGROUND_TIMEOUT));
    }

    /**
     * Raise the timeout for an interactive run (the grading buttons).
     *
     * @return callable Restores the previous state; always call it when done.
     */
    public static function for_manual(): callable {
        return self::apply(self::resolve('manual_timeout', self::DEFAULT_MANUAL_TIMEOUT));
    }

    /**
     * Read a timeout setting, falling back to the default if it was never written
     * (e.g. the plugin upgrade has not been run yet).
     *
     * @param string $name Setting name within local_aigrade
     * @param int $default
     * @return int
     */
    private static function resolve(string $name, int $default): int {
        $timeout = get_config('local_aigrade', $name);
        if ($timeout === false || $timeout === '') {
            return $default;
        }
        return (int) $timeout;
    }

    /**
     * Override the local_ai_manager request timeout for this process.
     *
     * @param int $timeout Timeout in seconds; 0 or less keeps the site-wide value.
     * @return callable Restores the previous state; always call it when done.
     */
    public static function apply(int $timeout): callable {
        global $CFG;

        $noop = function() {
        };

        if ($timeout <= 0) {
            // Explicit 0: keep the site-wide timeout.
            return $noop;
        }

        // Only the ai_manager backend reads this setting; core AI brings its own.
        $backend = get_config('local_aigrade', 'ai_backend');
        if ($backend !== false && $backend !== '' && $backend !== 'ai_manager') {
            return $noop;
        }

        if ((int) get_config('local_ai_manager', 'requesttimeout') >= $timeout) {
            // Site-wide timeout is already at least as generous.
            return $noop;
        }

        // Give the process a little more headroom than the HTTP request itself.
        // Note: raise() never lowers an existing limit and is a no-op on CLI.
        \core_php_time_limit::raise($timeout + 60);

        $hadplugin = isset($CFG->forced_plugin_settings['local_ai_manager']);
        $hadvalue = $hadplugin && array_key_exists('requesttimeout', $CFG->forced_plugin_settings['local_ai_manager']);
        $previous = $hadvalue ? $CFG->forced_plugin_settings['local_ai_manager']['requesttimeout'] : null;

        $CFG->forced_plugin_settings['local_ai_manager']['requesttimeout'] = $timeout;

        return function() use ($hadplugin, $hadvalue, $previous) {
            global $CFG;

            if ($hadvalue) {
                $CFG->forced_plugin_settings['local_ai_manager']['requesttimeout'] = $previous;
            } else {
                unset($CFG->forced_plugin_settings['local_ai_manager']['requesttimeout']);
                if (!$hadplugin) {
                    unset($CFG->forced_plugin_settings['local_ai_manager']);
                }
            }
        };
    }
}

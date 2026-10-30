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
 * Upgrade script for local_assignmentaifeedback
 *
 * @package    local_assignmentaifeedback
 * @copyright  2026 Brian A. Pool, National Trail Local Schools
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 *
 * This component was renamed from local_aigrade in v3.0.0-fhgr. Every install of
 * local_assignmentaifeedback therefore starts from db/install.xml at the current
 * schema, and the upgrade steps that local_aigrade accumulated between 2025012001
 * and 2026090100 can never run here. They were not carried over: keeping them would
 * describe changes to tables that never existed under this component name. The
 * history remains in git and in the final local_aigrade release.
 *
 * Data from an existing local_aigrade installation is migrated once, on install,
 * by db/install.php.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_assignmentaifeedback_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026103000) {
        // Fingerprint of the submission a suggestion was generated from, so a changed
        // submission is graded again instead of keeping the suggestion for the old one.
        // Existing rows stay NULL: which version they belong to is unknown.
        $table = new xmldb_table('local_assignmentaifeedback_feedback');
        $field = new xmldb_field('submissionhash', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'model');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026103000, 'local', 'assignmentaifeedback');
    }

    return true;
}

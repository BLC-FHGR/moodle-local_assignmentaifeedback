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
 * Install script for local_assignmentaifeedback
 *
 * @package    local_assignmentaifeedback
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Post-install hook.
 *
 * This plugin was called local_aigrade until v3.0.0-fhgr. Renaming the component makes
 * Moodle treat it as an entirely new plugin: it installs empty tables and leaves the old
 * ones untouched. This migrates the data across so a renamed installation keeps its
 * per-assignment configuration, stored suggestions, settings, uploaded rubrics and
 * capability overrides.
 *
 * It runs once, before update_capabilities() (see upgrade_plugins() in lib/upgradelib.php),
 * and does nothing at all on a clean install. Every step is written to be skippable, so a
 * partial run can be repeated without duplicating rows.
 *
 * The old local_aigrade tables are deliberately NOT dropped here. Uninstall the old plugin
 * through Site administration → Plugins → Plugin overview once the migration is verified.
 *
 * @return bool
 */
function xmldb_local_assignmentaifeedback_install() {
    global $DB;

    $dbman = $DB->get_manager();

    $oldcomponent = 'local_aigrade';
    $newcomponent = 'local_assignmentaifeedback';

    $oldconfigtable   = new xmldb_table('local_aigrade_config');
    $oldfeedbacktable = new xmldb_table('local_aigrade_feedback');

    if (!$dbman->table_exists($oldconfigtable) && !$dbman->table_exists($oldfeedbacktable)) {
        // Clean install, there is no predecessor to migrate from.
        return true;
    }

    // Per-assignment configuration. Row ids are not referenced anywhere (every lookup goes
    // through assignmentid), so they are not preserved.
    if ($dbman->table_exists($oldconfigtable)) {
        $rs = $DB->get_recordset('local_aigrade_config');
        foreach ($rs as $record) {
            if ($DB->record_exists('local_assignmentaifeedback_config',
                    ['assignmentid' => $record->assignmentid])) {
                continue;
            }
            unset($record->id);
            $DB->insert_record('local_assignmentaifeedback_config', $record);
        }
        $rs->close();
    }

    // Stored AI suggestions. (assignmentid, userid) is unique in both tables.
    if ($dbman->table_exists($oldfeedbacktable)) {
        $rs = $DB->get_recordset('local_aigrade_feedback');
        foreach ($rs as $record) {
            if ($DB->record_exists('local_assignmentaifeedback_feedback',
                    ['assignmentid' => $record->assignmentid, 'userid' => $record->userid])) {
                continue;
            }
            unset($record->id);
            $DB->insert_record('local_assignmentaifeedback_feedback', $record);
        }
        $rs->close();
    }

    // Plugin settings (AI name, backend, purpose, default instructions, autograde account
    // and timeout). 'version' and 'installrunning' belong to whichever component owns them
    // and must not be copied.
    $settings = $DB->get_records('config_plugins', ['plugin' => $oldcomponent]);
    foreach ($settings as $setting) {
        if ($setting->name === 'version' || $setting->name === 'installrunning') {
            continue;
        }
        if (get_config($newcomponent, $setting->name) !== false) {
            continue;
        }
        set_config($setting->name, $setting->value, $newcomponent);
    }

    // Uploaded rubrics. The component is part of the pathnamehash
    // (sha1("/$contextid/$component/$filearea/$itemid$filepath$filename")), so the hash has
    // to be recomputed or the files become unreachable through the file API.
    $files = $DB->get_records('files', ['component' => $oldcomponent]);
    foreach ($files as $file) {
        $newhash = \file_storage::get_pathname_hash(
            $file->contextid,
            $newcomponent,
            $file->filearea,
            $file->itemid,
            $file->filepath,
            $file->filename
        );
        if ($DB->record_exists('files', ['pathnamehash' => $newhash])) {
            continue;
        }
        $DB->update_record('files', (object) [
            'id'           => $file->id,
            'component'    => $newcomponent,
            'pathnamehash' => $newhash,
        ]);
    }

    // Capability overrides. Archetype defaults are applied by update_capabilities() straight
    // after this script; assign_capability() does not overwrite existing rows, so anything
    // inserted here wins over the defaults, which is what an explicit override should do.
    $oldcapability = 'local/aigrade:grade';
    $newcapability = 'local/assignmentaifeedback:grade';
    $overrides = $DB->get_records('role_capabilities', ['capability' => $oldcapability]);
    foreach ($overrides as $override) {
        if ($DB->record_exists('role_capabilities', [
                'capability' => $newcapability,
                'roleid'     => $override->roleid,
                'contextid'  => $override->contextid])) {
            continue;
        }
        unset($override->id);
        $override->capability = $newcapability;
        $override->timemodified = time();
        $DB->insert_record('role_capabilities', $override);
    }

    // Ad-hoc grading runs that were still queued when the rename happened. Their stored
    // class name points at the old namespace and would fatal on the next cron run.
    $DB->set_field('task_adhoc', 'classname', '\\local_assignmentaifeedback\\task\\autograde_task',
        ['classname' => '\\local_aigrade\\task\\autograde_task']);
    $DB->set_field('task_adhoc', 'component', $newcomponent, ['component' => $oldcomponent]);

    return true;
}

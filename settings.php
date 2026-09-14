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
 * Settings for local_assignmentaifeedback
 *
 * @package    local_assignmentaifeedback
 * @copyright  2025 Brian A. Pool, National Trail Local Schools
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_assignmentaifeedback', get_string('pluginname', 'local_assignmentaifeedback'));

    // Add important warning at the top.
    $settings->add(new admin_setting_heading(
        'local_assignmentaifeedback/warning',
        '',
        get_string('settings_warning_html', 'local_assignmentaifeedback')
    ));

    // AI Name setting.
    $settings->add(new admin_setting_configtext(
        'local_assignmentaifeedback/ai_name',
        get_string('ai_name', 'local_assignmentaifeedback'),
        get_string('ai_name_desc', 'local_assignmentaifeedback'),
        'KI',
        PARAM_TEXT
    ));

    // AI backend selection: local_ai_manager (default) or Moodle core AI subsystem.
    $aimanageravailable = class_exists('\local_ai_manager\manager');
    $backendoptions = [
        'ai_manager' => get_string('backend_ai_manager', 'local_assignmentaifeedback'),
        'core'       => get_string('backend_core', 'local_assignmentaifeedback'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_assignmentaifeedback/ai_backend',
        get_string('setting_ai_backend', 'local_assignmentaifeedback'),
        get_string('setting_ai_backend_desc', 'local_assignmentaifeedback')
            . ($aimanageravailable ? '' : '<br><strong>' . get_string('error_ai_manager_missing', 'local_assignmentaifeedback') . '</strong>'),
        'ai_manager',
        $backendoptions
    ));

    // Which local_ai_manager purpose to route the grading prompt through.
    // The available models are configured per purpose inside local_ai_manager.
    $purposeoptions = [];
    if (class_exists('\core_plugin_manager')) {
        foreach (\core_plugin_manager::instance()->get_plugins_of_type('aipurpose') as $name => $info) {
            $purposeoptions[$name] = $info->displayname . ' (' . $name . ')';
        }
    }
    if (empty($purposeoptions)) {
        // Fallback list if local_ai_manager (and thus the aipurpose subplugin type) is not installed yet.
        foreach (['feedback', 'singleprompt', 'chat'] as $name) {
            $purposeoptions[$name] = $name;
        }
    }
    $defaultpurpose = isset($purposeoptions['feedback']) ? 'feedback' : array_key_first($purposeoptions);
    $settings->add(new admin_setting_configselect(
        'local_assignmentaifeedback/ai_manager_purpose',
        get_string('setting_ai_manager_purpose', 'local_assignmentaifeedback'),
        get_string('setting_ai_manager_purpose_desc', 'local_assignmentaifeedback'),
        $defaultpurpose,
        $purposeoptions
    ));

    // Service account for automatic grading on submission (background/cron).
    $settings->add(new admin_setting_configtext(
        'local_assignmentaifeedback/autograde_user',
        get_string('setting_autograde_user', 'local_assignmentaifeedback'),
        get_string('setting_autograde_user_desc', 'local_assignmentaifeedback'),
        '',
        PARAM_USERNAME
    ));

    // Request timeout for the AI call in the grading task. Every AI request of this
    // plugin runs there, so this is the only timeout the plugin itself controls.
    // The site-wide local_ai_manager timeout is tuned for short interactive
    // requests and is too short for long rubric prompts.
    //
    // The default below is deliberately a literal and not read from
    // \local_assignmentaifeedback\local\request_timeout: this file is included whenever the
    // admin settings tree is built, and admin pages other than admin/index.php do
    // not define CACHE_DISABLE_ALL, so a class added by a not-yet-run upgrade is
    // still missing from the cached classmap. Keep it in sync with
    // DEFAULT_BACKGROUND_TIMEOUT (300).
    $settings->add(new admin_setting_configduration(
        'local_assignmentaifeedback/autograde_timeout',
        get_string('setting_autograde_timeout', 'local_assignmentaifeedback'),
        get_string('setting_autograde_timeout_desc', 'local_assignmentaifeedback'),
        300,
        60
    ));

    // Default instructions WITH rubric.
    $settings->add(new admin_setting_configtextarea(
        'local_assignmentaifeedback/default_instructions_with_rubric',
        get_string('assignmentaifeedback_instructions_with_rubric', 'local_assignmentaifeedback'),
        get_string('assignmentaifeedback_instructions_with_rubric_desc', 'local_assignmentaifeedback'),
        'Du bist eine Bewertungsassistenz für Dozierende an einer Hochschule. Du unterstützt bei der Bewertung schriftlicher Arbeiten (z. B. Exposés, Studien- und Seminararbeiten). Du vergibst keine abschliessende Note und übernimmst keine Bewertungshoheit. Du gibst eine nachvollziehbare, kriterienbezogene Punkteempfehlung ab. Die finale Entscheidung liegt bei den Dozierenden.

BEWERTUNGSGRUNDLAGE:
- Verwende für die Punktevergabe ausschliesslich das beigefügte Beurteilungsraster und die Vorgaben in dieser Anweisung.
- Erfinde keine zusätzlichen Kriterien, Anforderungen oder Punktabzüge.
- Bewerte nur Inhalte, die im eingereichten Dokument tatsächlich nachvollziehbar vorhanden sind.
- Nicht prüfbare Aspekte (z. B. rein visuelle Gestaltung) gelten nicht automatisch als erfüllt und werden als Unsicherheit gekennzeichnet.

AUSGABEFORMAT:
PUNKTEEMPFEHLUNG: [Summe] von [Maximum]

BEGRÜNDUNG JE KRITERIUM:
[Kriterium]: [erreichte Punkte] von [maximale Punkte] – [kurze, sachliche Begründung mit Bezug zur Abgabe]
(für jedes Kriterium des Rasters)

STÄRKEN:
[1–3 konkrete Stärken der Arbeit]

VERBESSERUNGSPOTENZIAL:
[1–3 konkrete, umsetzbare Hinweise]

HINWEISE:
- Formuliere sachlich, präzise und wertschätzend auf Hochschulniveau.
- Sprich die studierende Person direkt mit "Sie" an.
- Belege deine Einschätzung mit konkretem Bezug zur Abgabe.
- Kennzeichne Unsicherheiten transparent.',
        PARAM_TEXT
    ));

    // Default instructions WITHOUT rubric.
    $settings->add(new admin_setting_configtextarea(
        'local_assignmentaifeedback/default_instructions_without_rubric',
        get_string('assignmentaifeedback_instructions_without_rubric', 'local_assignmentaifeedback'),
        get_string('assignmentaifeedback_instructions_without_rubric_desc', 'local_assignmentaifeedback'),
        'Du bist eine Bewertungsassistenz für Dozierende an einer Hochschule. Du unterstützt bei der Bewertung schriftlicher Arbeiten anhand der Aufgabenstellung. Du vergibst keine abschliessende Note und übernimmst keine Bewertungshoheit. Du gibst eine nachvollziehbare, kriterienbezogene Punkteempfehlung ab. Die finale Entscheidung liegt bei den Dozierenden.

BEWERTUNGSGRUNDLAGE:
- Verwende ausschliesslich die Aufgabenstellung und die Vorgaben in dieser Anweisung.
- Erfinde keine zusätzlichen Anforderungen.
- Bewerte nur Inhalte, die im eingereichten Dokument tatsächlich nachvollziehbar vorhanden sind.

BEWERTE DIE ABGABE ANHAND:
- Vollständigkeit: Wurden alle Anforderungen der Aufgabenstellung bearbeitet?
- Fachliche Qualität: Ist die Arbeit fundiert und zeigt sie Verständnis?
- Korrektheit: Sind die Aussagen sachlich richtig?
- Struktur und Klarheit: Ist die Arbeit nachvollziehbar aufgebaut und verständlich formuliert?

AUSGABEFORMAT:
PUNKTEEMPFEHLUNG: [Summe] von [Maximum]

BEGRÜNDUNG:
[sachliche Einschätzung mit Bezug zu den obigen Aspekten]

STÄRKEN:
[1–3 konkrete Stärken der Arbeit]

VERBESSERUNGSPOTENZIAL:
[1–3 konkrete, umsetzbare Hinweise]

HINWEISE:
- Formuliere sachlich, präzise und wertschätzend auf Hochschulniveau.
- Sprich die studierende Person direkt mit "Sie" an.
- Belege deine Einschätzung mit konkretem Bezug zur Abgabe.
- Kennzeichne Unsicherheiten transparent.',
        PARAM_TEXT
    ));

    $ADMIN->add('localplugins', $settings);
}

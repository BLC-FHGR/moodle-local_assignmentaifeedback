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
 * German language strings for local_aigrade (Hochschulkontext)
 *
 * @package    local_aigrade
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin- und Grundbegriffe.
$string['pluginname'] = 'KI-Bewertungsassistenz';
$string['aigrade'] = 'KI-Bewertung';
$string['aigrade_help'] = 'Aktiviert die KI-gestützte Bewertungsunterstützung für diese Aufgabe. Die KI liefert eine nicht bindende Punkteempfehlung und ein schriftliches Feedback; die finale Bewertung nehmen die Dozierenden vor.';

// Capability.
$string['aigrade:grade'] = 'KI zur Unterstützung der Bewertung von Abgaben verwenden';

// Warnhinweis im Aufgaben-Formular.
$string['aigrade_warning'] = 'Hinweis zur KI-Bewertung';
$string['aigrade_warning_text'] = '<strong>Wichtig:</strong> Prüfen Sie KI-generierte Empfehlungen und Feedback stets kritisch. Die KI bewertet ausschliesslich Textinhalte und kann Bilder, Formatierung, Layout oder visuelle Gestaltung nicht beurteilen. Die KI vergibt keine Note; die finale Bewertung liegt bei den Dozierenden.';

// Warnhinweis auf der globalen Einstellungsseite.
$string['settings_warning_html'] = '<div class="alert alert-warning" style="margin: 20px 0; padding: 15px; background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
    <h4 style="margin-top: 0; color: #856404;"><i class="fa fa-exclamation-triangle"></i> Wichtig: Leitlinien zur KI-Bewertung</h4>
    <p><strong>Dozierende müssen KI-generierte Punkteempfehlungen und Feedback stets auf Richtigkeit prüfen.</strong> Die KI unterstützt, entscheidet aber nicht.</p>
    <p><strong>Geeignet für:</strong> textbasierte Arbeiten wie Exposés, Studien- und Seminararbeiten, schriftliche Ausarbeitungen.</p>
    <p><strong>Grenzen:</strong> Die KI beurteilt nur den Textinhalt und kann Folgendes nicht bewerten:</p>
    <ul>
        <li>Bilder, Grafiken oder visuelle Elemente</li>
        <li>Formatierung, Farben oder Layout</li>
        <li>Tabellen, Diagramme oder Abbildungen (Struktur kann verloren gehen)</li>
    </ul>
    <p><strong>Datenschutz:</strong> Abgabetexte werden an den in Moodle konfigurierten KI-Anbieter übermittelt. Stellen Sie sicher, dass dies mit den Datenschutzvorgaben Ihrer Hochschule vereinbar ist und die Studierenden entsprechend informiert wurden.</p>
</div>';

// Name der KI-Assistenz (globale Einstellung).
$string['ai_name'] = 'Name der KI-Assistenz';
$string['ai_name_desc'] = 'Name der KI-Bewertungsassistenz, wie er auf den Schaltflächen erscheint (z. B. "KI", "KI-Assistenz"). Standard ist "KI".';

// KI-Backend-Auswahl.
$string['setting_ai_backend'] = 'KI-Backend';
$string['setting_ai_backend_desc'] = 'Legt fest, über welches System die Bewertungsanfragen laufen. <strong>Local AI Manager</strong> nutzt die im Plugin "local_ai_manager" konfigurierten Modelle (z. B. lokal gehostete Modelle). <strong>Moodle Core AI-Subsystem</strong> nutzt die unter „Website-Administration → KI" konfigurierten Anbieter.';
$string['backend_ai_manager'] = 'Local AI Manager (lokale Modelle)';
$string['backend_core'] = 'Moodle Core AI-Subsystem';
$string['setting_ai_manager_purpose'] = 'Local AI Manager: Verwendungszweck (Purpose)';
$string['setting_ai_manager_purpose_desc'] = 'Nur relevant, wenn als Backend „Local AI Manager" gewählt ist. Der AI Grader sendet den Prompt über diesen Verwendungszweck. Das dabei verwendete Modell wird im Local AI Manager pro Verwendungszweck und Rolle festgelegt – wählen Sie den Zweck, dem Sie Ihr gewünschtes (lokales) Modell zugewiesen haben. Empfehlung: „feedback".';
$string['error_ai_manager_missing'] = 'Das Plugin „local_ai_manager" ist nicht installiert oder nicht verfügbar. Installieren Sie es oder wählen Sie als Backend das Moodle Core AI-Subsystem.';

// Automatische Bewertung bei Abgabe.
$string['aigrade_auto_grade'] = 'Automatisch bei Abgabe bewerten';
$string['aigrade_auto_grade_help'] = 'Wenn aktiviert, erstellt die KI automatisch einen Feedback-Vorschlag, sobald eine studentische Abgabe eingeht. Die Verarbeitung läuft im Hintergrund (Moodle-Cron) und setzt keine Note – der Vorschlag erscheint nur im Panel. Voraussetzungen: ein laufender Cron und ein konfiguriertes Bewertungskonto (siehe Plugin-Einstellungen „Konto für automatische Bewertung").';
$string['setting_autograde_user'] = 'Konto für automatische Bewertung';
$string['setting_autograde_user_desc'] = 'Benutzername des Kontos, unter dem die automatische Bewertung im Hintergrund läuft. Dieses Konto muss den lokalen KI-Dienst nutzen dürfen (Capability local/ai_manager:use), die KI-Nutzung bestätigt haben und über ausreichend Kontingent verfügen. Leer lassen, um stattdessen automatisch eine bewertungsberechtigte Lehrperson des jeweiligen Kurses zu verwenden (weniger vorhersehbar).';
$string['setting_autograde_timeout'] = 'Zeitlimit für Hintergrund-Anfragen';
$string['setting_autograde_timeout_desc'] = 'Wie lange eine KI-Anfrage der automatischen Bewertung dauern darf. Das site-weite Zeitlimit des Local AI Manager („requesttimeout", Standard 60 Sekunden) ist auf interaktive Anfragen ausgelegt und für lange Raster-Prompts zu kurz – die Hintergrund-Bewertung bricht dann mit „Die API hat zu lange gebraucht" ab. Dieser Wert gilt nur für den Ad-hoc-Task und nur für das Backend „Local AI Manager"; die site-weite Einstellung bleibt unverändert. 0 = site-weites Zeitlimit verwenden.';
$string['task_autograde'] = 'KI-Bewertung im Hintergrund (AI Grader)';

// Instruktionsfelder pro Aufgabe.
$string['aigrade_instructions_with_rubric_field'] = 'KI-Anweisungen (mit Beurteilungsraster)';
$string['aigrade_instructions_with_rubric_field_help'] = 'Anweisungen an die KI, wenn ein Beurteilungsraster hochgeladen wurde. Diese Anweisungen steuern das Bewertungsverhalten vollständig – zusammen mit dem hochgeladenen Raster.';
$string['aigrade_instructions_without_rubric_field'] = 'KI-Anweisungen (ohne Beurteilungsraster)';
$string['aigrade_instructions_without_rubric_field_help'] = 'Anweisungen an die KI, wenn kein Raster hochgeladen wurde. Die KI bewertet dann anhand der Aufgabenstellung und dieser Anweisungen.';

// Globale Default-Instruktionen (Einstellungsseite).
$string['aigrade_instructions_with_rubric'] = 'Standard-Anweisungen (mit Raster)';
$string['aigrade_instructions_with_rubric_desc'] = 'Standardanweisungen für Aufgaben mit hochgeladenem Beurteilungsraster. Dieser Text wird in neue Aufgaben übernommen, wenn die KI-Bewertung aktiviert wird.';
$string['aigrade_instructions_without_rubric'] = 'Standard-Anweisungen (ohne Raster)';
$string['aigrade_instructions_without_rubric_desc'] = 'Standardanweisungen für Aufgaben ohne Beurteilungsraster. Die KI bewertet anhand der Aufgabenbeschreibung. Dieser Text wird in neue Aufgaben übernommen, wenn die KI-Bewertung aktiviert wird.';

// Raster-Upload.
$string['aigrade_rubric'] = 'Beurteilungsraster';
$string['aigrade_rubric_help'] = 'Laden Sie ein Beurteilungsraster (PDF, TXT, DOCX oder DOC) hoch, das die KI zur Bewertung der Abgaben heranzieht.';

// Schaltflächen.
$string['button_grade_single'] = 'Mit {$a} bewerten';
$string['button_grade_bulk'] = 'Alle mit {$a} bewerten';

// Prompt-Bausteine.
$string['assignment_instructions_label'] = 'Aufgabenstellung:';
$string['grading_rubric_label'] = 'Beurteilungsraster:';
$string['student_submission_label'] = 'Studentische Abgabe:';
$string['default_grading_criteria'] = 'Bewerten Sie diese Arbeit anhand üblicher akademischer Kriterien für Qualität, Vollständigkeit und Korrektheit.';
$string['prompt_framing_heading'] = 'RAHMENBEDINGUNGEN:';
$string['prompt_max_points'] = 'Maximal erreichbare Punktzahl: {$a}.';
$string['prompt_recommendation_only'] = 'Gib eine begründete Punkteempfehlung ab. Es wird keine abschliessende Note vergeben – die finale Bewertung nehmen die Dozierenden vor.';
$string['prompt_address_language'] = 'Antworte auf Deutsch, sachlich und auf Hochschulniveau, und sprich die studierende Person direkt mit "Sie" an.';

// Datei-/Extraktionshinweise.
$string['file_submission_separator'] = '--- Inhalt der Datei-Abgabe ---';
$string['file_label'] = 'Datei: {$a}';
$string['slide_label'] = 'Folie {$a}:';
$string['pdf_rubric_fallback'] = 'PDF-Raster: {$a}';
$string['unsupported_file_type'] = 'Dateityp wird für die KI-Bewertung nicht unterstützt: {$a}';
$string['extraction_unavailable'] = 'Textextraktion für diese Datei nicht verfügbar: {$a}';
$string['extraction_error'] = 'Fehler bei der Textextraktion: {$a}';

// Status- und Fehlermeldungen.
$string['no_ungraded'] = 'Keine offenen Abgaben gefunden, für die noch kein Feedback vorliegt.';
$string['already_has_feedback'] = 'Für diese Abgabe liegt bereits ein Feedback vor. Klicken Sie erneut, um es neu zu erstellen.';
$string['aigrade_disabled'] = 'Die KI-Bewertung ist für diese Aufgabe nicht aktiviert.';
$string['error_no_submission'] = 'Diese studierende Person hat noch keinen Text eingereicht. Laden Sie die Seite ggf. neu, falls eine Abgabe vorliegt.';
$string['error_no_feedback'] = 'Die KI hat kein Feedback zurückgegeben.';
$string['error_invalid_action'] = 'Ungültige Aktion.';
$string['ai_error'] = 'Fehler des KI-Dienstes: {$a}';
$string['rubric_parse_error'] = 'Fehler beim Auslesen des Rasters: {$a}';

// JavaScript-Texte (aus PHP übergeben).
$string['js_grading_single'] = 'Bewertung läuft …';
$string['js_regrade'] = 'Mit {$a} neu bewerten';
$string['js_confirm_regrade'] = 'Für diese Abgabe liegt bereits ein Feedback vor. Möchten Sie es mit KI neu erstellen lassen?';
$string['js_confirm_bulk'] = 'Alle noch nicht bewerteten Abgaben mit KI bewerten? Das kann einen Moment dauern.';
$string['js_grading_bulk'] = 'Alle Abgaben werden bewertet …';
$string['js_success_count'] = 'Erfolgreich {count} Abgabe(n) bewertet.';
$string['js_error_prefix'] = 'Fehler: ';
$string['js_error_unknown'] = 'Unbekannter Fehler aufgetreten.';
$string['js_error_server'] = 'Fehler bei der Kommunikation mit dem Server: ';
$string['js_panel_title'] = '{$a}-Feedback';
$string['js_panel_jump'] = 'Zum Feedback-Feld';
$string['js_panel_collapse'] = 'Ein-/ausklappen';
$string['js_panel_apply'] = 'Ins Feedback-Feld übernehmen';
$string['js_panel_apply_confirm'] = 'Das Feedback-Feld enthält bereits Text. Mit dem KI-Feedback überschreiben?';
$string['js_panel_apply_failed'] = 'Das Feedback-Feld konnte nicht gefunden werden. Bitte kopieren Sie den Text manuell.';

// Datenschutz (Privacy API).
$string['privacy:metadata:ai_provider'] = 'Zur Bewertungsanalyse werden Inhalte an den in Moodle konfigurierten KI-Anbieter übermittelt. Diese Daten werden vom externen KI-Dienst verarbeitet, aber von diesem Plugin nicht dauerhaft gespeichert.';
$string['privacy:metadata:ai_provider:submissiontext'] = 'Der Textinhalt der Abgabe, der zur Bewertung an den KI-Anbieter übermittelt wird.';
$string['privacy:metadata:ai_provider:assignmentname'] = 'Der Name der Aufgabe, der zur Kontextgebung an den KI-Anbieter übermittelt wird.';
$string['privacy:metadata:ai_provider:assignmentinstructions'] = 'Die Aufgabenstellung, die zur Bewertungsgrundlage an den KI-Anbieter übermittelt wird.';
$string['privacy:metadata:ai_provider:rubric'] = 'Das Beurteilungsraster (falls hochgeladen), das zur Steuerung der Bewertung an den KI-Anbieter übermittelt wird.';
$string['privacy:metadata:local_aigrade_feedback'] = 'Vom Plugin gespeicherte KI-Feedback-Vorschläge (nur als Hilfestellung, nicht die offizielle Rückmeldung).';
$string['privacy:metadata:local_aigrade_feedback:userid'] = 'Die ID der studierenden Person, zu deren Abgabe der Vorschlag gehört.';
$string['privacy:metadata:local_aigrade_feedback:feedback'] = 'Der von der KI erzeugte Feedback-Vorschlag.';
$string['privacy:metadata:local_aigrade_feedback:timecreated'] = 'Zeitpunkt der Erstellung des Vorschlags.';
$string['privacy:metadata:local_aigrade_feedback:timemodified'] = 'Zeitpunkt der letzten Aktualisierung des Vorschlags.';
$string['privacy:aifeedbackpath'] = 'KI-Feedback-Vorschlag';

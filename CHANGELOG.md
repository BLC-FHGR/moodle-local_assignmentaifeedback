# Changelog

Änderungen an der FHGR-Fassung von `local_assignmentaifeedback` (bis v2.6.0-fhgr:
`local_aigrade`). Einträge vor v3.0.0-fhgr nennen weiterhin die damaligen Namen.

## v3.1.1-fhgr — 2026-09-29
**Keine manuellen Schaltflächen mehr, wenn „Automatisch bei Abgabe bewerten" aktiv ist.**
Der Observer reiht dann jede Abgabe selbst ein; beide Schaltflächen überspringen Abgaben,
für die bereits ein Vorschlag vorliegt, und hätten damit nichts mehr auszulösen.
- Sammelbewertung: Das AMD-Modul wird gar nicht mehr geladen, es fügt ausschliesslich die
  Schaltfläche ein.
- Einzelbewertung: Das Modul wird weiterhin geladen, `init()` erhält neu den Parameter
  `showButton`. Das **Vorschlags-Panel muss bleiben** – es zeigt das Ergebnis des
  automatischen Laufs und enthält „Ins Feedback-Feld übernehmen"; nur die Schaltfläche
  entfällt.
- Ausserdem: Maturity von `MATURITY_BETA` auf `MATURITY_STABLE`.

## v3.1.0-fhgr — 2026-09-28
**Neue Capability `local/assignmentaifeedback:configure`.** Die KI-Einstellungen einer
Aufgabe (Aktivieren, automatische Bewertung, beide Prompts, Beurteilungsraster) sind jetzt
von der Nutzung getrennt: `:grade` bleibt wie bisher bei Dozierenden, `:configure` ist
standardmässig **nur** Manager/innen erlaubt und wird dort vergeben, wo das Plugin
freigegeben ist – z. B. pro Kursbereich.
- Der Abschnitt „KI-Bewertung" im Aufgaben-Formular war bisher **ungeschützt**: Er wurde
  allen Personen angezeigt, die eine Aufgabe bearbeiten dürfen, unabhängig von `:grade`.
  `local_assignmentaifeedback_coursemodule_standard_elements()` und
  `..._definition_after_data()` prüfen jetzt `:configure`.
- **Wichtig für den Speicherpfad:** `..._coursemodule_edit_post_actions()` hat den
  Konfigurationsdatensatz bedingungslos geschrieben. Ohne den Formularabschnitt werden
  dessen Felder nicht mitgeschickt – jedes Speichern des Aufgaben-Formulars durch eine
  Person ohne `:configure` hätte die KI stillschweigend deaktiviert (`enabled = 0`) und
  die Anweisungen gelöscht. Die Funktion bricht nun vor jedem Schreibzugriff ab und lässt
  die gespeicherte Konfiguration unberührt.
- Vergabe pro Kursbereich: *Kursbereich → Rechte ändern* →
  `local/assignmentaifeedback:configure` für die gewünschte Rolle erlauben. Die Vererbung
  gilt für alle Kurse und Unterbereiche.

## v3.0.0-fhgr — 2026-09-14
**Umbenennung:** Das Plugin heisst neu `local_assignmentaifeedback` und liegt in
`moodle/local/assignmentaifeedback` (vormals `local_aigrade` in `moodle/local/aigrade`).
Der alte Name stammte vom US-Ursprungsplugin „AI Grade" und beschrieb das Falsche: Das
Plugin vergibt keine Note, sondern erzeugt einen Feedback-Vorschlag zu einer Abgabe.
- Umbenannt wurden Komponente, Verzeichnis, Namensräume, Sprachdateien, AMD-Module,
  CSS-Klassen, Capability (`local/assignmentaifeedback:grade`), Webservice-Funktionen,
  Backup-/Restore-Klassen und die Tabellen `local_assignmentaifeedback_config` und
  `local_assignmentaifeedback_feedback`.
- **Datenübernahme:** Moodle betrachtet die umbenannte Komponente als neues Plugin und legt
  leere Tabellen an. `db/install.php` übernimmt deshalb einmalig beim Installieren die
  Aufgaben-Konfiguration, die gespeicherten Vorschläge, die Plugin-Einstellungen, die
  hochgeladenen Beurteilungsraster (inklusive neu berechnetem `pathnamehash`, der die
  Komponente enthält), die Capability-Overrides und noch eingereihte Ad-hoc-Tasks aus
  `local_aigrade`. Bereits vorhandene Datensätze werden übersprungen, ein wiederholter
  Lauf erzeugt keine Duplikate.
- Die alten Tabellen bleiben stehen. `local_aigrade` erst **nach** geprüfter Übernahme
  deinstallieren – siehe „Umstieg von `local_aigrade`" in der [README](README.md).
- **`db/upgrade.php` wurde geleert.** Die Upgrade-Schritte von 2025012001 bis 2026090100
  gehören zu `local_aigrade`; unter der neuen Komponente beginnt jede Installation bei
  `db/install.xml` mit dem aktuellen Schema, und die Schritte könnten nie laufen. Die
  Historie steht in Git und in der letzten `local_aigrade`-Fassung.
- **Anzeigename unverändert:** „KI-Bewertungsassistenz".

## v2.6.0-fhgr — 2026-09-01
**Umbau:** Alle KI-Anfragen laufen jetzt im Hintergrund (Ad-hoc-Task) – auch die über die
Bewertungs-Buttons ausgelösten. Damit ist das Zeitlimit des Webservers für die Bewertung
kein Thema mehr, und fehlgeschlagene Läufe werden automatisch wiederholt.
- **Fehlgeschlagene Läufe wurden stillschweigend verworfen.** Der Task hat den Fehler nur
  per `mtrace` protokolliert und ist normal beendet worden – Moodle wertete ihn als
  erfolgreich (`result=0`) und entfernte ihn aus der Warteschlange. Eine vorübergehend
  nicht erreichbare KI bedeutete: die Abgabe wird nie bewertet, ohne Wiederholung und ohne
  Hinweis. Der Grader kennzeichnet Dienstfehler nun mit `retryable`, der Task wirft in dem
  Fall und wird von Moodle mit der üblichen Backoff-Staffelung wiederholt.
- **Einzelbewertung** reiht den Lauf ein und antwortet sofort; das Panel pollt (alle 3 s,
  maximal 15 Minuten) auf den neuen Vorschlag. Ein manueller Lauf läuft unter dem Konto der
  Lehrperson, die geklickt hat, und ignoriert das Aufgaben-Flag „Automatisch bei Abgabe
  bewerten".
- **Sammelbewertung** reiht einen Task pro offener Abgabe ein statt alle Abgaben in einem
  Request zu bewerten. Ein einzelner KI-Fehler reisst damit nicht mehr den ganzen Lauf mit.
- Die **Webservice-Funktionen** reihen ebenfalls nur noch ein, damit beide Eingänge
  dasselbe Ausführungsmodell haben.
- Die in v2.5.1 eingeführte Einstellung **„Zeitlimit für interaktive Anfragen"**
  (`manual_timeout`) ist damit gegenstandslos und wurde entfernt; ein Upgrade-Schritt löscht
  den gespeicherten Wert. Maßgeblich ist nur noch **„Zeitlimit für Hintergrund-Anfragen"**.
- **Voraussetzung:** Ein laufender Cron ist jetzt für *jede* KI-Bewertung nötig.

## v2.5.2-fhgr — 2026-08-31
**Fixes** aus einem Review des Bewertungspfads.
- **Webservice-Klassen brachen beim Autoloading ab.** `classes/external/grade_single.php`
  und `grade_bulk.php` lasen auf Dateiebene `$CFG`, ohne es zu importieren.
  Klassendateien werden von `core_component::classloader()` aus einer Methode heraus
  eingebunden – der Dateikontext ist dort nicht global, die `require_once`-Pfade fielen
  auf `/externallib.php` zusammen (`Failed opening required`). Beide registrierten
  Funktionen waren damit nicht aufrufbar; behoben durch `global $CFG;`.
- **`local_aigrade_grade_bulk` lieferte eine ungültige Antwortstruktur.** `count` ist als
  Pflichtfeld deklariert, fehlte aber in beiden Fehlerpfaden von `grade_submissions()`
  (keine unbewerteten Abgaben, abgefangene Exception). `count` wird jetzt immer
  zurückgegeben (0 im Fehlerfall).
- **`settings.php` hing von einer neu hinzugekommenen Klasse ab.** Die Standardwerte kamen
  aus den Konstanten von `request_timeout`. Da ein Plugin-Versionssprung das gecachte
  Classmap nicht invalidiert und Admin-Seiten ausser `admin/index.php` kein
  `CACHE_DISABLE_ALL` setzen, lief jede Admin-Seite im Zeitfenster zwischen Deployment
  und Upgrade in einen Fatal Error. Die Werte stehen dort jetzt als Literale.
- **Webservice-Pfade liefen weiter im 60-Sekunden-Zeitlimit.** Die Anhebung aus v2.5.1 lag
  nur in den AJAX-Endpunkten; beide `execute()`-Methoden nutzen jetzt ebenfalls
  `request_timeout::for_manual()`. `grade_single` kennt zusätzlich den Parameter
  `forceregrade`, mit dem eine Neubewertung über den Webservice überhaupt erst möglich ist.

## v2.5.1-fhgr — 2026-08-31
**Fix:** Die **manuelle** Bewertung brach nach exakt 60 Sekunden ab mit „AI service error:
The API took too long to process your request…" – derselbe Fehler, der in v2.4.2 nur für die
automatische Bewertung behoben wurde.
- Ursache: `local_ai_manager` baut seinen HTTP-Client bei **jeder** Anfrage mit dem site-weiten
  Zeitlimit `requesttimeout` (Standard 60 s). Die Anhebung in v2.4.2 lag als private Methode
  im Ad-hoc-Task und galt daher nur für den Hintergrund-Lauf; `grade_single.php` und
  `grade.php` liefen weiter im 60-Sekunden-Limit. Gemessene manuelle Läufe brauchten
  35–60 s, lagen also unmittelbar an der Grenze.
- Die Anhebung liegt jetzt in `\local_aigrade\local\request_timeout` und wird von beiden
  Pfaden genutzt (`for_manual()` bzw. `for_background()`); der Task verhält sich unverändert.
- Neue Einstellung **„Zeitlimit für interaktive Anfragen"** (`manual_timeout`, Standard
  **4 Minuten**, 0 = site-weites Zeitlimit verwenden). Der Standard liegt bewusst unter dem
  Apache-Standard `Timeout 300`, damit im Fehlerfall die lesbare Plugin-Meldung erscheint
  statt eines Gateway-Fehlers.
- Das Zeitlimit gilt nur für den jeweiligen Prozess (`$CFG->forced_plugin_settings`) und wird
  danach wiederhergestellt; die site-weite Einstellung bleibt unverändert.
- **Bekannte Einschränkung:** Die Sammelbewertung verarbeitet alle Abgaben nacheinander in
  einer einzigen Anfrage. Das angehobene Limit gilt pro KI-Aufruf, das Webserver-Zeitlimit
  begrenzt aber den Gesamtlauf – bei vielen Abgaben ist die automatische Bewertung bei Abgabe
  der robustere Weg. Ein Umbau der Sammelbewertung auf einen Task pro Abgabe steht aus.

## v2.5.0-fhgr — 2026-08-31
**Restrukturierung:** Das Repository ist kein Monorepo mehr, sondern enthält nur noch
das Plugin `local_aigrade`.
- Der mitgelieferte Ordner `local_ai_manager/` wurde **entfernt**. Das Plugin wird
  separat installiert: <https://github.com/bycs-lp/moodle-local_ai_manager>.
- Der Plugin-Inhalt liegt jetzt im **Repository-Wurzelverzeichnis** (`version.php` auf
  oberster Ebene). Damit lässt sich das Repository direkt nach `moodle/local/aigrade`
  klonen; das bisherige Kopieren aus dem Unterordner entfällt.
- `version.php` deklariert jetzt eine **harte Abhängigkeit** auf `local_ai_manager`
  (`$plugin->dependencies`). Das umschaltbare Backend (Local AI Manager ⇄ Moodle-Core-AI)
  bleibt unverändert erhalten.
- Der FHGR-Patch am `local_ai_manager` (frei eingebbares Modellfeld) ist als
  Dokumentation unter `docs/local_ai_manager-patch.md` erhalten und muss bei Bedarf
  auf die separate Installation angewendet werden.
- Doku zusammengeführt: `README.md`, `ANPASSUNGEN-FHGR.md`, `CHANGELOG.md` und
  `CHANGELOG-upstream.md` (vormals `local_aigrade/CHANGES.md`) liegen im Wurzelverzeichnis.

## v2.4.2-fhgr — 2026-08-31
**Fix:** Die automatische Bewertung schlug immer nach exakt 60 Sekunden fehl mit
„AI service error: The API took too long to process your request or could not be
reached in a reasonable time" – es wurde nie ein Feedback-Vorschlag gespeichert.
- Ursache: `local_ai_manager` verwendet für jede Anfrage das site-weite Zeitlimit
  `requesttimeout` (Standard 60 s). Das ist für interaktive Anfragen gedacht und für
  die langen Raster-Prompts im Hintergrund zu kurz.
- Neue Einstellung **„Zeitlimit für Hintergrund-Anfragen"** (`autograde_timeout`,
  Standard 5 Minuten, 0 = site-weites Zeitlimit verwenden).
- Der Ad-hoc-Task hebt das Zeitlimit nur für seinen eigenen Prozess an
  (`$CFG->forced_plugin_settings`) und stellt es danach wieder her; die site-weite
  Einstellung und interaktive Anfragen bleiben unverändert. Das PHP-Zeitlimit wird
  passend mit angehoben.

## v2.4.1-fhgr — 2026-08-27
**Fix:** Ad-hoc-Task schlug fehl mit „Call to undefined function
local_aigrade\task\cron_setup_user()". In Moodle 4.4+ existiert die globale Funktion
`cron_setup_user()` nicht mehr; der Task nutzt jetzt `\core\cron::setup_user()`
(mit Fallback auf die alte globale Funktion für ältere Versionen).

## v2.4.0-fhgr — 2026-08-27 (Idee A)
**Feature:** Automatische KI-Bewertung bei Abgabe (opt-in, asynchron).
- Neue Checkbox „Automatisch bei Abgabe bewerten" pro Aufgabe (`auto_grade`).
- Event-Observer auf `\mod_assign\event\assessable_submitted` stellt einen **Ad-hoc-Task**
  in die Cron-Warteschlange (der KI-Aufruf läuft **nie** im Submit-Request des Studierenden).
- Ad-hoc-Task `local_aigrade\task\autograde_task` erzeugt den Feedback-Vorschlag im
  Hintergrund und speichert ihn in `local_aigrade_feedback` (keine Note, nur Panel).
- Neue Einstellung **„Konto für automatische Bewertung"** (`autograde_user`): Dienstkonto,
  unter dem der KI-Aufruf läuft (braucht ai_manager-Rechte, bestätigte Nutzung, Kontingent);
  leer = Fallback auf eine bewertungsberechtigte Lehrperson des Kurses.
- Voraussetzungen: laufender Moodle-Cron; bei Deadlines Modell-/Cron-Kapazität beachten.
- DB: Feld `auto_grade` via `install.xml` + Upgrade (2026082007); Backup/Restore ergänzt.

## v2.3.2-fhgr — 2026-08-27
**Fix:** „Ins Feedback-Feld übernehmen" funktionierte nicht.
- Ursache: Moodle 4.x nutzt TinyMCE 6 mit globalem Zugriff `window.tinymce` (klein);
  bisher wurde nur `window.tinyMCE` geprüft.
- Robuster Editor-Zugriff (`window.tinymce || window.tinyMCE`, Auflösung über die
  Textarea-ID via `tinymce.get(id)`), Fallbacks für Atto und reines Textarea inkl.
  `input`/`change`-Event; Hinweismeldung, wenn kein Feld gefunden wird.

## v2.3.1-fhgr — 2026-08-27
**Fix:** Moodle „hing" in anderen Tabs, während eine Bewertung lief.
- Ursache: PHP-Session-Sperre wurde für die Dauer des (synchronen) KI-Requests gehalten.
- `grade.php` und `grade_single.php` geben die Sperre nach den Zugriffsprüfungen und vor
  dem KI-Aufruf via `\core\session\manager::write_close()` frei.

## v2.3.0-fhgr — 2026-08-27 (Idee B)
**Feature:** KI-Feedback nur noch im Panel, nicht mehr im Kommentarfeld.
- KI-Feedback ist ein unverbindlicher Vorschlag und wird in einer eigenen Tabelle
  `local_aigrade_feedback` gespeichert (nicht als offizielle Rückmeldung) — verhindert
  versehentliches Mitspeichern.
- Panel lädt das Feedback pro Abgabe (AJAX-Endpunkt `get_feedback.php`) und schaltet beim
  Wechsel zwischen Abgaben korrekt um (bzw. bleibt leer).
- Neuer Button „Ins Feedback-Feld übernehmen" (mit Überschreib-Nachfrage).
- „Bereits bewertet"-Erkennung basiert nun auf der neuen Tabelle.
- Datenschutz: Privacy-Provider erweitert (Metadaten, Export/Löschung, userlist).
- DB: neue Tabelle via `install.xml` + Upgrade-Schritt (2026082004).

---

## Bereits im ersten Push enthalten (Basis)

### local_aigrade v2.2.1-fhgr und früher
- Hochschul-Anpassung: Deutsch als primäre Sprache, kriterienbezogen, **keine automatische
  Note** (nur Punkteempfehlung + Feedback), Entfernung des US-K-12-Gerüsts (Grade-Level,
  Strictness, Lexile).
- Umschaltbares **KI-Backend** (Local AI Manager ⇄ Moodle-Core-AI) inkl. Purpose-Auswahl.
- **Feedback-Seitenpanel** rechts neben der Rubrik.
- Datenschutz-Bereinigung: kein Debug-Logging von Abgaben, kein externer Google-Docs-Abruf.

# Anpassungen für den Hochschulkontext (FH Graubünden)

Basis: `local_aigrade` v1.7.2 (Brian A. Pool, National Trail Local Schools, GPLv3).
Angepasste Version: **v2.1.0-fhgr** (`$plugin->version = 2026082001`).

## Ziel
Umbau vom US-K-12-Werkzeug zu einer schlanken **Bewertungsunterstützung für Dozierende**
im Hochschulkontext. Leitprinzip: Das Plugin liefert Gerüst und Datenfluss – **Prompt und
Beurteilungsraster steuern den Inhalt vollständig**. Kein hartcodierter didaktischer oder
kultureller Kontext mehr.

## Wesentliche Änderungen

1. **US-Gerüst aus dem Prompt entfernt** (`classes/grader.php`)
   - Weg: „Ohio public school", „Grade X student", Lexile-Lesestufen, erzwungene
     Feedback-Leseniveaus, die „MOST IMPORTANT INSTRUCTION"-Strenge-Injektion.
   - Neu: `build_prompt()` fügt nur noch neutral zusammen:
     *Dozierenden-Anweisungen + Raster/Aufgabenbeschreibung + Abgabe + Rahmenhinweis*
     (max. Punkte, „keine Note – nur Empfehlung", Sprache Deutsch/„Sie").

2. **„Student grade level" und „Grading strictness" komplett entfernt**
   - Aus Formular (`lib.php`), DB-Schema (`db/install.xml`), Backup/Restore und Prompt.
   - `db/upgrade.php`: neuer Schritt `2026082000` entfernt die Spalten `grade_level`
     und `grading_strictness` bei bestehenden Installationen (mit `field_exists`-Check).

3. **Keine Note in den Gradebook** (`classes/grader.php`)
   - `save_grade()` → `save_feedback()`: schreibt die Punkteempfehlung + Begründung als
     **Feedback-Kommentar**. `update_grade()` entfällt vollständig; die numerische Note
     bleibt leer und wird von den Dozierenden gesetzt.
   - „Bereits bearbeitet?"-Erkennung basiert nun auf dem **Vorhandensein eines
     Feedback-Kommentars** (`has_ai_feedback()`), nicht mehr auf der Note. Dadurch werden
     bestehende (auch manuell verfasste) Kommentare nicht überschrieben, und die
     Sammelbewertung verarbeitet nur noch offene Abgaben.

4. **Deutsch als primäre Sprache**
   - Neue `lang/de/local_aigrade.php` (primär), bereinigte `lang/en/local_aigrade.php`
     (Fallback, ohne Lexile-/Strictness-/Grade-Level-Strings).
   - Hochschultaugliche deutsche Default-Prompts in `settings.php`.
   - Sichtbare JS-Texte (Buttons, Dialoge) werden lokalisiert aus PHP übergeben
     (`lib.php` → `amd/src` + `amd/build`).

5. **Datenschutz**
   - `debug_log_prompt()` **entfernt** – schrieb komplette Abgaben im Klartext nach `/tmp`.
   - Externer **Google-Docs-Abruf entfernt** (`extract_google_docs_text`/`fetch_google_doc`).
   - Privacy-API bereinigt (`gradelevel`-Metadatum entfernt), Hinweise präzisiert.

## Nachträglich ergänzt (v2.1.0): Anbindung an local_ai_manager

Der AI Grader kann die Modelle des Plugins **`local_ai_manager`** nutzen statt des
Moodle-Core-AI-Subsystems (dieses konnte lokale Modelle nicht einbinden).

- **Umschaltbares Backend** (globale Einstellung `ai_backend`):
  - `ai_manager` (Standard): Anfrage läuft über `\local_ai_manager\manager`.
  - `core`: bisheriges Moodle-Core-AI-Subsystem (`\core_ai\...`).
- **Purpose-Auswahl** (`ai_manager_purpose`, Standard `feedback`): Der Prompt wird über
  den gewählten Verwendungszweck gesendet. Das Modell wird im `local_ai_manager` pro
  Purpose und Rolle konfiguriert – daher genügt es, den Grader auf den Purpose zu zeigen,
  dem das gewünschte (lokale) Modell zugewiesen ist.
- **Keine Änderung am `local_ai_manager`** und **keine harte Abhängigkeit**: Der Aufruf
  ist über `class_exists('\local_ai_manager\manager')` abgesichert; ist das Plugin nicht
  vorhanden, erscheint ein klarer Hinweis bzw. es kann auf das Core-Backend gewechselt werden.
- Umgesetzt in `classes/grader.php` (`call_ai_service()` → `call_ai_manager()` /
  `call_core_ai()`), `settings.php` (zwei neue Einstellungen), `lang/de` + `lang/en`.

### Voraussetzungen im local_ai_manager (Admin-Seite, einmalig)
1. Ein **Tool/Modell** (z. B. lokales Modell) einrichten und dem gewünschten **Purpose**
   (Empfehlung: `feedback`) für die Rolle der Dozierenden zuweisen; Tenant aktivieren.
2. Dozierende benötigen die Capability **`local/ai_manager:use`** im jeweiligen Kontext.
3. Dozierende müssen die **KI-Nutzung einmalig bestätigt** haben (Confirm-Flow des
   local_ai_manager). Andernfalls liefert der Grader eine entsprechende Fehlermeldung.

### Test
- Backend auf „Local AI Manager" lassen, Purpose auf den mit dem lokalen Modell belegten
  Zweck setzen, eine Testabgabe bewerten → Feedback erscheint, Log-Eintrag entsteht im
  `local_ai_manager`. Bei Fehlkonfiguration erscheint eine klare Fehlermeldung
  (kein Tool zugewiesen / keine Berechtigung / nicht bestätigt).

## Nachträglich ergänzt (v2.2.0): Feedback-Seitenpanel (Benutzerfreundlichkeit)

Beim Einzel-Bewerten erscheint das KI-Feedback jetzt zusätzlich in einem **fixierten,
scrollbaren Panel rechts**, das beim Ausfüllen der Rubrik sichtbar bleibt. Das native
Feedback-Feld unten bleibt unverändert (dort wird gespeichert/bearbeitet); das Panel ist
eine Sicht-Kopie.

- Rein clientseitig (JS/CSS), kein Core-/ai_manager-Eingriff.
- Der AJAX-Endpunkt gibt den Feedback-Text jetzt mit zurück (`grader.php` →
  `grade_single_submission`, plus Rückgabeschema in `classes/external/grade_single.php`).
- `amd/src/grade_single.js` rendert das Panel: nach erfolgreicher Bewertung wird der Text
  in `sessionStorage` (pro userid) abgelegt und nach dem Reload angezeigt; Fallback: Lesen
  aus dem Feedback-Textarea beim Laden. Panel ist ein-/ausklappbar und hat einen
  „Zum Feedback-Feld"-Link.
- Styling in `styles.css` (`.aigrade-feedback-panel`), responsiv (auf schmalen Screens
  Vollhöhe). Build-Datei `amd/build/grade_single.min.js` mitaktualisiert.
- Neue Sprach-Strings: `js_panel_title`, `js_panel_jump`, `js_panel_collapse` (de/en).

**Korrektur (v2.2.1):** Das Panel spiegelt jetzt **live das Kommentarfeld der aktuell
angezeigten Abgabe** statt aus `sessionStorage` gefüllt zu werden. Beim Wechsel zwischen
Abgaben zeigt es dadurch immer den zur Abgabe gehörenden Kommentar – ist das Kommentarfeld
leer, ist auch das Panel leer (kein Rest-Feedback der vorherigen Abgabe mehr). Quelle der
Wahrheit ist der TinyMCE-Editor bzw. das Textarea des Feedback-Kommentars; während aktiver
Bearbeitung (Feld hat Fokus) wird das Panel nicht angetastet.

## Betroffene Dateien
`classes/grader.php`, `lib.php`, `settings.php`, `db/install.xml`, `db/upgrade.php`,
`classes/privacy/provider.php`, `version.php`, `lang/de/…` (neu), `lang/en/…`,
`amd/src/grade_single.js`, `amd/src/grade_bulk.js`, `amd/build/*.min.js`,
`backup/moodle2/backup_*`, `backup/moodle2/restore_*`, `README.md`.

## Installation / Update
1. Alte Version ggf. deinstallieren ist **nicht** nötig – der Upgrade-Pfad greift.
   ZIP über *Website-Administration → Plugins → Plugins installieren* hochladen
   (oder Ordner `aigrade` nach `local/` kopieren) und *Benachrichtigungen* aufrufen.
2. Beim Update wird DB-Version `2026082000` ausgeführt (entfernt die zwei Spalten).
3. Danach unter *Lokale Plugins → KI-Bewertungsassistenz* die deutschen Default-Prompts
   prüfen/anpassen.

## Testschritte (empfohlen)
1. **Installation/Upgrade** ohne Fehler; Einstellungsseite zeigt deutsche Texte.
2. **Aufgabe** anlegen, KI-Bewertung aktivieren, Raster (PDF/DOCX) hochladen.
   Kontrollieren: Grade-Level- und Strictness-Felder sind **weg**.
3. Testabgabe (Online-Text und/oder Datei) einreichen.
4. **Einzelbewertung**: Button „Mit KI bewerten" → Feedback-Kommentar erscheint mit
   Punkteempfehlung; **Notenfeld bleibt leer**. Erneuter Klick bietet „neu bewerten" an.
5. **Sammelbewertung**: „Alle mit KI bewerten" verarbeitet nur Abgaben ohne Feedback;
   bereits kommentierte werden übersprungen.
6. **Sprache**: mit Nutzersprache Englisch prüfen, dass der Fallback greift.
7. **Datenschutz**: prüfen, dass unter `/tmp` **kein** `aigrade_debug` mehr entsteht.

## Hinweise / bewusste Entscheidungen
- Der konfigurierte **KI-Anbieter und dessen Datenstandort** ist eine organisatorische
  Entscheidung der Hochschule (revDSG), keine Plugin-Einstellung. Studierende informieren.
- Das Format der Punkteempfehlung wird durch die **Instruktionsfelder/das Raster** bestimmt,
  nicht mehr durch hartcodierte Vorgaben.

# Changelog

Änderungen an der FHGR-Fassung von `local_aigrade`.

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

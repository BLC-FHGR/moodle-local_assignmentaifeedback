# Changelog

Änderungen an der FHGR-Fassung. Versionsangaben beziehen sich auf `local_aigrade`
(sofern nicht anders vermerkt).

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

### local_ai_manager 4.0-fhgr
- Patch: **frei eingebbares Modellfeld** (Textfeld statt Dropdown), damit eigene
  Modellnamen wie `gemma4-31b-reasoning` nutzbar sind; `supported_purposes()` verträgt
  frei eingegebene Modelle.

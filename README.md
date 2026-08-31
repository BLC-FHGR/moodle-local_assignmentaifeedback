# KI-Bewertungsassistenz (local_aigrade) – Hochschulanpassung

Moodle-Local-Plugin, das die **Bewertung schriftlicher Hochschularbeiten** (Exposés, Studien- und Seminararbeiten) durch Dozierende unterstützt. Die KI liefert eine **nicht bindende, kriterienbezogene Punkteempfehlung mit Begründung**. Sie vergibt **keine Note** – die finale Bewertung nehmen die Dozierenden vor.

> Diese Version ist eine Anpassung des Plugins „AI Grade" von Brian A. Pool (National Trail Local Schools) an den Hochschulkontext (Deutsch, kriterienbezogen, ohne US-K-12-Spezifika). Siehe `ANPASSUNGEN-FHGR.md`.

## Funktionen

- **Bewertungsunterstützung** über das Moodle-Core-AI-Subsystem
- **Abgabetypen**: Online-Text sowie Datei-Uploads (PDF, DOCX, DOC, TXT, PPTX, PPT, ODT)
- **Beurteilungsraster**: Upload eines Rasters (PDF, DOCX, DOC, TXT), das die Bewertung steuert
- **Zwei Instruktionsfelder**: getrennte KI-Anweisungen mit bzw. ohne Raster – diese steuern das Verhalten der KI vollständig
- **Punkteempfehlung statt Note**: Die Empfehlung wird als Feedback-Kommentar gespeichert; es wird **keine** Zahl in den Gradebook geschrieben
- **Einzel- und Sammelbewertung**: eine Abgabe oder alle noch nicht kommentierten Abgaben
- **Volle Kontrolle der Dozierenden** über alle KI-Ausgaben

## Anforderungen

- Moodle 4.5 oder neuer
- Moodle-AI-Subsystem mit konfiguriertem KI-Anbieter
- Für PDF-Raster/-Abgaben: `pdftotext` (empfohlen)
- PHP-ZipArchive-Erweiterung (für DOCX, PPTX, ODT)

## Installation

1. ZIP über **Website-Administration → Plugins → Plugins installieren** hochladen, oder
2. Ordner `aigrade` nach `/pfad/zu/moodle/local/` kopieren und **Website-Administration → Benachrichtigungen** aufrufen.

## Konfiguration

### Globale Einstellungen
**Website-Administration → Plugins → Lokale Plugins → KI-Bewertungsassistenz**
- **Name der KI-Assistenz** (Standard: „KI")
- **Standard-Anweisungen (mit Raster)** und **(ohne Raster)** – werden in neue Aufgaben übernommen

### KI-Backend wählen
In den globalen Einstellungen lässt sich das Backend wählen:
- **Local AI Manager (Standard):** nutzt die Modelle des Plugins `local_ai_manager` – ideal für lokal gehostete Modelle. Zusätzlich den **Purpose** wählen (Standard `feedback`); das Modell wird im `local_ai_manager` pro Purpose/Rolle festgelegt. Voraussetzungen: Tool/Modell einem Purpose zugewiesen, Dozierende mit Capability `local/ai_manager:use` und einmalig bestätigter KI-Nutzung.
- **Moodle Core AI-Subsystem:** nutzt die unter **Website-Administration → KI → KI-Anbieter** eingerichteten Anbieter.

Beachten Sie die Datenschutzvorgaben Ihrer Hochschule (siehe unten).

## Verwendung

### Aktivieren pro Aufgabe
1. Aufgabe erstellen/bearbeiten → Abschnitt **KI-Bewertung**
2. **KI-Bewertung** aktivieren
3. Optional die KI-Anweisungen für diese Aufgabe anpassen
4. Optional ein Beurteilungsraster hochladen
5. Speichern

### Einzelbewertung
Abgabe öffnen → Schaltfläche **„Mit KI bewerten"**. Die Empfehlung erscheint im Feedback-Kommentar. Prüfen, anpassen und die Note selbst setzen.

### Sammelbewertung
In der Abgabenübersicht **„Alle mit KI bewerten"**. Es werden nur Abgaben verarbeitet, für die noch **kein** Feedback vorliegt (bestehende Kommentare werden nicht überschrieben).

## Funktionsweise

1. Die KI liest den eingereichten Text.
2. Sie berücksichtigt Raster bzw. Aufgabenbeschreibung und die Dozierenden-Anweisungen.
3. Sie erstellt eine begründete Punkteempfehlung samt Feedback.
4. Die Empfehlung wird als Feedback-Kommentar gespeichert – **ohne** Note.
5. Die Dozierenden prüfen und entscheiden final.

## Datenschutz

- Abgabetexte werden an den in Moodle konfigurierten KI-Anbieter übermittelt. Klären Sie die Vereinbarkeit mit den Datenschutzvorgaben Ihrer Hochschule (in der Schweiz insb. revDSG) und informieren Sie die Studierenden.
- Das Plugin speichert keine personenbezogenen Daten über die Standard-Bewertung von Moodle hinaus und implementiert die Moodle-Privacy-API.
- Diese Version enthält **kein** Debug-Logging von Abgaben und **keinen** externen Google-Docs-Abruf (in der Ursprungsversion vorhanden, aus Datenschutzgründen entfernt).

## Grenzen

- Nur **Textinhalte** werden bewertet; Bilder, Formatierung, Layout, Tabellen/Diagramme und Folien-Design können nicht beurteilt werden.
- Gut geeignet für textlastige Arbeiten; nicht geeignet, wenn visuelle Gestaltung Bewertungskriterium ist.

## Credits & Lizenz

- **Ursprünglicher Autor**: Brian A. Pool, National Trail Local Schools (© 2025/2026)
- **Hochschulanpassung**: FH Graubünden (© 2026)
- **Lizenz**: GNU GPL v3 oder später

Dieses Programm ist freie Software gemäss GNU General Public License v3 (oder später). Es wird ohne jede Gewährleistung bereitgestellt. Siehe <https://www.gnu.org/licenses/>.

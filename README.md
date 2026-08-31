# KI-Bewertungsassistenz (local_aigrade) – Hochschulanpassung

Moodle-Local-Plugin, das die **Bewertung schriftlicher Hochschularbeiten** (Exposés, Studien- und Seminararbeiten) durch Dozierende unterstützt. Die KI liefert eine **nicht bindende, kriterienbezogene Punkteempfehlung mit Begründung**. Sie vergibt **keine Note** – die finale Bewertung nehmen die Dozierenden vor.

> Diese Version ist eine Anpassung des Plugins „AI Grade" von Brian A. Pool (National Trail Local Schools) an den Hochschulkontext (Deutsch, kriterienbezogen, ohne US-K-12-Spezifika). Siehe [`ANPASSUNGEN-FHGR.md`](ANPASSUNGEN-FHGR.md).

## Funktionen

- **Bewertungsunterstützung** über [`local_ai_manager`](https://github.com/bycs-lp/moodle-local_ai_manager) (lokale Modelle) oder wahlweise das Moodle-Core-AI-Subsystem
- **Abgabetypen**: Online-Text sowie Datei-Uploads (PDF, DOCX, DOC, TXT, PPTX, PPT, ODT)
- **Beurteilungsraster**: Upload eines Rasters (PDF, DOCX, DOC, TXT), das die Bewertung steuert
- **Zwei Instruktionsfelder**: getrennte KI-Anweisungen mit bzw. ohne Raster – diese steuern das Verhalten der KI vollständig
- **Punkteempfehlung statt Note**: Der Vorschlag wird separat gespeichert und im Feedback-Panel angezeigt; es wird **keine** Zahl in den Gradebook geschrieben
- **Einzel- und Sammelbewertung** sowie optional **automatische Bewertung bei Abgabe** (asynchron über den Cron)
- **Volle Kontrolle der Dozierenden** über alle KI-Ausgaben

## Anforderungen

- Moodle 4.5 oder neuer
- **[`local_ai_manager`](https://github.com/bycs-lp/moodle-local_ai_manager)** (ISB Bayern) – wird **separat installiert** und ist als Plugin-Abhängigkeit deklariert
- Alternativ (Backend „Moodle Core AI"): Moodle-AI-Subsystem mit konfiguriertem KI-Anbieter
- Für PDF-Raster/-Abgaben: `pdftotext` (empfohlen)
- PHP-ZipArchive-Erweiterung (für DOCX, PPTX, ODT)

## Installation

Zuerst `local_ai_manager` installieren (siehe dessen Dokumentation), danach dieses Plugin:

```bash
git clone https://github.com/BLC-FHGR/moodle-ai-grader-fhgr /pfad/zu/moodle/local/aigrade
```

Anschliessend **Website-Administration → Benachrichtigungen** aufrufen und das Upgrade
ausführen. Alternativ als ZIP über **Website-Administration → Plugins → Plugins installieren**
hochladen (Ordnername im ZIP muss `aigrade` lauten).

**Wichtig:** Bei bestehenden Installationen **nur aktualisieren, nicht deinstallieren** –
Deinstallieren löscht die Plugin-Datenbanktabellen (Konfiguration, gespeicherte Vorschläge).

> **Hinweis zum `local_ai_manager`:** Die FHGR-Installation nutzt eine minimal gepatchte
> Fassung, die **frei eingebbare Modellnamen** erlaubt (Upstream bietet nur ein Dropdown der
> einprogrammierten Modelle). Wer eigene/lokale Modellnamen wie `gemma4-31b-reasoning`
> verwenden will, braucht diesen Patch – siehe [`docs/local_ai_manager-patch.md`](docs/local_ai_manager-patch.md).

## Konfiguration

### Globale Einstellungen
**Website-Administration → Plugins → Lokale Plugins → KI-Bewertungsassistenz**
- **Name der KI-Assistenz** (Standard: „KI")
- **Standard-Anweisungen (mit Raster)** und **(ohne Raster)** – werden in neue Aufgaben übernommen
- **Zeitlimit für interaktive Anfragen** (Standard 4 Minuten) – wie lange eine über einen
  Bewertungs-Button ausgelöste KI-Anfrage dauern darf; hebt das site-weite Limit des
  `local_ai_manager` (Standard 60 s) nur für diese Anfrage an
- **Konto für automatische Bewertung** und **Zeitlimit für Hintergrund-Anfragen** (für die Auto-Bewertung)

### KI-Backend wählen
In den globalen Einstellungen lässt sich das Backend wählen:
- **Local AI Manager (Standard):** nutzt die Modelle des Plugins `local_ai_manager` – ideal für lokal gehostete Modelle. Zusätzlich den **Purpose** wählen (Standard `feedback`); das Modell wird im `local_ai_manager` pro Purpose/Rolle festgelegt. Voraussetzungen: Tool/Modell einem Purpose zugewiesen, Tenant aktiviert, Dozierende mit Capability `local/ai_manager:use` und einmalig bestätigter KI-Nutzung.
- **Moodle Core AI-Subsystem:** nutzt die unter **Website-Administration → KI → KI-Anbieter** eingerichteten Anbieter.

Beachten Sie die Datenschutzvorgaben Ihrer Hochschule (siehe unten).

## Verwendung

### Aktivieren pro Aufgabe
1. Aufgabe erstellen/bearbeiten → Abschnitt **KI-Bewertung**
2. **KI-Bewertung** aktivieren
3. Optional **„Automatisch bei Abgabe bewerten"** aktivieren (läuft im Cron)
4. Optional die KI-Anweisungen für diese Aufgabe anpassen
5. Optional ein Beurteilungsraster hochladen
6. Speichern

### Einzelbewertung
Abgabe öffnen → Schaltfläche **„Mit KI bewerten"**. Der Vorschlag erscheint im Panel rechts.
Mit **„Ins Feedback-Feld übernehmen"** lässt er sich in Moodles Feedback-Editor übertragen;
gespeichert wird er nur durch die Dozierenden selbst.

### Sammelbewertung
In der Abgabenübersicht **„Alle mit KI bewerten"**. Es werden nur Abgaben verarbeitet, für die noch **kein** KI-Vorschlag vorliegt.

## Funktionsweise

1. Die KI liest den eingereichten Text.
2. Sie berücksichtigt Raster bzw. Aufgabenbeschreibung und die Dozierenden-Anweisungen.
3. Sie erstellt eine begründete Punkteempfehlung samt Feedback.
4. Der Vorschlag wird in `local_aigrade_feedback` gespeichert – **ohne** Note, **ohne** Eintrag im Kommentarfeld.
5. Die Dozierenden prüfen, übernehmen bei Bedarf und entscheiden final.

## Datenschutz

- Abgabetexte werden an den im `local_ai_manager` (bzw. im Moodle-AI-Subsystem) konfigurierten KI-Anbieter übermittelt. Bei lokal gehosteten Modellen bleiben sie in der eigenen Infrastruktur. Klären Sie die Vereinbarkeit mit den Datenschutzvorgaben Ihrer Hochschule (in der Schweiz insb. revDSG) und informieren Sie die Studierenden.
- Der `local_ai_manager` protokolliert Prompt und Antwort in `local_ai_manager_request_log` (Zugriff über Capabilities steuern).
- Dieses Plugin speichert keine personenbezogenen Daten über die gespeicherten KI-Vorschläge hinaus und implementiert die Moodle-Privacy-API.
- Diese Version enthält **kein** Debug-Logging von Abgaben und **keinen** externen Google-Docs-Abruf (in der Ursprungsversion vorhanden, aus Datenschutzgründen entfernt).

## Grenzen

- Nur **Textinhalte** werden bewertet; Bilder, Formatierung, Layout, Tabellen/Diagramme und Folien-Design können nicht beurteilt werden.
- Gut geeignet für textlastige Arbeiten; nicht geeignet, wenn visuelle Gestaltung Bewertungskriterium ist.

## Dokumentation

- [`ANPASSUNGEN-FHGR.md`](ANPASSUNGEN-FHGR.md) – Änderungen gegenüber dem Upstream „AI Grade"
- [`CHANGELOG.md`](CHANGELOG.md) – Änderungen der FHGR-Fassung
- [`CHANGELOG-upstream.md`](CHANGELOG-upstream.md) – Changelog des Upstream-Plugins bis v1.7.2
- [`docs/ROADMAP.md`](docs/ROADMAP.md) – Weiterentwicklung
- [`docs/prompt-und-raster/`](docs/prompt-und-raster/) – versionierter System-Prompt und LN1-Beurteilungsraster
- [`docs/local_ai_manager-patch.md`](docs/local_ai_manager-patch.md) – benötigter Patch am `local_ai_manager`

## Credits & Lizenz

- **Ursprünglicher Autor**: Brian A. Pool, National Trail Local Schools (© 2025/2026)
- **Hochschulanpassung**: FH Graubünden (© 2026)
- **Lizenz**: GNU GPL v3 oder später

Dieses Programm ist freie Software gemäss GNU General Public License v3 (oder später). Es wird ohne jede Gewährleistung bereitgestellt. Siehe <https://www.gnu.org/licenses/>.

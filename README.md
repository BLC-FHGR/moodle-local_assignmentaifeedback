# Moodle KI-Bewertungsassistenz – FH Graubünden

Dieses Repository bündelt zwei Moodle-Plugins, die gemeinsam die **KI-gestützte
Bewertung schriftlicher Hochschularbeiten** (Exposés, Studien-/Seminararbeiten)
als **Unterstützung für Dozierende** ermöglichen — mit **lokalen Sprachmodellen**
und ohne automatische Notenvergabe.

> Das Repository ist ein **Monorepo**: beide Plugins liegen als eigene Unterordner
> vor. Für die Installation wird jeweils der Plugin-Ordner nach `moodle/local/`
> kopiert (siehe unten) – ein direktes `git clone` in `moodle/local/` ist wegen der
> Monorepo-Struktur nicht vorgesehen.

## Enthaltene Plugins

| Ordner | Komponente | Zweck |
|---|---|---|
| [`local_aigrade/`](local_aigrade/) | `local_aigrade` | Der eigentliche AI Grader: erzeugt kriterienbezogene **Punkteempfehlungen + Feedback** anhand eines hinterlegten Beurteilungsrasters. |
| [`local_ai_manager/`](local_ai_manager/) | `local_ai_manager` | KI-Anbindung (Tenant, Modelle, Nutzungslogs). Gepatchte Fork-Version, die **frei eingebbare Modellnamen** erlaubt. |

## Herkunft / Upstream

- **local_aigrade** basiert auf „AI Grade" von **Brian A. Pool, National Trail Local
  Schools** (Upstream-Version `2026031100`, v1.7.2). Diese Fassung ist stark an den
  **Schweizer Hochschulkontext** angepasst — aktuell **v2.2.1-fhgr** (`2026082003`).
- **local_ai_manager** ist ein Plugin von **ISB Bayern** (Upstream `2026102300`,
  Release 4.0). Hier nur **ein minimaler Patch** — **4.0-fhgr** (`2026102301`).

Details der Änderungen:
- [`local_aigrade/ANPASSUNGEN-FHGR.md`](local_aigrade/ANPASSUNGEN-FHGR.md)
- [`local_ai_manager/ANPASSUNGEN-FHGR.md`](local_ai_manager/ANPASSUNGEN-FHGR.md)

## Kernpunkte der Hochschul-Anpassung (local_aigrade)

- **Keine automatische Note**: Ergebnis ist eine **Punkteempfehlung + Begründung** als
  Feedback-Kommentar; die finale Bewertung nehmen die Dozierenden vor.
- **Steuerung über Prompt + Beurteilungsraster** (kein hartcodierter US-K-12-Kontext mehr).
- **Deutsch als primäre Sprache**, bereinigte englische Fallback-Strings.
- **Umschaltbares KI-Backend**: *Local AI Manager* (lokale Modelle) oder *Moodle-Core-AI*.
- **Feedback-Seitenpanel**: das erzeugte Feedback erscheint zusätzlich rechts neben der
  Rubrik und spiegelt live das Kommentarfeld der aktuellen Abgabe.
- **Datenschutz**: kein Debug-Logging von Abgaben, kein externer Google-Docs-Abruf.

## Installation

Pro Plugin (zuerst `local_ai_manager`, dann `local_aigrade`):

1. Den jeweiligen Ordner nach `moodle/local/` kopieren, sodass
   `moodle/local/ai_manager/version.php` bzw. `moodle/local/aigrade/version.php` existiert.
   (Alternativ als ZIP über *Website-Administration → Plugins → Plugins installieren*.)
2. *Website-Administration → Benachrichtigungen* aufrufen und das Upgrade ausführen.

**Wichtig:** Bei bestehenden Installationen **nur aktualisieren, nicht deinstallieren** –
Deinstallieren löscht die Plugin-Datenbanktabellen (Konfiguration/Logs).

## Konfiguration (Kurzfassung)

1. **local_ai_manager**: KI-Werkzeug/Modell einrichten und dem Purpose (Empfehlung:
   `feedback`) zuweisen; Tenant aktivieren; Dozierende brauchen `local/ai_manager:use`
   und einmalige KI-Nutzungsbestätigung.
2. **local_aigrade** (Website-Administration → Plugins → Lokale Plugins → KI-Bewertungsassistenz):
   - **KI-Backend** = *Local AI Manager*, **Purpose** = derselbe wie oben.
   - Standard-Anweisungen (Prompt) prüfen; pro Aufgabe Raster hochladen und KI-Bewertung aktivieren.

## Datenschutz-Hinweise

- Abgabetexte werden an den im ai_manager konfigurierten KI-Anbieter/Modell gesendet.
  Bei lokal gehosteten Modellen bleiben sie in der eigenen Infrastruktur.
- Der ai_manager protokolliert Prompt + Antwort in `local_ai_manager_request_log`
  (Zugriff über Capabilities steuern).
- Es werden **nur Textinhalte** extrahiert und übertragen – keine Originaldateien,
  keine Bilder/Formatierung (siehe auch Roadmap zu LN2/Formbewertung).

## Grading-Konfiguration (Prompt & Raster)

Der aktuell verwendete System-Prompt und das LN1-Beurteilungsraster sind unter
[`docs/prompt-und-raster/`](docs/prompt-und-raster/) versioniert.

## Weiterentwicklung

Siehe [`docs/ROADMAP.md`](docs/ROADMAP.md).

## Lizenz

GNU GPL v3 oder später. Copyright/Attribution siehe [`LICENSE`](LICENSE) sowie die
`ANPASSUNGEN-FHGR.md` der Plugins. Modifikationen der FH Graubünden stehen unter
derselben Lizenz.

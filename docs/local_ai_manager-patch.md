# Benötigter Patch am local_ai_manager (FH Graubünden)

> **Hinweis:** `local_ai_manager` ist **nicht Teil dieses Repositories** – es wird separat
> installiert (<https://github.com/bycs-lp/moodle-local_ai_manager>). Dieses Dokument hält
> fest, welche minimale Anpassung die FHGR-Installation an jenem Plugin vorgenommen hat und
> warum. Ohne den Patch funktioniert `local_assignmentaifeedback` weiterhin, es lassen sich dann aber nur
> die im jeweiligen Connector fest einprogrammierten Modelle auswählen (Dropdown statt
> freiem Modellnamen).

## Ausgangslage

Basis: `local_ai_manager` v4.0 (`2026102300`, ISB Bayern, GPLv3).
Angepasste Version: **4.0-fhgr** (`$plugin->version = 2026102301`).

## Grund
Die Modellauswahl war ein festes Dropdown, das nur die im jeweiligen Connector
einprogrammierten Modelle zuliess. Eigene/lokale Modelle mit Bezeichnungen wie
`google/gemma4-31b-reasoning` liessen sich damit nicht eintragen.

## Änderungen (nur `classes/base_instance.php`)

1. **Freies Textfeld statt Dropdown** (`edit_form_definition()`)
   - Wenn der Connector auswählbare Modelle kennt, wird jetzt ein **Textfeld** gerendert
     (statt `select`). Die bekannten Modelle werden weiterhin als **Autocomplete-Vorschläge**
     über eine HTML-`<datalist>` angeboten; frei eingegebene Modellnamen sind aber ebenso möglich.
   - Connectoren ohne Modellauswahl (preconfigured) behalten das bisherige Verhalten.

2. **`supported_purposes()` verträgt frei eingegebene Modelle**
   - Steht das konfigurierte Modell **nicht** in den fest einprogrammierten
     `get_models_by_purpose()`-Listen (also ein eigenes Modell), erbt die Instanz alle
     Verwendungszwecke, die der Connector grundsätzlich unterstützt (alle Purposes mit
     nicht-leerer Modell-Liste). Ohne diese Anpassung wäre ein frei eingegebenes Modell
     **keinem Purpose** zuweisbar gewesen.
   - Für vordefinierte Modelle bleibt das Verhalten exakt gleich (der Fallback greift nur,
     wenn kein exakter Treffer gefunden wurde).

## Nicht geändert
Sonst nichts. Keine DB-, API- oder Connector-Änderungen. Die Aufrufe in
`manager::get_tools_for_purpose()` und `connector_factory` bleiben unberührt.

## Test
1. Plugin aktualisieren (Version steigt auf `2026102301`).
2. **KI-Werkzeug (Instanz) bearbeiten**: Beim Modellfeld lässt sich jetzt frei tippen,
   z. B. `google/gemma4-31b-reasoning`; Vorschläge erscheinen weiterhin.
3. Instanz aktivieren und einem **Purpose** (z. B. `feedback`) zuweisen – die Instanz mit
   dem frei eingegebenen Modell erscheint jetzt in der Auswahl.
4. Testanfrage (z. B. über den AI Grader mit Backend „Local AI Manager") absetzen.

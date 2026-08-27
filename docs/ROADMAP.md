# Roadmap / Weiterentwicklungsideen

Status: Sammlung offener Ideen. Priorisierung folgt.

## Aktuell diskutiert

### 1. Feedback nur ins Panel, nicht ins Kommentarfeld
Idee: Das KI-Feedback nur im rechten Panel anzeigen und **nicht** in das Moodle-
Feedback-Kommentarfeld schreiben.
- Offene Frage: Speicherort/Persistenz, wenn nicht ins Kommentarfeld geschrieben wird
  (das Panel ist bisher nur eine Sicht-Kopie und speichert nichts).
- Abwägung Benutzerfreundlichkeit vs. Nachvollziehbarkeit/Export der Bewertung.

### 2. Rubrik aus Moodles „Erweiterter Bewertung" übernehmen
Idee: Statt das Raster als Datei hochzuladen, die in Moodle bereits gepflegte
**Rubrik/Bewertungsrichtlinie (Advanced Grading)** direkt als Bewertungsgrundlage nutzen.
- Vorteil: keine Doppelpflege des Rasters.
- Zu klären: Auslesen der `gradingform_rubric`/`gradingform_guide`-Definitionen und
  Einspeisen in den Prompt; Mapping der Punkte/Stufen.

## Aus früherer Analyse (Qualität & LN2)

- **LN2/Formbewertung**: Text-only-Extraktion kann Form (Layout, Tabellen, Abbildungen,
  Unterschrift) nicht erfassen. Optionen:
  - **A) Multimodaler Bild-Pfad** (Seiten rendern → Vision-Modell) – größter Hebel für Form.
  - **B) Strukturierte Extraktion** (Überschriften, Tabellen als Markdown, Bild-Platzhalter).
  - **C) Deterministische Form-Checks** (Umfang/Wortzahl, Abschnitte, Dateiname,
    Unterschrift vorhanden) als Fakten in den Prompt – günstig und robust.
- **Urteilsqualität**: gehärtete Grün-Gates im Raster, Ankerbeispiele im Prompt,
  stärkeres/Reasoning-Modell.
- **Pro-Aufgabe-Backend/Modell-Auswahl**: aktuell global; ggf. pro Aufgabe überschreibbar.
- **Timeout-Handling**: bei langsamen (Reasoning-)Modellen Connector-/Web-Timeouts;
  ggf. schnelleres Modell fürs Grading, Output-Token begrenzen.

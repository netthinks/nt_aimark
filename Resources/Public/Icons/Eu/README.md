# EU-Symbole

Die offiziellen Symbole der Europäischen Kommission zur Kennzeichnung
KI-generierter Inhalte. **Sie liegen diesem Paket bei** — es ist nichts
herunterzuladen.

## Herkunft und Nutzungsbedingungen

| | |
|---|---|
| Herausgeber | Europäische Kommission |
| Seite | <https://digital-strategy.ec.europa.eu/de/policies/eu-icons-labelling-ai-generated-content> |
| Bezug SVG | <https://ec.europa.eu/newsroom/dae/redirection/document/129546> |
| Bezug PNG | <https://ec.europa.eu/newsroom/dae/redirection/document/129547> |
| Nutzung | kostenfrei, ohne Attributionspflicht |
| Stand | August 2026 |

Die Symbole sind fester Bestandteil von Abschnitt 2 des Verhaltenskodex zur
Kennzeichnung KI-generierter Inhalte. Die Kommission stellt sie ausdrücklich
für diesen Zweck bereit.

Die Dateien in diesem Verzeichnis sind **unveränderte Originale**, lediglich
umbenannt (siehe unten). Nachzeichnen, Umfärben oder Übersetzen des
Schriftzugs ist nicht zulässig und wäre auch nicht sinnvoll: Ein Zeichen, das
unionsweit gleich aussieht, wird wiedererkannt — eine eigene Fassung wäre ein
Nachbau, kein offizielles Symbol.

## Warum sie beiliegen

Ohne sie zeigt die Extension nur Textlabel. Sie erst herunterladen, zwölf
Dateien umbenennen und ablegen zu lassen, hieße: Die Kernfunktion läuft nicht
ab Werk, und der häufigste Fehler wäre ein falsch benanntes Symbol.

## Namensschema

Zwölf Dateien je Format: drei Varianten in vier Farbfassungen.

```
ai-basic-black.svg          ai-basic-white.svg
ai-basic-black-50.svg       ai-basic-white-50.svg
ai-generated-black.svg      ai-generated-white.svg
ai-generated-black-50.svg   ai-generated-white-50.svg
ai-modified-black.svg       ai-modified-white.svg
ai-modified-black-50.svg    ai-modified-white-50.svg
```

Dazu dieselben zwölf als `.png`. Das Frontend arbeitet mit den SVG-Dateien;
die PNG-Fassung braucht `nt_aimark_pro` zum Einbrennen in die Bilddatei, weil
ImageMagick SVG nur mit zusätzlichen Delegates verarbeiten kann.

`-50` ist die halbtransparente Fassung.

## Selbst aktualisieren

Sollte die Kommission die Symbole ändern, bevor eine neue Version dieses
Pakets erscheint: Archiv herunterladen (Links oben), entpacken und nach obigem
Schema benennen. **Achtung:** Eine Datei ist im Archiv der Kommission falsch
geschrieben — `LABEL_AI MOFIFIED_black` statt `MODIFIED`.

| Datei im Archiv | Zielname hier |
|---|---|
| `LABEL_AI_black` | `ai-basic-black` |
| `LABEL_AI_black transparent` | `ai-basic-black-50` |
| `LABEL_AI_white` | `ai-basic-white` |
| `LABEL_AI_white transparent` | `ai-basic-white-50` |
| `LABEL_AI GENERATED_black` | `ai-generated-black` |
| `LABEL_AI GENERATED_black transparent` | `ai-generated-black-50` |
| `LABEL_AI GENERATED_white` | `ai-generated-white` |
| `LABEL_AI GENERATED_white transparent` | `ai-generated-white-50` |
| `LABEL_AI MOFIFIED_black` ← *(Schreibfehler im Original)* | `ai-modified-black` |
| `LABEL_AI MODIFIED_black transparent` | `ai-modified-black-50` |
| `LABEL_AI MODIFIED_white` | `ai-modified-white` |
| `LABEL_AI MODIFIED_white transparent` | `ai-modified-white-50` |

Bei Composer-Installation liegt dieses Verzeichnis unterhalb von `vendor/`.
Eigene Änderungen dort sind nach dem nächsten `composer install` weg — was in
diesem Fall kein Verlust ist, denn dann liegen die mitgelieferten Dateien
wieder da.

## Was die Extension damit macht

Die Grafik selbst wird nicht verändert. Vor dem Einbinden ins Seitenmarkup
geschehen zwei Dinge, die technisch nötig sind:

- **Sanitising.** Die Dateien werden unescaped in die Seite eingebettet.
- **Farben als Attribute statt `<style>`.** Alle zwölf tragen ihre Farben in
  einem Stylesheet im SVG. Eine Content Security Policy mit Nonce für
  `style-src-elem` — in TYPO3 v14 der Standard — verwirft das kommentarlos,
  und aus dem Symbol würde eine schwarze Fläche. Die Deklarationen werden
  deshalb auf die Elemente geschrieben. Nebenbei entfällt damit die Kollision
  der generischen Klassennamen, die alle zwölf Dateien teilen.

Fehlen die Dateien trotzdem einmal — etwa weil jemand sie gelöscht hat —,
gibt die Extension ein Textlabel aus und meldet es im Systemstatus. Es
entsteht kein Fehler.

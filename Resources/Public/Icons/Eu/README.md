# EU-Icons

Die offiziellen Icons der Europäischen Kommission zur Kennzeichnung
KI-generierter Inhalte liegen bewusst **nicht** im Repository — die
Originaldateien der Kommission sind die maßgebliche Fassung, eine mitgelieferte
Kopie würde daran vorbeialtern. Sie sind kostenfrei und ohne
Attributionspflicht nutzbar.

## Bezug

Übersichtsseite:
<https://digital-strategy.ec.europa.eu/de/policies/eu-icons-labelling-ai-generated-content>

Die Download-Links stehen nur in der **englischen** Fassung der Seite; die
deutsche ist eine maschinelle eTranslation-Übersetzung, in der sie fehlen.
Direkt:

| Format | ZIP |
|---|---|
| SVG (für diese Extension) | <https://ec.europa.eu/newsroom/dae/redirection/document/129546> |
| PNG | <https://ec.europa.eu/newsroom/dae/redirection/document/129547> |

Das SVG-Archiv enthält genau die zwölf benötigten Dateien: drei Varianten in
je vier Farbfassungen (schwarz, weiß, beide zusätzlich mit 50 % Transparenz).

## Umbenennen

Die Dateinamen im Archiv folgen einem anderen Schema und müssen zugeordnet
werden. **Achtung:** Eine Datei ist im Archiv der Kommission falsch geschrieben
(`MOFIFIED` statt `MODIFIED`).

| Datei im Archiv | Zielname hier |
|---|---|
| `LABEL_AI_black.svg` | `ai-basic-black.svg` |
| `LABEL_AI_black transparent.svg` | `ai-basic-black-50.svg` |
| `LABEL_AI_white.svg` | `ai-basic-white.svg` |
| `LABEL_AI_white transparent.svg` | `ai-basic-white-50.svg` |
| `LABEL_AI GENERATED_black.svg` | `ai-generated-black.svg` |
| `LABEL_AI GENERATED_black transparent.svg` | `ai-generated-black-50.svg` |
| `LABEL_AI GENERATED_white.svg` | `ai-generated-white.svg` |
| `LABEL_AI GENERATED_white transparent.svg` | `ai-generated-white-50.svg` |
| `LABEL_AI MOFIFIED_black.svg` ← *(Schreibfehler im Original)* | `ai-modified-black.svg` |
| `LABEL_AI MODIFIED_black transparent.svg` | `ai-modified-black-50.svg` |
| `LABEL_AI MODIFIED_white.svg` | `ai-modified-white.svg` |
| `LABEL_AI MODIFIED_white transparent.svg` | `ai-modified-white-50.svg` |

Fehlen die Dateien, verwendet die Extension automatisch Textlabels und meldet
den fehlenden Icon-Satz im Systemstatus-Bericht. Es entsteht kein Fehler.

## Was die Extension mit den Dateien macht — und was nicht

Die Icons dürfen nicht nachgezeichnet oder generiert werden. Die Extension
verändert die Grafik auch nicht; sie nimmt vor dem Einbinden nur zwei
Eingriffe am Markup vor, die technisch nötig sind:

- **Sanitising.** Die Dateien kommen per manuellem Download ins System, an
  TYPOs Upload-Prüfung vorbei, und werden unescaped in die Seite eingebettet.
- **Eindeutige Klassennamen und IDs.** Alle zwölf Dateien deklarieren
  dieselben `.cls-1`/`.cls-2` und dieselbe `id="Calque_1"`. Ohne Umbenennung
  würde auf einer Seite mit zwei Icons das zweite `<style>`-Element das erste
  Icon umfärben.

Die Icons sind **zweifarbig** (gefüllte Scheibe, Schriftzug ausgespart). Eine
Einfärbung per `fill: currentColor` ist deshalb ausdrücklich nicht vorgesehen —
die Kontrastwahl erfolgt über die Dateivariante (schwarz/weiß), nicht über CSS.

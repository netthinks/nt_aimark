# Installation

## Voraussetzungen

| | |
|---|---|
| TYPO3 | 13.4 LTS oder 14 LTS |
| PHP | 8.2 – 8.4 |
| Optional | `c2patool` zum Auswerten von Content Credentials |

## Extension installieren

```bash
composer require netthinks/nt-aimark
vendor/bin/typo3 extension:setup
vendor/bin/typo3 cache:flush
```

`extension:setup` legt die Felder in `sys_file_metadata`, `pages` und
`tt_content` sowie die Audit-Tabelle `tx_ntaimark_audit` an.

## Site Set einbinden

In `config/sites/<identifier>/config.yaml`:

```yaml
dependencies:
  - netthinks/nt-aimark
```

Erst damit stehen die Einstellungen aus der
[Konfiguration](Konfiguration.md) zur Verfügung.

## EU-Icons ergänzen

Die drei offiziellen Symbole der Europäischen Kommission liegen **nicht** im
Repository. Sie sind kostenfrei und ohne Attributionspflicht nutzbar, dürfen
aber nicht nachgezeichnet oder generiert werden — nur die Originaldateien sind
die Originaldateien.

Bezugsquelle:
<https://digital-strategy.ec.europa.eu/de/policies/eu-icons-labelling-ai-generated-content>

Legen Sie die SVG-Dateien unter `Resources/Public/Icons/Eu/` ab. Erwartet wird:

```
ai-basic-black.svg          ai-basic-white.svg
ai-basic-black-50.svg       ai-basic-white-50.svg
ai-generated-black.svg      ai-generated-white.svg
ai-generated-black-50.svg   ai-generated-white-50.svg
ai-modified-black.svg       ai-modified-white.svg
ai-modified-black-50.svg    ai-modified-white-50.svg
```

**Fehlen die Dateien, läuft die Extension weiter** und gibt statt des Symbols
eine Textkennzeichnung aus („KI-generiert", „KI-bearbeitet", „KI"). Es
entsteht kein Fehler und kein leeres Bild.

> Bei Installation über Composer liegt das Icon-Verzeichnis unterhalb von
> `vendor/`. Verwalten Sie die Dateien deshalb außerhalb und kopieren Sie sie
> im Deployment dorthin, sonst sind sie nach dem nächsten
> `composer install` weg.

## Kennzeichnung ins Template bringen

Im Fluid-Template der Seite oder des Inhaltselements:

```html
{namespace nt=NetThinks\NtAimark\ViewHelpers}

<nt:aiFigure file="{file}">
    <f:image image="{file}" width="800" />
</nt:aiFigure>
```

Der ViewHelper gibt das Bild unverändert zurück, wenn die Datei nach dem
Regelwerk nicht zu kennzeichnen ist — er kann also bedingungslos gesetzt
werden.

CSS und JavaScript werden nur eingebunden, wenn tatsächlich eine
Kennzeichnung gerendert wird.

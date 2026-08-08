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
[Konfiguration](configuration.md) zur Verfügung.

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

## Optional: c2patool für Content Credentials

Ohne dieses Werkzeug erkennt die Extension Herkunftsdaten nur aus XMP und
EXIF und vermerkt den C2PA-Zustand als „nicht prüfbar". Mit ihm liest sie
zusätzlich signierte C2PA-Manifeste — die einzige Quelle, die eine
kryptografisch abgesicherte statt einer selbst behaupteten Aussage liefert.

Zum **Lesen und Prüfen ist kein Zertifikat nötig**; nur das Schreiben von
Manifesten bräuchte eines, und das tut die Extension nicht.

Fertige Binärdateien für Linux, macOS und Windows:
<https://github.com/contentauth/c2pa-rs/releases> (nach `c2patool` filtern).

In einem DDEV-Projekt genügt ein Eintrag in `.ddev/web-build/Dockerfile`:

```dockerfile
ARG C2PATOOL_VERSION=0.27.7
RUN curl -fsSL "https://github.com/contentauth/c2pa-rs/releases/download/c2patool-v${C2PATOOL_VERSION}/c2patool-v${C2PATOOL_VERSION}-x86_64-unknown-linux-gnu.tar.gz" \
        -o /tmp/c2patool.tar.gz \
    && tar xzf /tmp/c2patool.tar.gz -C /tmp \
    && mv /tmp/c2patool/c2patool /usr/local/bin/c2patool \
    && chmod +x /usr/local/bin/c2patool \
    && rm -rf /tmp/c2patool /tmp/c2patool.tar.gz
```

Liegt das Werkzeug nicht im `PATH`, tragen Sie den Pfad in den
Extension-Einstellungen unter *Erkennung* ein.

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

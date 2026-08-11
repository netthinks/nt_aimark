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

## Die EU-Symbole

**Sie liegen bei.** Nichts herunterzuladen, nichts umzubenennen — die zwölf
offiziellen Symbole der Europäischen Kommission sind Bestandteil des Pakets,
als SVG für die Ausgabe im Frontend und als PNG für das Einbrennen in
Bilddateien durch `nt_aimark_pro`.

Die Kommission stellt sie kostenfrei und ohne Attributionspflicht bereit; sie
sind fester Bestandteil von Abschnitt 2 des Verhaltenskodex zur Kennzeichnung
KI-generierter Inhalte. Herkunft und Bedingungen stehen in
`Resources/Public/Icons/Eu/README.md`.

Nachgezeichnet, umgefärbt oder übersetzt werden dürfen sie nicht — und es
wäre auch unsinnig: Ein Zeichen, das unionsweit gleich aussieht, wird
wiedererkannt. Eine eigene Fassung wäre ein Nachbau.

Sollten die Dateien einmal fehlen, weil jemand sie entfernt hat, **läuft die
Extension weiter** und gibt statt des Symbols ein Textlabel aus
(„KI-generiert", „KI-bearbeitet", „KI"). Der Systemstatus im Backend-Modul
meldet es. Es entsteht kein Fehler und kein leeres Bild.

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

### Wenn sich `c2patool` nicht installieren lässt

Auf gemanagten Hostings scheitert es häufig unabhängig vom Pfad: Das Werkzeug
ist gegen glibc gebunden und braucht den dynamischen Loader unter `/lib64`,
den solche Umgebungen nicht bereitstellen. Daran ändert keine Einstellung
etwas.

Drei Wege stehen dann offen:

1. **Nichts tun.** Die Kennzeichnung funktioniert vollständig. Es entfällt
   eine von drei Erkennungsquellen, und der Signaturzustand bleibt „nicht
   prüfbar". Für viele Bestände ist das kein praktischer Verlust — C2PA ist
   noch wenig verbreitet.
2. **Eigener Server oder Container.** Wo Sie selbst installieren dürfen, ist
   es in Minuten erledigt (siehe oben).
3. **Prüfung auslagern.** Das Zusatzpaket `nt_aimark_pro` kann die
   Signaturprüfung an einen gehosteten Dienst abgeben, sodass auf Ihrem
   Server nichts installiert werden muss. Dabei verlassen Mediendateien Ihren
   Server — das ist eine Auftragsverarbeitung und will vertraglich geregelt
   sein. Wir haben das unter
   [netthinks.com](https://www.netthinks.com/leistungen/websites/ki-kennzeichnung-typo3/)
   beschrieben.

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

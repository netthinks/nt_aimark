# AI Mark – AI transparency for TYPO3 (`nt_aimark`)

[![CI](https://github.com/netthinks/nt_aimark/actions/workflows/ci.yml/badge.svg)](https://github.com/netthinks/nt_aimark/actions/workflows/ci.yml)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![TYPO3 13.4 | 14](https://img.shields.io/badge/TYPO3-13.4%20%7C%2014-orange.svg)](https://get.typo3.org/)
[![TER](https://img.shields.io/badge/TER-nt__aimark-orange.svg)](https://extensions.typo3.org/extension/nt_aimark)

*[Deutsche Fassung weiter unten](#ai-mark--ki-kennzeichnung-für-typo3-nt_aimark)*

Record, label and document AI-generated content in TYPO3 — for the transparency obligations under Art. 50 of the EU AI Act (Regulation (EU) 2024/1689), in force since 2 August 2026.

> **Status:** Beta. Functionally complete and tested throughout; the interfaces
> are considered stable from here on. Details may still be refined before 1.0.
> The current version and what changed is in the [changelog](CHANGELOG.md).

---

## What it does

- **Record** — a dedicated "AI transparency" tab in the file metadata: AI status, system used, creation date, responsible person. Matching fields on pages and content elements for text.
- **Suggest automatically** — on upload, existing provenance data is read (C2PA / Content Credentials, IPTC `DigitalSourceType`, EXIF signatures) and entered as a *suggestion*. Confirmation stays with a human.
- **Label** — accessible frontend output using the official EU icons, with an expandable detail level naming the system used.
- **Evidence** — a backend module showing reviewed and open assets, plus an append-only log of every status change.
- **Keep metadata** — TYPO3 strips profiles during image processing by default. The extension writes the relevant fields back into the processed files.

## What it deliberately does not do

- **No legal advice.** Whether a specific piece of content must be labelled is a case-by-case decision. The extension structures and documents that decision — it does not make it.
- **No detection of third-party AI content.** Heuristic detectors are unreliable, and a false positive would be a false statement about someone's content.
- **No guarantee of compliance.** The extension supports the implementation. For assessing your case, please seek legal advice.

---

## Installation

```bash
composer require netthinks/nt-aimark
vendor/bin/typo3 extension:setup
```

Then add the `AI Mark` site set to your site configuration.

### EU icons

The twelve official icons of the European Commission are included — twelve SVG for frontend output, twelve PNG for burning in via `nt_aimark_pro`. Nothing to download.

Origin, terms of use and the naming scheme are in [`Resources/Public/Icons/Eu/README.md`](Resources/Public/Icons/Eu/README.md). If someone removes the files, the extension falls back to text labels and reports it in the system status.

## Use in a template

```html
{namespace nt=NetThinks\NtAimark\ViewHelpers}

<nt:aiFigure file="{file}">
    <f:image image="{file}" width="800" />
</nt:aiFigure>
```

Alternatively the bundled FileRenderer labels images without any template change.

## Requirements

| | |
|---|---|
| TYPO3 | 13.4 LTS, 14 LTS |
| PHP | 8.2 – 8.4 |
| Optional | `c2patool` for reading Content Credentials |

Without `c2patool`, labelling works in full; only one of three detection sources drops out and the signature state stays "not verifiable". [What to do if it cannot be installed](https://docs.netthinks.com/nt-aimark/installation/).

## A free core package

This repository is complete and usable on its own: no licence key, no domain
binding, no greyed-out functions. Additional features ship as a separate
Composer package and hook into documented extension points — see
[Integration](Documentation/Integration.md).

## Documentation

| | |
|---|---|
| Full documentation, bilingual | <https://docs.netthinks.com/nt-aimark/> |
| TYPO3 documentation | [`Documentation/`](Documentation/) |

## Contributing

Bug reports and feature requests are welcome as an [issue](https://github.com/netthinks/nt_aimark/issues). Pull requests against `develop`, with green CI (PHPStan level 8, PHP-CS-Fixer, unit and acceptance tests).

## Licence

GPL-2.0-or-later, see [LICENSE](LICENSE).

## Who builds this

[NET.THINKS](https://www.netthinks.com), a TYPO3 agency in Villingen-Schwenningen, Germany.
Commercial support, audits and the add-on package: <https://www.netthinks.com/leistungen/websites/ki-kennzeichnung-typo3/>

## Legal note

This extension is a technical aid. It does not constitute legal advice and gives no warranty of compliance with the AI Act or any other regulation. Judging whether and how a specific piece of content must be labelled remains with the site operator.

---
---

# AI Mark – KI-Kennzeichnung für TYPO3 (`nt_aimark`)

*[English version above](#ai-mark--ai-transparency-for-typo3-nt_aimark)*

Erfassen, kennzeichnen und dokumentieren Sie KI-generierte Inhalte in TYPO3 — passend zu den Transparenzpflichten aus Art. 50 der EU-KI-Verordnung (VO (EU) 2024/1689), die seit dem 2. August 2026 gelten.

> **Stand:** Beta. Funktional vollständig und durchgehend getestet; die
> Schnittstellen gelten ab hier als stabil. Bis zur 1.0 kann sich noch
> Feinschliff ändern. Welche Fassung aktuell ist und was sich geändert hat,
> steht im [Changelog](CHANGELOG.md).

---

## Was die Extension macht

- **Erfassen** — eigener Reiter „KI-Transparenz" in den Dateimetadaten: KI-Status, eingesetztes System, Erzeugungsdatum, verantwortliche Person. Für Texte entsprechende Felder auf Seiten und Inhaltselementen.
- **Automatisch vorschlagen** — beim Upload werden vorhandene Provenienzdaten ausgelesen (C2PA / Content Credentials, IPTC `DigitalSourceType`, EXIF-Signaturen) und als *Vorschlag* eingetragen. Die Bestätigung bleibt beim Menschen.
- **Kennzeichnen** — barrierefreie Ausgabe im Frontend mit den offiziellen EU-Symbolen, inklusive aufklappbarer Detailebene mit Angaben zum eingesetzten System.
- **Nachweisen** — Backend-Modul mit Übersicht über geprüfte und offene Dateien sowie einem fortschreibenden Protokoll aller Statusänderungen.
- **Metadaten erhalten** — TYPO3 entfernt bei der Bildverarbeitung standardmäßig Profile. Die Extension schreibt die relevanten Angaben in die Prozessdateien zurück.

## Was die Extension ausdrücklich nicht macht

- **Keine Rechtsberatung.** Ob ein konkreter Inhalt kennzeichnungspflichtig ist, ist eine Einzelfallentscheidung. Die Extension strukturiert diese Entscheidung und dokumentiert sie — sie trifft sie nicht.
- **Keine Erkennung fremder KI-Inhalte.** Heuristische Detektoren sind unzuverlässig; ein falsch positives Ergebnis wäre eine falsche Aussage über einen fremden Inhalt.
- **Keine Garantie der Rechtskonformität.** Die Extension unterstützt bei der Umsetzung. Für die Bewertung des Einzelfalls ziehen Sie bitte rechtlichen Rat hinzu.

---

## Installation

```bash
composer require netthinks/nt-aimark
vendor/bin/typo3 extension:setup
```

Anschließend das Site Set `AI Mark` in der Site-Konfiguration einbinden.

### EU-Symbole

Die zwölf offiziellen Symbole der Europäischen Kommission liegen bei — zwölf SVG für die Ausgabe im Frontend, zwölf PNG für das Einbrennen durch `nt_aimark_pro`. Es ist nichts herunterzuladen.

Herkunft, Nutzungsbedingungen und Namensschema stehen in [`Resources/Public/Icons/Eu/README.md`](Resources/Public/Icons/Eu/README.md). Fehlen die Dateien, weil jemand sie entfernt hat, verwendet die Extension automatisch Textlabels und meldet es im Systemstatus.

## Verwendung im Template

```html
{namespace nt=NetThinks\NtAimark\ViewHelpers}

<nt:aiFigure file="{file}">
    <f:image image="{file}" width="800" />
</nt:aiFigure>
```

Alternativ übernimmt der mitgelieferte FileRenderer die Kennzeichnung, ohne dass Templates angepasst werden müssen.

## Voraussetzungen

| | |
|---|---|
| TYPO3 | 13.4 LTS, 14 LTS |
| PHP | 8.2 – 8.4 |
| Optional | `c2patool` für die Auswertung von Content Credentials |

Ohne `c2patool` funktioniert die Kennzeichnung vollständig; es entfällt eine von drei Erkennungsquellen, und der Signaturzustand bleibt „nicht prüfbar". [Was tun, wenn es sich nicht installieren lässt](https://docs.netthinks.com/nt-aimark/de/installation/).

## Freies Kernpaket

Dieses Repository ist vollständig und eigenständig nutzbar: kein
Lizenzschlüssel, keine Domainbindung, keine ausgegrauten Funktionen.
Zusatzfunktionen erscheinen als eigenes Composer-Paket und klinken sich über
dokumentierte Erweiterungspunkte ein — siehe
[Integration](Documentation/Integration.md).

## Dokumentation

| | |
|---|---|
| Vollständige Doku, zweisprachig | <https://docs.netthinks.com/nt-aimark/de/> |
| TYPO3-Dokumentation | [`Documentation/`](Documentation/) |

## Mitwirken

Fehlerberichte und Wünsche gern als [Issue](https://github.com/netthinks/nt_aimark/issues). Pull Requests bitte gegen `develop`, mit grüner CI (PHPStan Level 8, PHP-CS-Fixer, Unit- und Acceptance-Tests).

## Lizenz

GPL-2.0-or-later, siehe [LICENSE](LICENSE).

## Wer dahintersteht

[NET.THINKS](https://www.netthinks.com), TYPO3-Agentur aus Villingen-Schwenningen.
Kommerzieller Support, Audits und das Zusatzpaket: <https://www.netthinks.com/leistungen/websites/ki-kennzeichnung-typo3/>

## Rechtlicher Hinweis

Diese Extension ist ein technisches Hilfsmittel. Sie stellt keine Rechtsberatung dar und begründet keine Gewähr für die Einhaltung der KI-Verordnung oder anderer Rechtsvorschriften. Die inhaltliche Bewertung, ob und wie ein konkreter Inhalt zu kennzeichnen ist, obliegt dem Betreiber der Website.

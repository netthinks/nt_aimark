# AI Mark – KI-Kennzeichnung für TYPO3 (`nt_aimark`)

[![CI](https://github.com/netthinks/nt_aimark/actions/workflows/ci.yml/badge.svg)](https://github.com/netthinks/nt_aimark/actions/workflows/ci.yml)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![TYPO3 13.4 | 14](https://img.shields.io/badge/TYPO3-13.4%20%7C%2014-orange.svg)](https://get.typo3.org/)

Erfassen, kennzeichnen und dokumentieren Sie KI-generierte Inhalte in TYPO3 — passend zu den Transparenzpflichten aus Art. 50 der EU-KI-Verordnung (VO (EU) 2024/1689), die seit dem 2. August 2026 gelten.

> **Status:** In Entwicklung (`alpha`). Noch nicht für den Produktiveinsatz geeignet.

---

## Was die Extension macht

- **Erfassen** — eigener Reiter „KI-Transparenz“ in den Dateimetadaten: KI-Status, eingesetztes System, Erzeugungsdatum, verantwortliche Person. Für Texte entsprechende Felder auf Seiten und Inhaltselementen.
- **Automatisch vorschlagen** — beim Upload werden vorhandene Provenienzdaten ausgelesen (C2PA / Content Credentials, IPTC `DigitalSourceType`, EXIF-Signaturen) und als Vorschlag eingetragen. Die Bestätigung bleibt beim Menschen.
- **Kennzeichnen** — barrierefreie Ausgabe im Frontend mit den offiziellen EU-Icons, inklusive aufklappbarer Detailebene mit Angaben zum eingesetzten System.
- **Nachweisen** — Backend-Modul mit Übersicht über geprüfte und offene Assets sowie einem revisionssicheren Protokoll aller Statusänderungen.
- **Metadaten erhalten** — TYPO3 entfernt bei der Bildverarbeitung standardmäßig Profile. Die Extension schreibt die relevanten Angaben in die Prozessdateien zurück.

## Was die Extension ausdrücklich nicht macht

- **Keine Rechtsberatung.** Ob ein konkreter Inhalt kennzeichnungspflichtig ist, ist eine Einzelfallentscheidung. Die Extension strukturiert diese Entscheidung und dokumentiert sie — sie trifft sie nicht.
- **Keine Erkennung fremder KI-Inhalte.** Heuristische Detektoren sind unzuverlässig; ein falsch positives Ergebnis wäre eine falsche Aussage über einen Inhalt.
- **Keine Garantie der Rechtskonformität.** Die Extension unterstützt bei der Umsetzung. Für die Bewertung des Einzelfalls ziehen Sie bitte rechtlichen Rat hinzu.

---

## Installation

```bash
composer require netthinks/nt-aimark
```

Anschließend das Site Set `AI Mark` in Ihrer Site-Konfiguration einbinden.

### EU-Icons ergänzen

Aus lizenz- und integritätsgründen liegen die offiziellen Icons nicht im Repository. Laden Sie sie kostenfrei von der Europäischen Kommission herunter und legen Sie sie unter `Resources/Public/Icons/Eu/` ab:

<https://digital-strategy.ec.europa.eu/de/policies/eu-icons-labelling-ai-generated-content>

Erwartete Dateinamen siehe [`CLAUDE.md`](CLAUDE.md), Abschnitt 1. Fehlen die Dateien, verwendet die Extension automatisch Textlabels.

---

## Verwendung im Template

```html
{namespace nt=NetThinks\NtAimark\ViewHelpers}

<nt:aiFigure file="{file}">
    <f:image image="{file}" width="800" />
</nt:aiFigure>
```

Alternativ übernimmt der mitgelieferte FileRenderer die Kennzeichnung automatisch, ohne dass Templates angepasst werden müssen.

---

## Voraussetzungen

| | |
|---|---|
| TYPO3 | 13.4 LTS, 14 LTS |
| PHP | 8.2 – 8.4 |
| Optional | `c2patool` für die Auswertung von Content Credentials |

---

## Teil der NET.THINKS KI-Suite

| Extension | Zweck |
|---|---|
| [`nt_ai`](https://github.com/netthinks) | KI-Anbindung für TYPO3: Alt-Texte, Barrierefreiheits-Audits, Claude/OpenAI/Ollama |
| [`nt_lingua`](https://github.com/netthinks) | Übersetzung, Einfache Sprache, l10n-Overlays |
| **`nt_aimark`** | KI-Transparenz und Kennzeichnung |

Sind `nt_ai` oder `nt_lingua` installiert, übernimmt `nt_aimark` die Provenienzdaten automatisch — KI-generierte Inhalte sind ohne redaktionellen Zusatzaufwand erfasst.

---

## Dokumentation

| | |
|---|---|
| Vollständige Doku (MkDocs, zweisprachig) | [`docs/`](docs/) — `mkdocs serve` |
| TYPO3-Dokumentation | [`Documentation/`](Documentation/) |

Beide Fassungen werden parallel gepflegt: `Documentation/` folgt der
TYPO3-Konvention, `docs/` baut die Website unter docs.netthinks.com.

## Mitwirken

Fehlerberichte und Feature-Wünsche gern als [Issue](https://github.com/netthinks/nt_aimark/issues). Pull Requests bitte gegen `develop`, mit grüner CI (PHPStan Level 8, PHP-CS-Fixer, Unit- und Acceptance-Tests).

## Lizenz

GPL-2.0-or-later. Siehe [LICENSE](LICENSE).

## Support

[NET.THINKS](https://www.netthinks.com) · Dietmar Engler · Villingen-Schwenningen
Kommerzieller Support, Audits und Schulungen: <https://www.netthinks.com/leistungen/ki-kennzeichnung-typo3/>

---

## Rechtlicher Hinweis

Diese Extension ist ein technisches Hilfsmittel. Sie stellt keine Rechtsberatung dar und begründet keine Gewähr für die Einhaltung der KI-Verordnung oder anderer Rechtsvorschriften. Die inhaltliche Bewertung, ob und wie ein konkreter Inhalt zu kennzeichnen ist, obliegt dem Betreiber der Website.

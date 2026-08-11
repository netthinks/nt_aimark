# AI Mark — AI labelling for TYPO3

Record, label and document AI-generated content in TYPO3, in line with the
transparency obligations in Art. 50 of the EU AI Act (Regulation (EU)
2024/1689), which apply since 2 August 2026.

!!! warning "Not legal advice"
    This extension is a technical aid. It supports you in implementing the
    obligations and does not constitute legal advice. Whether and how a
    specific piece of content has to be labelled remains a decision for the
    operator in each individual case.

## What the extension does

**Record** — a dedicated "AI transparency" tab in the file metadata: degree of
AI involvement, system used, creation date, responsible person. Equivalent
fields for texts on pages, content elements and any table you configure.

**Suggest automatically** — on upload, existing provenance data is read: a
C2PA signature, the IPTC `DigitalSourceType` from XMP, and finally a signature
list over EXIF fields. Confirming it stays with a human.

**Label** — accessible frontend output using the official EU icons, with an
expandable detail panel. For texts, a sentence rather than an icon.

![An AI-generated image in the frontend carrying the official "AI GENERATED" icon in the bottom right, with the "Details on AI use" button below it](assets/frontend-badge.png)

**Evidence** — a backend module showing how far the review has come and what
is still open, plus a trail of every status change.

![The "AI transparency" backend module with ring charts for review progress and distribution](assets/backend-module.png)

## What it deliberately does not do

- **No detection of AI in foreign images.** Heuristic detectors are
  unreliable, and a false positive would be a false statement about a piece of
  content.
- **No labelling without human confirmation.** An automatic finding is always
  a suggestion. "This content is AI generated" is a claim, and a person makes
  it.
- **No guarantee of legal compliance.** For assessing an individual case,
  please seek legal advice.

## Requirements

| | |
|---|---|
| TYPO3 | 13.4 LTS, 14 LTS |
| PHP | 8.2 – 8.4 |
| Optional | `c2patool` for Content Credentials |

## Quick start

```bash
composer require netthinks/nt-aimark
vendor/bin/typo3 extension:setup
```

Then add the `AI Mark` site set — see [Installation](installation.md).

## Part of the NET.THINKS AI suite

| Extension | Purpose |
|---|---|
| `nt_ai` | AI integration: alt texts, accessibility audits |
| `nt_lingua` | Translation, plain language, l10n overlays |
| **`nt_aimark`** | AI transparency and labelling |

With `nt_ai` or `nt_lingua` installed, they can report what they generate —
see [Integration](integration.md).

## Who is behind this

`nt_aimark` is built by **NET.THINKS**, a TYPO3 agency in
Villingen-Schwenningen, Germany. The core package is free
(GPL-2.0-or-later) and complete — it is not a trial version of something
bigger.

Beyond it there are two things some people need and many do not:

**The add-on package `nt_aimark_pro`** hooks into the documented
[extension points](integration.md) and adds what the free package
deliberately leaves out because not everyone needs it: burning the icon into
the image file, post-processing finished HTML for grown templates, audit
evaluation and export, a transparency statement, and a hosted service for
checking Content Credentials.

**Help with the rollout** — taking stock, classifying content, setting things
up. Anyone wanting to do it themselves will find everything needed in these
pages; that is explicitly the normal case.

- Website: <https://www.netthinks.com/leistungen/websites/ki-kennzeichnung-typo3/>
- Questions, bugs, wishes: <https://github.com/netthinks/nt_aimark/issues>

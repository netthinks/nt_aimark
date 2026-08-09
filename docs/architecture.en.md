# Architecture

This page describes how the extension is put together and where a decision is
actually made. It is meant for anyone following the flow or hooking into it —
not only for developers.

## The underlying idea

The extension keeps three things apart that are easily conflated:

| | |
|---|---|
| **What is known about a file?** | Recording — metadata fields, automatic detection, reports from sibling extensions |
| **What follows from it?** | Rules — a single class decides whether and how to label |
| **What does it look like?** | Output — ViewHelpers, icon, detail layer |

That separation is why automatic detection can never produce a label without a
person agreeing to it: recording and deciding are different steps, and the
transition between them is exactly one status change.

## Overview

```mermaid
flowchart TB
    subgraph Recording
        upload[File upload] --> extract[ProvenanceExtractorService]
        extract --> c2pa[C2paService<br/>Content Credentials]
        extract --> xmp[XmpReaderService<br/>IPTC DigitalSourceType]
        extract --> exif[ExifSignatureService<br/>signature list]
        event[AiContentGeneratedEvent<br/>from nt_ai / nt_lingua] --> listener[AiContentGeneratedListener]
        form[&quot;AI transparency&quot; tab<br/>in the backend] --> meta
    end

    c2pa --> meta[(sys_file_metadata<br/>tx_ntaimark_*)]
    xmp --> meta
    exif --> meta
    listener --> meta

    meta --> decl[AiDeclaration<br/>value object]
    decl --> rules{{DisclosureRuleService}}
    settings[Site set<br/>settings] --> rules
    rules --> decision[LabelDecision]

    decision --> vh[ViewHelpers<br/>aiFigure / aiLabel / hasLabel]
    vh --> render[LabelRenderService]
    icons[IconResolverService<br/>EU icons] --> render
    contrast[BadgeContrastService<br/>brightness of the image] --> render
    render --> out([Label in the frontend])

    meta -.every change.-> audit[(tx_ntaimark_audit<br/>trail)]
    decision -.-> audit
```

Dotted lines are evidence: every status change lands in the trail, whichever
route produced it.

## The decision path

The rules work through a fixed order. The first rule that applies wins and
leaves a machine-readable reason code in the trail.

```mermaid
flowchart TD
    start([AiDeclaration]) --> r1{Manually<br/>exempted?}
    r1 -->|yes| no1[no label<br/>manual_exempt]
    r1 -->|no| r2{Created before<br/>2 Aug 2026?}
    r2 -->|yes| no2[no label<br/>pre_cutoff]
    r2 -->|no| r3{Unreviewed or<br/>unconfirmed suggestion?}
    r3 -->|yes| no3[no label<br/>unreviewed<br/>counts as an open item]
    r3 -->|no| r4{No AI involved?}
    r4 -->|yes| no4[no label<br/>no_ai]
    r4 -->|no| r5{Manually<br/>forced?}
    r5 -->|yes| yes1[label<br/>manual_forced]
    r5 -->|no| r6{AI generated or<br/>AI modified?}
    r6 -->|yes| yes2[label<br/>rule_default]
    r6 -->|no| r7{Origin<br/>unknown?}
    r7 -->|yes| cfg[per setting<br/>unknown_origin]
```

Two things about it are deliberate rather than incidental:

**Rule 2 only applies with a date set.** An empty creation date explicitly does
**not** count as "before the cutoff". Otherwise every incomplete record would
turn itself into an exemption.

**Rule 3 comes before anything that could produce a label.** A suggestion from
automatic detection never renders a label in the frontend. "This content is AI
generated" is a legal assertion and needs a human to release it.

## Where the data lives

```mermaid
erDiagram
    sys_file ||--o| sys_file_metadata : "has metadata"
    sys_file_metadata {
        int tx_ntaimark_status "classification, 0-5"
        int tx_ntaimark_disclosure "automatic / forced / exempt"
        string tx_ntaimark_exempt_reason
        string tx_ntaimark_icon
        string tx_ntaimark_system
        string tx_ntaimark_vendor
        int tx_ntaimark_created_at "drives the cutoff rule"
        int tx_ntaimark_reviewer
        int tx_ntaimark_c2pa_state
        string tx_ntaimark_source_type "IPTC DigitalSourceType"
    }
    pages ||--o{ tx_ntaimark_audit : "recorded in"
    tt_content ||--o{ tx_ntaimark_audit : "recorded in"
    sys_file_metadata ||--o{ tx_ntaimark_audit : "recorded in"
    tx_ntaimark_audit {
        int tstamp
        string table_name
        int record_uid
        string be_user_name "denormalised"
        string action
        string field_name
        text old_value
        text new_value
        string source "manual / auto_detect / nt_ai / cli"
    }
```

The trail is **append-only**: the application adds to it and never changes or
deletes anything. The user name is written along rather than referenced, so
the record stays readable once the backend user is deleted.

Text in `pages` and `tt_content` carries its own fields
(`tx_ntaimark_text_status`, `tx_ntaimark_public_interest`,
`tx_ntaimark_editorial_control`, `tx_ntaimark_responsible`); further tables can
be added in the extension settings.

## Two routes by which a change reaches the trail

This is the least conspicuous part of the architecture and the one where
something is most likely to go missing.

```mermaid
flowchart LR
    api[Written through the FAL API<br/>detection, CLI, bulk edit] --> ev[AfterFileMetaDataUpdatedEvent]
    form[Saving the metadata form] --> hook[DataHandler hook]
    ev --> rec[MetaDataAuditRecorder]
    hook --> rec
    rec --> audit[(tx_ntaimark_audit)]
```

The PSR-14 event fires **only** for writes through the FAL API. An editor
saving the form does not trigger it — TYPO3 v14 offers no comparable event for
record updates, hence the additional DataHandler hook. Both routes end in the
same class, and the previous value comes from the trail itself, so nothing is
recorded twice when both routes see the same change.

## Frontend output

```mermaid
sequenceDiagram
    participant T as Fluid template
    participant V as AiFigureViewHelper
    participant R as DisclosureRuleService
    participant L as LabelRenderService
    participant B as BadgeContrastService
    participant I as IconResolverService

    T->>V: nt:aiFigure with file
    V->>R: apply the rules
    R-->>V: LabelDecision
    alt no label needed
        V-->>T: image unchanged
    else label
        V->>L: renderBadge(image markup)
        L->>B: brightness behind the icon
        B-->>L: black or white
        L->>I: icon variant
        I-->>L: SVG, colours as attributes
        L-->>V: figure with frame, icon, detail layer
        V-->>T: labelled image
    end
```

Three decisions the result does not show:

**The ViewHelper can be set unconditionally.** Where the rules produce no
label, it returns the image untouched — editors need not know which images are
affected.

**The icon sits in a frame of its own around the picture**, not in the whole
`figure`. Otherwise it would come to rest below the image, and the contrast
decision made from the image's brightness would apply to something else.

**The SVG carries its colours as attributes**, not in a `<style>` block. A
Content Security Policy with a nonce for `style-src-elem` — the default in
TYPO3 v14 — drops inline stylesheets without a word, and the official icon
would render as a solid black shape.

With the EU icons missing entirely, a text label appears instead of an empty
element. See [Installation](installation.md).

## Backend module

![The "AI transparency" module: two rings for review progress and distribution, below them the storage overview, system status and the filtered work list](assets/backend-module.png)

```mermaid
flowchart LR
    repo[TransparencyRepository] --> kpi[Figures and rings]
    repo --> list[Work list<br/>filtered, paged]
    status[SystemStatusCheck] --> panel[System status]
    list --> bulk[Bulk edit]
    bulk --> audit[(Trail)]
    bulk --> fal[FAL metadata]
```

The repository keeps automatically produced format variants
(`photo.jpg.webp`, `photo.jpg.avif`) out of every count — they show the same
picture as the original and would distort both the work list and the reviewed
percentage. Switchable via the `hideDerivedFormats` setting.

`SystemStatusCheck` reports what can be missing at runtime: EU icons,
`c2patool`, the `exif` PHP extension, and a GFX configuration that destroys
metadata.

## Extension points

This repository is the free core package. Additional features hook in through
defined points without patching it — and without any licence or activation
logic in the code.

```mermaid
flowchart TB
    core[Core package nt-aimark]
    core --> m[LabelDecisionModifierInterface<br/>adjust a decision afterwards]
    core --> e1[AfterLabelDecisionEvent<br/>observe]
    core --> e2[AfterStatusChangedEvent<br/>react to status changes]
    core --> ic[IconCompositorInterface<br/>burn the icon into the image]
    core --> mw[Middleware slot<br/>label-injection]
    core --> res[ProcessedFileDeclarationResolver<br/>image path to declaration]
    core --> aud[AuditService public<br/>write your own trail entries]
```

All are marked `@api` and described individually in
[Integration](integration.md).

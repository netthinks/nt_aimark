# Integration with other extensions

Anything that generates AI content can report it to `nt_aimark`. The labelling
then comes about with no extra editorial effort — a person only confirms it.

The coupling is a single event. **No hard dependency in either direction**: if
`nt_aimark` is not installed, the event goes nowhere and the reporting
extension never notices.

## The event

```php
use NetThinks\NtAimark\Event\AiContentGeneratedEvent;

$this->eventDispatcher->dispatch(new AiContentGeneratedEvent(
    tableName: 'sys_file',
    recordUid: $file->getUid(),
    aiSystem: 'DALL·E 3',
    aiVendor: 'OpenAI',
    contentKind: AiContentGeneratedEvent::KIND_IMAGE,
    fullyGenerated: true,
    prompt: $prompt,
    generatedAt: time(),
    source: 'nt_ai',
));
```

| Field | Meaning |
|---|---|
| `tableName` | `sys_file`, `sys_file_metadata`, `pages`, `tt_content`, … |
| `recordUid` | uid in that table |
| `aiSystem` | Product name, e.g. "DALL·E 3" |
| `aiVendor` | Vendor, e.g. "OpenAI" |
| `contentKind` | `image`, `audio`, `video`, `text` or `alt_text` |
| `fullyGenerated` | `true` = fully generated, `false` = AI-assisted change |
| `prompt` | Optional, internal documentation — **never** rendered in the frontend |
| `generatedAt` | Optional creation time (drives the cutoff rule) |
| `source` | Optional, which extension is reporting — goes into the trail |

The event is marked `@api` and stays stable within a major version.

## What happens with it — and what deliberately does not

Three decisions that are not obvious at first glance:

### Media stays a suggestion

Even a first-hand report sets the status to **"Suggestion"**, not to "AI
generated". The public statement "this image is AI generated" remains a human
decision, exactly as with automatic detection. The matching icon is
pre-filled from `fullyGenerated`, so confirming is one click.

A record a person has already settled is **not** overwritten.

### Text is recorded as fact

For text the situation differs: the reporting extension wrote the text itself,
which is knowledge rather than a guess. `tx_ntaimark_text_status` is therefore
set directly.

That still produces no disclosure on its own — for that it additionally needs
"matter of public interest", and only a person sets that. See
[Rules](rules.md).

### An alt text changes nothing about the image

`KIND_ALT_TEXT` is recorded but leaves the AI status of the image **alone**.
An alt text describes a picture and says nothing about how the picture came
about. Treating it otherwise would suddenly mark every image with an
AI-written alt text as "AI generated" — a false statement about the content,
and a frequent one at that.

## Trail

Every report lands in `tx_ntaimark_audit` with the action `reported` and the
source from the `source` field (`nt_ai`, `nt_lingua`, otherwise `import`).

## For nt_ai and nt_lingua

Sensible places to dispatch:

| Extension | Place | `contentKind` |
|---|---|---|
| `nt_ai` | Alt-text generation for a FAL image | `alt_text` |
| `nt_ai` | AI-generated text in a content element | `text` |
| `nt_lingua` | Translation or plain-language transformation | `text`, `fullyGenerated: false` |

For `nt_lingua`, `fullyGenerated: false` is the normal case: a translation is
an AI modification of an existing text, not a new creation.

---

# Extension points

This repository is the **free core package**. It is complete on its own — no
locked feature, no licence key, no upgrade hint anywhere in the interface.
Additional features arrive as a **separate Composer package** and hook in
through the points below, without patching this one.

Everything listed here is `@api` and stays stable within a major version.

## Adjusting a labelling decision

```php
use NetThinks\NtAimark\Service\LabelDecisionModifierInterface;

final class MyModifier implements LabelDecisionModifierInterface
{
    public function modify(AiDeclaration $declaration, LabelDecision $decision): LabelDecision
    {
        return $decision;
    }
}
```

Register with the tag `nt_aimark.label_decision_modifier`; with `autoconfigure`
on, implementing the interface is enough. Several modifiers run in registration
order, each seeing what the previous one produced.

The rules in `DisclosureRuleService` remain the single place the reasoning
lives — a modifier refines it, it does not replace it.

## Events

| Event | When | Purpose |
|---|---|---|
| `AfterLabelDecisionEvent` | after every decision | Observing (counting, logging). To change the outcome, use the modifier. |
| `AfterStatusChangedEvent` | on every status change | Reacting: notify a service, invalidate a cache, feed a dashboard |

`AfterStatusChangedEvent` is dispatched from the audit trail and therefore
covers **every** route: the form, bulk editing, automatic detection, the CLI
and reports from sibling extensions. If it is in the trail, this event fired.

`AfterLabelDecisionEvent` fires once per resolved file — many times on a page
with many images. Keep listeners cheap.

## Burning the icon into the image

`IconCompositorInterface` is registered and passes through unchanged by default
(`NullIconCompositor`). A second package overrides the service alias and takes
over.

## Post-processing finished HTML

For grown projects whose templates cannot carry the ViewHelpers. Two building
blocks are provided:

- **A named place in the middleware chain**:
  `netthinks/nt-aimark/label-injection`. The core package passes through
  unchanged; overriding `LabelInjectorInterface` as a service alias takes over,
  with no middleware registration of your own.
- **`ProcessedFileDeclarationResolver`**: given an image path from the finished
  HTML, finds the matching `AiDeclaration` — including scaled variants, through
  `sys_file_processedfile`.

The method is called `apply()` rather than `inject()` on purpose: Symfony
treats methods whose name starts with "inject" as setter injection, and the
container then refuses to build the service.

## Writing to the audit trail

`AuditService` is `public: true`, so a second package can add its own entries.
The evaluation view (audit log, export) is deliberately not part of the core
package — but writing happens here in full, so the data is complete.

## Configuration keys

Keys belonging to an additional package live in their own namespace
(`aimarkPro.*`) and do not appear in the core package.

## Why there is no licence check

TYPO3 extensions using core API are derivative works and therefore
GPL-2.0-or-later. Selling is allowed; forbidding redistribution is not. A
runtime lock would be legally contestable — and the wrong foundation for a
product whose selling point is legal compliance. A test in this repository
asserts that no licence or activation logic appears in the code.

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

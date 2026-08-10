# Rules

The `DisclosureRuleService` answers the one question the extension exists for:
*does this file get a label, and which one?* The order of the checks is
load-bearing.

## Order of checks — media

The first matching rule decides.

| # | Condition | Result | Reason code |
|---|---|---|---|
| 1 | Labelling = "Do not label" | no label | `manual_exempt` |
| 2 | Created before 2 August 2026 | no label | `pre_cutoff` |
| 3 | AI involvement = Not reviewed **or** Suggestion | no label, counted as open | `unreviewed` |
| 4 | AI involvement = No AI involved | no label | `no_ai` |
| 5 | Labelling = "Always label" | label | `manual_forced` |
| 6 | AI involvement = AI generated or AI modified | label | `rule_default` |
| 7 | AI involvement = Origin unknown | configurable, off by default | `unknown_origin` |

The reason code goes into the audit trail, so it stays reconstructible why a
label appeared — and why one did not.

## Why the order is what it is

**Rule 3 before anything that produces a label.** A suggestion from automatic
detection is a guess. Rendering it would present a guess as a finding. That is
why the "not confirmed yet" check sits ahead of every labelling branch.

**Rule 5 after rules 2 to 4.** "Always label" is an editorial reinforcement,
not a master key. It cannot override the cutoff, cannot label a file marked as
AI-free, and cannot publish an unreviewed record.

**Rule 7 switchable, off by default.** "Origin unknown" is a different claim
from "AI was involved". Equating them asserts something you do not know.

## An unset creation date is not an exemption

`0` means "not recorded", not "before the cutoff". Otherwise every file nobody
has dated would quietly be exempt from labelling.

## Which icon

1. If an editor picked an icon, that one applies — including the deliberate
   choice of "no icon" for a text-only label.
2. Otherwise it follows the AI involvement: generated → `AI GENERATED`,
   modified → `AI MODIFIED`, origin unknown → `AI`.
3. If the icon file is missing, text is rendered instead.

## What may appear in the detail panel

Only what a visitor may see: the system used with its vendor, and the creation
date. **The prompt and the internal note never reach the markup**; a test
guarantees it.

---

# Rules for texts

The obligation for texts is cut differently: it only covers matters of public
interest, and it falls away when a human reviewed the text **and** somebody is
named for that review.

| # | Condition | Result | Reason code |
|---|---|---|---|
| 1 | AI involvement = No AI involved | no notice | `no_ai` |
| 2 | Not a matter of public interest | no notice | `not_public_interest` |
| 3 | Reviewed **and** a person named | no notice | `editorial_control` |
| 4 | Reviewed, but nobody named | **notice** | `editorial_control_incomplete` |
| 5 | Otherwise | notice | `rule_default` |

## Why rule 4 comes out that way

The exception depends on somebody answering for the review. A tick box with no
name documents nothing. The text therefore keeps its notice, and the case is
recognisable as an incomplete exemption so the gap can be closed rather than
sitting there unnoticed. The TCA requires the name as soon as the box is
ticked.

## Why there is no cutoff rule for texts

For media the moment of creation decides. For texts the moment of publication
counts **as well** — and a text sitting on a website is being published
continuously. Turning that into an automatic exemption would be a legal
reading, and the extension does not make those. Editors have the fields to
record their own decision.

## What appears in the frontend

A sentence, not an icon: for a text, wording is what makes the disclosure
understandable at first contact. The responsible person is internal
documentation and does **not** appear in the frontend.

```html
{namespace nt=NetThinks\NtAimark\ViewHelpers}
<nt:textNotice record="{data}" table="tt_content" />
```

---

## What is recorded

Every change to the transparency fields lands in `tx_ntaimark_audit`, with the
old value, the new value, a timestamp, the user name and the source:

| Source | When |
|---|---|
| `manual` | Editing in the form, bulk editing in the backend module |
| `auto_detect` | Automatic detection on upload or replacement |
| `cli` | `aimark:verify` |
| `nt_ai`, `nt_lingua` | Reported through the integration event |

Two paths feed this: a PSR-14 listener for writes through the file API, and a
DataHandler hook for edits in the form. Both are needed — TYPO3 v14 dispatches
no PSR-14 event for record updates, and the FAL event does not fire on a form
save.

The previous value comes from the trail itself. That has a useful side effect:
a change the extension already recorded with context (`bulk_review`, say)
looks unchanged to the generic path and is not written a second time with less
meaning.

## Test coverage

The order is covered by a written-out matrix over every combination of AI
involvement × labelling mode × cutoff
(`Tests/Unit/Service/DisclosureRuleServiceTest.php`). The expectations are
spelled out rather than computed, so a changed rule order shows up as a
failing row instead of quietly moving along.

The same applies to texts in `TextDisclosureRuleServiceTest.php`. A test there
additionally checks that every combination of the three persisted flags really
appears in the matrix — a row count on its own says nothing about that.

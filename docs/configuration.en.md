# Configuration

All site-level settings go through the `AI Mark` site set. They can be set per
site — in the backend under *Sites → Setup → Settings*, or in
`config/sites/<identifier>/settings.yaml`.

## Site settings

| Key | Type | Default | Meaning |
|---|---|---|---|
| `ntAimark.labelUnknownOrigin` | bool | `false` | Whether files of unknown origin are labelled |
| `ntAimark.useFileRenderer` | bool | `true` | Label audio and video without template changes |
| `ntAimark.showDetails` | bool | `true` | Expandable detail panel below the icon |
| `ntAimark.badgePosition` | string | `bottom-right` | `top-left`, `top-right`, `bottom-left`, `bottom-right` |
| `ntAimark.badgeSize` | string | `medium` | `small`, `medium`, `large` |
| `ntAimark.showTextLabel` | bool | `true` | Wording in the site language beside the icon |

```yaml
ntAimark:
  labelUnknownOrigin: false
  useFileRenderer: true
  showDetails: true
  badgePosition: bottom-right
  badgeSize: medium
  showTextLabel: true
```

### `labelUnknownOrigin`

Off by default, for a substantive reason: "origin unknown" is a different
claim from "AI was involved". A label would assert the second. Switch it on
only if you deliberately want to make that statement about your library.

### `useFileRenderer`

Labels audio and video output without any template change. Switch it off where
your templates already place the ViewHelpers, or the label appears twice.

!!! info "Images are deliberately not covered by the file renderer"
    TYPO3 ships no file renderer for images; `f:media` falls back to its own
    private rendering. An image renderer would have to reimplement that
    output — crop variants, focus area, `loading`, `decoding`, alternative
    text — and maintain the copy against every core release. For images the
    ViewHelpers are the intended route.

## Extension settings

Server-level rather than site-level: where the external tooling is and how
long it may take. Backend → *Settings → Extension Configuration → nt_aimark*.

| Key | Default | Meaning |
|---|---|---|
| `c2patoolPath` | `c2patool` | Path to the binary; leave as is if it is on the `PATH` |
| `c2patoolTimeout` | `15` | Time limit in seconds |
| `additionalExifSignatures` | empty | Extra EXIF signatures, comma-separated as `needle=vendor` |
| `preserveMetadata` | on | Restore the XMP packet in processed images |
| `additionalTextTables` | empty | Further tables carrying the text fields |

### `showTextLabel`

The official icons carry the English wordmark "AI", "AI GENERATED" or "AI
MODIFIED". **They cannot be translated**: the Commission publishes them in
three variants and four colour versions, in no other language, and permits no
change to the wording. That is precisely the point — a mark that looks the
same across the Union is recognised the way a road sign is. A version of one's
own would be a lookalike, not the official icon.

The meaning therefore travels in the text beside it. This setting places the
wording in the site's language next to the icon — "AI generated", "AI
modified", "AI" — translatable through XLIFF like any other text in the
extension. The Code of Practice explicitly recommends such an accompanying
label, in plain language and without abbreviations other than "AI".

A caption entered on the file itself takes precedence. With the icon files
absent the text appears on its own anyway, and is not then set twice.

The wording gets a ground of its own, light or dark to match the chosen icon
variant. That is not a matter of taste: the brightness measurement covers the
small area behind the icon, and the text reaches beyond it — without a ground
it would sit over pixels nobody measured.

## ViewHelpers

| ViewHelper | Purpose |
|---|---|
| `nt:aiFigure` | Wraps image markup in a `<figure>` with the label |
| `nt:aiLabel` | Renders only the label |
| `nt:hasLabel` | Returns `true`/`false` for your own branching |
| `nt:textNotice` | Disclosure notice for a text record |

```html
<nt:aiLabel file="{file}" position="top-left" size="small" showDetails="false" />
<nt:textNotice record="{data}" table="tt_content" />
```

### Further tables for texts

The fields ship on `pages` and `tt_content`. Additional tables are added in
the extension settings under *Texts*, comma-separated:

```
tx_news_domain_model_news, tx_blog_domain_model_post
```

Update the database schema afterwards (`vendor/bin/typo3 extension:setup`).
Only plausible table names are accepted; anything else is dropped rather than
reaching DDL or TCA.

## Overriding the markup

The output comes from a Fluid template
(`Resources/Private/Templates/Label/Badge.html`). You can override it in your
own sitepackage without touching PHP.

If you replace the markup, keep these four points — they are not a matter of
taste:

- The icon carries a text alternative (`role="img"` with `aria-label`), or
  visible text sits next to it.
- The toggle is a `<button>` with correct `aria-expanded` and `aria-controls`;
  the panel `id` is unique within the document.
- The label does not move the image.
- The contrast of the icon does not depend on the picture underneath.

## Contrast of the label

The extension measures the area of the image where the icon sits and decides
how to draw the badge:

| Case | Result | CSS class |
|---|---|---|
| Area measurable, chosen icon colour clears 4.5:1 at **every** sample | icon without a plate, black or white to suit | `nt-aimark__badge--plain` |
| Busy area, unreadable image, no GD, image too large | icon on an opaque plate | `nt-aimark__badge--plate` |

Contrast therefore never depends on chance: the plate is the guarantee,
dropping it is the exception. Every failure path leads back to the plate.

The measured area is the quarter of the image where the icon actually sits, so
`badgePosition` steers what is examined. The result is cached under `ntaimark`,
keyed by the file's content hash; a replaced image is measured again.

!!! note "A quirk of contrast maths"
    Against a **uniform** area, at least one of the two icon colours always
    reaches 4.5:1, because 4.5 × 4.5 is less than the maximum contrast range
    of 21. The plate is therefore only needed for busy areas and for images
    that cannot be measured.

## Your own CSS

The bundled `EXT:nt_aimark/Resources/Public/Css/aimark.css` is deliberately
lean and carries no colour scheme of its own. To replace it, load your own
file and override the classes — the AssetCollector entry is called `ntAimark`.

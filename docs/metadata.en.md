# Metadata and image processing — what was measured

This is the finding on the question that counted as the project's biggest
technical risk: **does the machine-readable marking survive TYPO3's image
processing?**

Short answer: **XMP yes, if you do something about it. C2PA no — and rightly
so.**

## Starting point

TYPO3 strips all profiles when scaling by default. The relevant settings are
in `$GLOBALS['TYPO3_CONF_VARS']['GFX']`:

| Setting | Default |
|---|---|
| `processor_stripColorProfileByDefault` | `true` |
| `processor_stripColorProfileParameters` | `['+profile', '*']` |

`+profile '*'` removes every embedded profile — colour profile, EXIF, IPTC
**and XMP**.

## Measurement

Measured with `Build/experiments/metadata-survival.php`. The script pushes a
file through the same conversions TYPO3 invokes, once with and once without
the strip parameter, for both processors.

Environment: ImageMagick 7.1.1-43, GraphicsMagick 1.4 (snapshot 2025-03-31),
c2patool 0.27.7, Debian in the DDEV web container.

Input: a JPEG carrying XMP `Iptc4xmpExt:DigitalSourceType` **and** a valid,
signed C2PA manifest (`validation_state: Valid`).

| Processing | XMP | C2PA |
|---|---|---|
| Source file | present | **Valid** |
| ImageMagick, with strip (default) | **gone** | gone |
| ImageMagick, without strip | present | gone |
| GraphicsMagick, with strip (default) | **gone** | gone |
| GraphicsMagick, without strip | present | **Invalid** |
| ImageMagick with strip, XMP restored afterwards | **present** | gone |

## What follows from that

### XMP does not survive, but it can be restored

With the default configuration the XMP packet is gone after the first
`f:image`. Two ways back:

1. **Switch the strip off globally.** It works, but it is a blunt instrument:
   colour profiles and EXIF then stay in every derived file, bloating them.
   The setting exists for a reason.
2. **Restore the packet selectively.** That is the route the extension takes.
   The APP1 segment is written back into the derived JPEG after processing;
   TYPO3's strip configuration is left alone.

The second route is measured: XMP present again, image still readable.

### C2PA does not survive — and must not

This is the more important part of the finding.

A C2PA signature cryptographically confirms that the image data is unchanged.
After scaling it necessarily no longer matches. The measurement shows this
plainly: **GraphicsMagick without the strip parameter does carry the manifest
across — and the result validates as `Invalid`.** The derived file then claims
of itself that it has been tampered with.

Copying a manifest unchanged would therefore not merely be useless, it would
be a false statement about the file. The extension therefore **never** carries
C2PA over. A test holds that in place.

The clean alternative would be to create a **new** manifest for the derived
file, declaring the scaling as an editing step. That requires a signing
certificate and is not part of v1.0.

### For the product description

- Acceptable: "The relevant XMP data is preserved in scaled images."
- **Not acceptable**: "Content Credentials are preserved." They are not, and
  no setting changes that.

Worth keeping in perspective: the visible label under Art. 50(4) needs **no
metadata at all**. It comes from the database, not from the image file.
Preserving metadata is a bonus for the machine-readable layer, not the
foundation of the labelling.

## Limits of the implementation

- **JPEG only.** Restoring XMP in PNG or WebP means rewriting container
  chunks — a different job. For other formats the service declines rather than
  damaging the file.
- **Switchable** through the extension setting *Restore XMP in processed
  images*.
- The write goes to a temporary file first; only once the result still parses
  as an image does it replace the target. A preview a browser cannot display
  would be far worse than a missing XMP packet.
- If the processed file already carries a packet, no second one is added.

## Repeating the measurement

```bash
php Build/experiments/metadata-survival.php
php Build/experiments/metadata-survival.php --input=/path/to/signed-image.jpg
```

Without `--input` the script generates a JPEG with XMP. The C2PA column needs
a signed file; signing itself requires a certificate and is not part of the
script.

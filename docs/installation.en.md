# Installation

## Requirements

| | |
|---|---|
| TYPO3 | 13.4 LTS or 14 LTS |
| PHP | 8.2 – 8.4 |
| Optional | `c2patool` for reading Content Credentials |

## Install the extension

```bash
composer require netthinks/nt-aimark
vendor/bin/typo3 extension:setup
vendor/bin/typo3 cache:flush
```

`extension:setup` creates the fields in `sys_file_metadata`, `pages` and
`tt_content`, and the audit table `tx_ntaimark_audit`.

## Add the site set

In `config/sites/<identifier>/config.yaml`:

```yaml
dependencies:
  - netthinks/nt-aimark
```

Only then are the settings described under [Configuration](configuration.md)
available.

## The EU icons

**They ship with the package.** Nothing to download, nothing to rename — the
twelve official icons of the European Commission are part of it, as SVG for
frontend output and as PNG for burning them into image files with
`nt_aimark_pro`.

The Commission provides them free of charge and without an attribution
requirement; they are an integral part of Section 2 of the Code of Practice on
marking and labelling AI-generated content. Origin and terms are recorded in
`Resources/Public/Icons/Eu/README.md`.

They must not be redrawn, recoloured or translated — and it would make no
sense either: a mark that looks the same across the Union is recognised. A
version of one's own would be a lookalike.

Should the files ever be missing because somebody removed them, **the
extension carries on** and renders a text label instead of the icon ("AI
generated", "AI modified", "AI"). The system status in the backend module
reports it. Nothing errors and no empty image appears.

## Optional: c2patool for Content Credentials

Without this tool the extension reads provenance data from XMP and EXIF only,
and records the C2PA state as "could not be verified". With it, signed C2PA
manifests are read as well — the only source that carries a cryptographically
backed statement rather than a self declaration.

**Reading and verifying needs no certificate**; only writing manifests would,
and this extension never writes any.

Prebuilt binaries for Linux, macOS and Windows:
<https://github.com/contentauth/c2pa-rs/releases> (filter for `c2patool`).

In a DDEV project one entry in `.ddev/web-build/Dockerfile` is enough:

```dockerfile
ARG C2PATOOL_VERSION=0.27.7
RUN curl -fsSL "https://github.com/contentauth/c2pa-rs/releases/download/c2patool-v${C2PATOOL_VERSION}/c2patool-v${C2PATOOL_VERSION}-x86_64-unknown-linux-gnu.tar.gz" \
        -o /tmp/c2patool.tar.gz \
    && tar xzf /tmp/c2patool.tar.gz -C /tmp \
    && mv /tmp/c2patool/c2patool /usr/local/bin/c2patool \
    && chmod +x /usr/local/bin/c2patool \
    && rm -rf /tmp/c2patool /tmp/c2patool.tar.gz
```

If the tool is not on the `PATH`, set its path in the extension settings under
*Detection*.

## Bring the label into your templates

In the Fluid template of a page or content element:

```html
{namespace nt=NetThinks\NtAimark\ViewHelpers}

<nt:aiFigure file="{file}">
    <f:image image="{file}" width="800" />
</nt:aiFigure>
```

The ViewHelper returns the image untouched when the rules say the file is not
to be labelled, so it can be applied unconditionally.

CSS and JavaScript are only loaded when a label is actually rendered.

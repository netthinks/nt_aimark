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

## Add the EU icons

The three official icons published by the European Commission are **not** part
of the repository. They are free to use without attribution, but they must not
be redrawn or generated — only the original files are the original files.

Download them from:
<https://digital-strategy.ec.europa.eu/en/policies/eu-icons-labelling-ai-generated-content>

Place the SVG files under `Resources/Public/Icons/Eu/`, named like this:

```
ai-basic-black.svg          ai-basic-white.svg
ai-basic-black-50.svg       ai-basic-white-50.svg
ai-generated-black.svg      ai-generated-white.svg
ai-generated-black-50.svg   ai-generated-white-50.svg
ai-modified-black.svg       ai-modified-white.svg
ai-modified-black-50.svg    ai-modified-white-50.svg
```

!!! info "Missing icons are not an error"
    Without the files the extension keeps working and renders a text label
    instead ("AI generated", "AI modified", "AI"). There is no error and no
    empty image.

!!! warning "Composer installs overwrite the directory"
    Installed through Composer, the icon directory sits below `vendor/`. Keep
    the files somewhere else and copy them in during deployment, or they are
    gone after the next `composer install`.

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

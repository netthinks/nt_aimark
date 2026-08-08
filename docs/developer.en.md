# Developer guide

## Install dependencies

The extension carries its own `composer.json`; the vendor directory is
`.Build/vendor`.

```bash
composer install
```

## Checks

```bash
composer ci:php:cs         # PHP-CS-Fixer, PER-CS 2.0
composer fix:php:cs        # ... and fix automatically
composer ci:php:stan       # PHPStan level 8, no baseline
composer ci:tests:unit
composer ci:tests:functional
```

Functional tests need a database:

```bash
typo3DatabaseDriver=pdo_mysql \
typo3DatabaseHost=db typo3DatabasePort=3306 \
typo3DatabaseUsername=root typo3DatabasePassword=root \
typo3DatabaseName=func_aimark \
composer ci:tests:functional
```

Accessibility tests (Playwright + axe-core, WCAG 2.1 AA):

```bash
npm ci
npx playwright install chromium
npx playwright test
```

They run against a static fixture at
`Tests/Acceptance/fixtures/labelled-page.html` and therefore need neither a
database nor a TYPO3 boot. So that this proves something, the functional test
`MarkupFixtureTest` checks that the fixture still contains exactly the markup
`LabelRenderService` produces — change the Fluid template and it fails until
the fixture follows.

The fixture is served by a small Node server (`Tests/Acceptance/server.mjs`),
not over `file://`: browsers refuse to load ES modules from `file://`, the
page would come up without its JavaScript, and the keyboard tests would pass
for the wrong reason.

## CLI

```bash
vendor/bin/typo3 aimark:scan --dry-run --limit=50
vendor/bin/typo3 aimark:scan --storage=1 --force
vendor/bin/typo3 aimark:report --limit=25 --email=editors@example.com
vendor/bin/typo3 aimark:verify --dry-run
```

All three are schedulable. `--force` on the scan refreshes existing
suggestions and leaves confirmed records alone — detection may not overrule a
person, whatever options it is given.

## Conventions

- **Code style:** PER-CS 2.0 (`(int) $value` with a space). It differs from
  the TYPO3 CGL but is what CI enforces — do not write against it.
- **Language:** classes, variables and comments in English. Labels and the
  German documentation in German.
- **XLIFF:** `locallang*.xlf` is the English source language, `de.locallang*.xlf`
  the translation. That is the TYPO3 convention; a file named
  `en.locallang*.xlf` would never be loaded, since TYPO3 resolves `en` as the
  default language.
- **Comments** explain the why, not the what.
- **No XCLASS overrides**, no deprecated core API.

## Pitfalls already encountered

- `displayCond` is evaluated server-side only in TYPO3 v14. Dependent fields
  appear after saving, not while choosing in the form.
- Tables **without TCA** get no `uid` and no primary key from the schema
  migrator. `tx_ntaimark_audit` therefore declares both explicitly in
  `ext_tables.sql`.
- `EXT:` paths are not resolvable in unit tests, because no package is
  registered. `IconResolverService` therefore takes its directory through the
  constructor.
- Fluid's `f:translate` needs the request attributes `applicationType` (as an
  `int`, not an enum case), `site` and `language`. Functional tests have to
  build such a request.
- Fluid resolves `{item.labelKey}` through `getLabelKey()`. An enum method
  named `labelKey()` renders as empty rather than failing, so values are
  prepared in PHP instead of handing enums to the template.
- Backend controllers need the `#[AsController]` attribute; DataHandler hooks
  need `public: true`, because they are created through `makeInstance`.
- An exception inside a DataHandler hook aborts the whole save. The audit call
  is wrapped for that reason: a gap in the trail is bad, an editor who cannot
  save at all is worse.

## Release

Before the tag:

1. Set the version in `ext_emconf.php`.
2. Add to `CHANGELOG.md` under the **concrete version number** — do not
   collect under `[Unreleased]`.
3. Update the affected documentation.

Then:

```bash
git tag -a v1.0.0 -m "Release 1.0.0"
git push origin v1.0.0
```

`.github/workflows/publish-ter.yml` takes over. It runs code style, static
analysis and unit tests first, then checks that the version in
`ext_emconf.php` matches the tag and that `CHANGELOG.md` has an entry for it,
and only then publishes through `typo3/tailor`.

### Once, before the first release

| Step | Where |
|---|---|
| Reserve the extension key `nt_aimark` | <https://extensions.typo3.org/> |
| Create an access token (scope "Extension write") | TYPO3 account → *Access Tokens* |
| Store it as the secret `TYPO3_API_TOKEN` | GitHub → *Settings → Secrets and variables → Actions* |

Without the secret the workflow stops with a clear message rather than
quietly doing nothing.

## Known notices

TYPO3 14.3 deprecates `ext_emconf.php`. The file stays as long as TYPO3 13.4
is supported. The notice appears when the package artifact is built, not on
every request.

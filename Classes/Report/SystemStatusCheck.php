<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Report;

use NetThinks\NtAimark\Domain\Enum\IconVariant;
use NetThinks\NtAimark\Service\C2paService;
use NetThinks\NtAimark\Service\ExtensionSettings;
use NetThinks\NtAimark\Service\IconResolverService;

/**
 * Tells the operator what is missing before anything silently degrades.
 *
 * Every optional dependency this extension has degrades quietly by design — a
 * missing icon becomes a text label, a missing binary becomes "not verifiable".
 * That is the right behaviour at runtime and the wrong one to leave
 * undocumented, so it is collected here.
 *
 * Pure computation, no rendering: the backend module and a future status
 * report use the same findings.
 */
final readonly class SystemStatusCheck
{
    public const SEVERITY_OK = 'ok';
    public const SEVERITY_NOTICE = 'notice';
    public const SEVERITY_WARNING = 'warning';

    private const LL = 'LLL:EXT:nt_aimark/Resources/Private/Language/locallang_mod.xlf:';

    public function __construct(
        private IconResolverService $iconResolver,
        private C2paService $c2paService,
        private ExtensionSettings $settings,
    ) {}

    /**
     * @return list<array{severity: string, titleKey: string, detailKey: string, detail: string, hintKey?: string, hintUrl?: string}>
     */
    public function findings(): array
    {
        return array_values(array_filter([
            $this->euIcons(),
            $this->c2patool(),
            $this->imageProcessing(),
            $this->exifExtension(),
        ]));
    }

    public function hasWarnings(): bool
    {
        foreach ($this->findings() as $finding) {
            if ($finding['severity'] === self::SEVERITY_WARNING) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{severity: string, titleKey: string, detailKey: string, detail: string}
     */
    private function euIcons(): array
    {
        $missing = $this->iconResolver->missingVariants();

        if ($missing === []) {
            return $this->ok('status.icons');
        }

        return [
            'severity' => self::SEVERITY_WARNING,
            'titleKey' => self::LL . 'status.icons',
            'detailKey' => self::LL . 'status.icons.detail',
            'detail' => implode(', ', array_map(
                static fn(IconVariant $variant): string => $variant->value,
                $missing,
            )),
        ];
    }

    /**
     * The most common finding on shared hosting, and the one an operator can
     * do least about.
     *
     * c2patool is a static Rust binary that still needs a glibc loader under
     * /lib64. Plenty of managed hosts do not have one, and no setting fixes
     * that — so the notice carries the way out with it instead of leaving the
     * reader with "not available". The link is a plain hint; the extension
     * works on without it, and an empty addOnInfoUrl removes it.
     *
     * @return array{severity: string, titleKey: string, detailKey: string, detail: string, hintKey?: string, hintUrl?: string}
     */
    private function c2patool(): array
    {
        if ($this->c2paService->isAvailable()) {
            return $this->ok('status.c2patool');
        }

        $finding = [
            'severity' => self::SEVERITY_NOTICE,
            'titleKey' => self::LL . 'status.c2patool',
            'detailKey' => self::LL . 'status.c2patool.detail',
            'detail' => $this->settings->c2patoolPath(),
        ];

        $url = $this->settings->addOnInfoUrl();

        if ($url !== '') {
            $finding['hintKey'] = self::LL . 'status.c2patool.hint';
            $finding['hintUrl'] = $url;
        }

        return $finding;
    }

    /**
     * TYPO3 strips profiles from processed images by default, which takes the
     * XMP packet with it. Either the restore is on, or the operator should
     * know the marking does not survive scaling.
     *
     * @return array{severity: string, titleKey: string, detailKey: string, detail: string}
     */
    private function imageProcessing(): array
    {
        $gfx = $GLOBALS['TYPO3_CONF_VARS']['GFX'] ?? [];
        $stripsProfiles = (bool) ($gfx['processor_stripColorProfileByDefault'] ?? true);

        if (!$stripsProfiles || $this->settings->preserveMetadata()) {
            return $this->ok('status.metadata');
        }

        return [
            'severity' => self::SEVERITY_WARNING,
            'titleKey' => self::LL . 'status.metadata',
            'detailKey' => self::LL . 'status.metadata.detail',
            'detail' => 'processor_stripColorProfileByDefault',
        ];
    }

    /**
     * @return array{severity: string, titleKey: string, detailKey: string, detail: string}
     */
    private function exifExtension(): array
    {
        return function_exists('exif_read_data')
            ? $this->ok('status.exif')
            : [
                'severity' => self::SEVERITY_NOTICE,
                'titleKey' => self::LL . 'status.exif',
                'detailKey' => self::LL . 'status.exif.detail',
                'detail' => 'ext-exif',
            ];
    }

    /**
     * @return array{severity: string, titleKey: string, detailKey: string, detail: string}
     */
    private function ok(string $titleKey): array
    {
        return [
            'severity' => self::SEVERITY_OK,
            'titleKey' => self::LL . $titleKey,
            'detailKey' => '',
            'detail' => '',
        ];
    }
}

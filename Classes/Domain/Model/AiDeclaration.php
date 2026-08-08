<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Model;

use NetThinks\NtAimark\Domain\AiActCutoff;
use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\C2paState;
use NetThinks\NtAimark\Domain\Enum\DisclosureMode;
use NetThinks\NtAimark\Domain\Enum\ExemptReason;
use NetThinks\NtAimark\Domain\Enum\IconVariant;

/**
 * Everything recorded about the AI involvement in a single media asset.
 *
 * Plain value object: it carries the editorial declaration, it does not decide
 * whether a label is shown. That decision belongs to DisclosureRuleService.
 */
final readonly class AiDeclaration
{
    public const FIELD_PREFIX = 'tx_ntaimark_';

    public function __construct(
        public string $tableName,
        public int $recordUid,
        public AiStatus $status = AiStatus::Unreviewed,
        public DisclosureMode $disclosure = DisclosureMode::Automatic,
        public ?ExemptReason $exemptReason = null,
        public IconVariant $icon = IconVariant::None,
        public string $labelText = '',
        public string $system = '',
        public string $vendor = '',
        public string $prompt = '',
        public int $createdAt = 0,
        public int $reviewer = 0,
        public int $reviewedAt = 0,
        public C2paState $c2paState = C2paState::None,
        public string $c2paManifest = '',
        public string $sourceType = '',
        public string $notes = '',
    ) {}

    /**
     * @param array<string, mixed> $record A sys_file_metadata row
     */
    public static function fromRecord(array $record, string $tableName = 'sys_file_metadata'): self
    {
        $get = static fn(string $field): mixed => $record[self::FIELD_PREFIX . $field] ?? null;

        return new self(
            tableName: $tableName,
            recordUid: (int) ($record['uid'] ?? 0),
            status: AiStatus::tryFrom((int) $get('status')) ?? AiStatus::Unreviewed,
            disclosure: DisclosureMode::tryFrom((int) $get('disclosure')) ?? DisclosureMode::Automatic,
            exemptReason: ExemptReason::tryFromDatabase((string) $get('exempt_reason')),
            icon: IconVariant::tryFrom((string) $get('icon')) ?? IconVariant::None,
            labelText: (string) $get('label_text'),
            system: (string) $get('system'),
            vendor: (string) $get('vendor'),
            prompt: (string) $get('prompt'),
            createdAt: (int) $get('created_at'),
            reviewer: (int) $get('reviewer'),
            reviewedAt: (int) $get('reviewed_at'),
            c2paState: C2paState::tryFrom((int) $get('c2pa_state')) ?? C2paState::None,
            c2paManifest: (string) $get('c2pa_manifest'),
            sourceType: (string) $get('source_type'),
            notes: (string) $get('notes'),
        );
    }

    /**
     * Content created before Art. 50 became applicable.
     */
    public function isPreCutoff(): bool
    {
        return AiActCutoff::isBefore($this->createdAt);
    }

    /**
     * The icon to use when a label is rendered: the explicit editorial choice
     * if there is one, otherwise the one matching the status.
     */
    public function effectiveIcon(): IconVariant
    {
        return $this->icon === IconVariant::None
            ? IconVariant::defaultForStatus($this->status)
            : $this->icon;
    }

    /**
     * True once a human has looked at the record and signed off on the status.
     */
    public function isReviewed(): bool
    {
        return $this->reviewedAt > 0 && !$this->status->requiresReview();
    }

    /**
     * Name and vendor of the AI system, for the detail panel — empty when
     * nothing was recorded.
     */
    public function systemLabel(): string
    {
        if ($this->system === '') {
            return $this->vendor;
        }

        return $this->vendor === '' ? $this->system : $this->system . ' (' . $this->vendor . ')';
    }
}

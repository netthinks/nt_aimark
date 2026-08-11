<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

use NetThinks\NtAimark\Domain\AiActCutoff;
use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\C2paState;
use NetThinks\NtAimark\Domain\Enum\ExemptReason;
use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use NetThinks\NtAimark\Domain\Model\ProvenanceResult;
use TYPO3\CMS\Core\Resource\File;

/**
 * Runs the three detection stages over a file and writes the outcome back as a
 * suggestion.
 *
 * Two properties matter more than anything this class does technically:
 *
 * - The result is always AiStatus::Suggested, never a confirmed status.
 *   "This content is AI generated" is a claim about the world and needs a
 *   human behind it.
 * - A record a human has already dealt with is never touched again.
 */
final readonly class ProvenanceExtractorService
{
    public function __construct(
        private C2paInspectorInterface $c2paService,
        private XmpReaderService $xmpReaderService,
        private ExifSignatureService $exifSignatureService,
        private AuditService $auditService,
    ) {}

    /**
     * Reads the file without writing anything.
     */
    public function extract(string $absolutePath): ProvenanceResult
    {
        // 1. A signed manifest is the only stage that carries a cryptographic
        //    statement rather than a self declaration.
        $c2pa = $this->c2paService->inspect($absolutePath);

        if ($c2pa->hasFinding()) {
            return $c2pa;
        }

        // 2. IPTC DigitalSourceType — standardised, but unsigned.
        $xmp = $this->xmpReaderService->read($absolutePath);

        if ($xmp->hasFinding()) {
            return $xmp->withC2pa($c2pa->c2paState, $c2pa->c2paManifest);
        }

        // 3. Whatever the producing tool happened to write into EXIF.
        $exif = $this->exifSignatureService->read($absolutePath);

        if ($exif->hasFinding()) {
            return $exif->withC2pa($c2pa->c2paState, $c2pa->c2paManifest);
        }

        return $c2pa;
    }

    /**
     * Applies the result to a file's metadata, if that is still allowed.
     *
     * @return bool Whether anything was written
     */
    public function applyTo(File $file): bool
    {
        if (!$this->mayWriteTo($file)) {
            return false;
        }

        try {
            $path = $file->getForLocalProcessing(false);
        } catch (\Throwable) {
            return false;
        }

        $result = $this->extract($path);

        if (!$result->hasAnything()) {
            return false;
        }

        $metaData = $file->getMetaData();
        $before = $metaData->get();
        $changes = $this->changeSet($result);

        if ($changes === []) {
            return false;
        }

        // Logged first: the generic listener compares against the trail, so
        // recording here keeps it from logging the same change again as a
        // plain manual update.
        $this->auditService->logChanges(
            'sys_file_metadata',
            (int) ($before['uid'] ?? 0),
            'auto_detect',
            AuditService::SOURCE_AUTO_DETECT,
            $before,
            $changes,
        );

        $metaData->add($changes)->save();

        return true;
    }

    /**
     * Only untouched records and earlier suggestions are overwritten. Anything
     * a human has confirmed stays as it is — including "no AI involved".
     */
    private function mayWriteTo(File $file): bool
    {
        return $this->isOpenForSuggestion(AiDeclaration::fromRecord($file->getMetaData()->get()));
    }

    /**
     * Whether automatic detection is still allowed to write to this record.
     *
     * Public so the rule can be tested on its own — it is the guard that keeps
     * a machine guess from overruling a human decision.
     */
    public function isOpenForSuggestion(AiDeclaration $declaration): bool
    {
        return $declaration->status->requiresReview();
    }

    /**
     * The fields a finding writes back.
     *
     * Public so the "never more than a suggestion" rule can be tested without
     * a file system and a FAL storage behind it.
     *
     * @internal
     *
     * @return array<string, string|int>
     */
    public function changeSet(ProvenanceResult $result): array
    {
        $changes = [];

        if ($result->hasFinding()) {
            // Never the detected status itself — always the suggestion.
            $changes['tx_ntaimark_status'] = AiStatus::Suggested->value;
        }

        if ($result->system !== '') {
            $changes['tx_ntaimark_system'] = $result->system;
        }
        if ($result->vendor !== '') {
            $changes['tx_ntaimark_vendor'] = $result->vendor;
        }
        if ($result->sourceType !== '') {
            $changes['tx_ntaimark_source_type'] = $result->sourceType;
        }
        if ($result->createdAt > 0) {
            $changes['tx_ntaimark_created_at'] = $result->createdAt;

            // Content that predates the obligation: propose the exemption
            // right away so the editor sees why nothing will be labelled.
            if (AiActCutoff::isBefore($result->createdAt)) {
                $changes['tx_ntaimark_exempt_reason'] = ExemptReason::PreCutoff->value;
            }
        }
        if ($result->c2paState !== C2paState::None) {
            $changes['tx_ntaimark_c2pa_state'] = $result->c2paState->value;
        }
        if ($result->c2paManifest !== '') {
            $changes['tx_ntaimark_c2pa_manifest'] = $result->c2paManifest;
        }

        return $changes;
    }
}

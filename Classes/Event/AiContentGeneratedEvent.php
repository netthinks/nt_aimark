<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Event;

/**
 * Announces that AI produced or altered a piece of content.
 *
 * Dispatch this from wherever the generating happens — nt_ai, nt_lingua, or
 * any project code — and nt_aimark records it. No dependency in either
 * direction: without nt_aimark installed the event simply goes nowhere.
 *
 * The dispatcher speaks from first-hand knowledge; it is not guessing about a
 * file it found. That is why this carries more weight than the automatic
 * detection — see AiContentGeneratedListener for what is done with it.
 *
 * @api
 */
final readonly class AiContentGeneratedEvent
{
    public const KIND_IMAGE = 'image';
    public const KIND_TEXT = 'text';
    public const KIND_AUDIO = 'audio';
    public const KIND_VIDEO = 'video';

    /**
     * An alt text is a description *of* an image, not the image itself.
     * Generating one says nothing about how the image came about.
     */
    public const KIND_ALT_TEXT = 'alt_text';

    public function __construct(
        /** `sys_file`, `sys_file_metadata`, `pages`, `tt_content`, … */
        public string $tableName,
        public int $recordUid,
        /** Product name, e.g. "DALL·E 3". */
        public string $aiSystem,
        /** Vendor, e.g. "OpenAI". */
        public string $aiVendor,
        /** One of the KIND_* constants. */
        public string $contentKind,
        /** True for fully generated, false for AI-assisted changes. */
        public bool $fullyGenerated,
        /** Internal documentation only; never rendered in the frontend. */
        public ?string $prompt = null,
        public ?int $generatedAt = null,
        /**
         * Which extension is speaking, for the audit trail — e.g. "nt_ai".
         *
         * Not in the originally specified signature, but the trail is supposed
         * to record the source and nothing else in the event reveals it.
         */
        public ?string $source = null,
    ) {}
}

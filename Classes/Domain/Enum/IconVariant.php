<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Domain\Enum;

/**
 * The three official EU labelling icons, plus the explicit "no icon" case.
 *
 * The icon files themselves are not shipped with this extension; see
 * Resources/Public/Icons/Eu/README.md.
 */
enum IconVariant: string
{
    /** "AI" — AI was involved, kind of involvement unspecified. */
    case Basic = 'basic';

    /** "AI GENERATED" — fully generated, human involvement limited to prompting. */
    case Generated = 'generated';

    /** "AI MODIFIED" — mixed authorship in either direction. */
    case Modified = 'modified';

    case None = 'none';

    /**
     * Icon that matches a confirmed status when no explicit choice was made.
     */
    public static function defaultForStatus(AiStatus $status): self
    {
        return match ($status) {
            AiStatus::Generated => self::Generated,
            AiStatus::Modified => self::Modified,
            AiStatus::UnknownOrigin => self::Basic,
            default => self::None,
        };
    }

    /**
     * File name inside Resources/Public/Icons/Eu/, following the naming scheme
     * the operator is asked to use when placing the downloaded files.
     */
    public function fileName(bool $white = false, bool $transparent = false): string
    {
        if ($this === self::None) {
            return '';
        }

        return sprintf(
            'ai-%s-%s%s.svg',
            $this->value,
            $white ? 'white' : 'black',
            $transparent ? '-50' : '',
        );
    }

    public function labelKey(): string
    {
        return 'LLL:EXT:nt_aimark/Resources/Private/Language/locallang_db.xlf:icon.' . $this->name;
    }
}

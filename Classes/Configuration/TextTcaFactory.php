<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Configuration;

use NetThinks\NtAimark\Domain\Enum\TextStatus;

/**
 * Builds the TCA for the text transparency fields.
 *
 * One definition for every table that carries them, so `pages`, `tt_content`
 * and any configured extra table stay in step.
 */
final class TextTcaFactory
{
    private const LL = 'LLL:EXT:nt_aimark/Resources/Private/Language/locallang_db.xlf:';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            'tx_ntaimark_text_status' => [
                'exclude' => true,
                'label' => self::LL . 'text.tx_ntaimark_text_status',
                'description' => self::LL . 'text.tx_ntaimark_text_status.description',
                'config' => [
                    'type' => 'select',
                    'renderType' => 'selectSingle',
                    'items' => array_map(
                        static fn(TextStatus $case): array => ['label' => $case->labelKey(), 'value' => $case->value],
                        TextStatus::cases(),
                    ),
                    'default' => TextStatus::NoAi->value,
                ],
            ],
            'tx_ntaimark_public_interest' => [
                'exclude' => true,
                'label' => self::LL . 'text.tx_ntaimark_public_interest',
                'description' => self::LL . 'text.tx_ntaimark_public_interest.description',
                // Only relevant once AI was involved at all.
                'displayCond' => 'FIELD:tx_ntaimark_text_status:>:0',
                'config' => [
                    'type' => 'check',
                    'renderType' => 'checkboxToggle',
                    'default' => 0,
                ],
            ],
            'tx_ntaimark_editorial_control' => [
                'exclude' => true,
                'label' => self::LL . 'text.tx_ntaimark_editorial_control',
                'description' => self::LL . 'text.tx_ntaimark_editorial_control.description',
                'displayCond' => 'FIELD:tx_ntaimark_public_interest:=:1',
                'config' => [
                    'type' => 'check',
                    'renderType' => 'checkboxToggle',
                    'default' => 0,
                ],
            ],
            'tx_ntaimark_responsible' => [
                'exclude' => true,
                'label' => self::LL . 'text.tx_ntaimark_responsible',
                'description' => self::LL . 'text.tx_ntaimark_responsible.description',
                'displayCond' => 'FIELD:tx_ntaimark_editorial_control:=:1',
                'config' => [
                    'type' => 'input',
                    'size' => 40,
                    'max' => 255,
                    'eval' => 'trim',
                    // Without a name the exception does not hold, so the field
                    // is required as soon as review is claimed.
                    'required' => true,
                ],
            ],
        ];
    }

    public static function paletteShowitem(): string
    {
        return 'tx_ntaimark_text_status, --linebreak--,'
            . ' tx_ntaimark_public_interest, tx_ntaimark_editorial_control, --linebreak--,'
            . ' tx_ntaimark_responsible';
    }

    public static function tabShowitem(): string
    {
        return '--div--;' . self::LL . 'tab.aimark,--palette--;;ntaimark_text';
    }

    /**
     * @return array<string, mixed>
     */
    public static function palette(): array
    {
        return [
            'label' => self::LL . 'palette.text',
            'showitem' => self::paletteShowitem(),
        ];
    }
}

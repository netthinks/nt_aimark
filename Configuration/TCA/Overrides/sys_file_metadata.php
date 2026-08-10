<?php

declare(strict_types=1);

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\C2paState;
use NetThinks\NtAimark\Domain\Enum\DisclosureMode;
use NetThinks\NtAimark\Domain\Enum\ExemptReason;
use NetThinks\NtAimark\Domain\Enum\IconVariant;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

(static function (): void {
    $ll = 'LLL:EXT:nt_aimark/Resources/Private/Language/locallang_db.xlf:';

    // Items are derived from the enums so a new case cannot be forgotten here.
    $itemsFromEnum = static function (array $cases): array {
        return array_map(
            static fn(AiStatus|DisclosureMode|IconVariant|ExemptReason|C2paState $case): array => [
                'label' => $case->labelKey(),
                'value' => $case->value,
            ],
            $cases,
        );
    };

    $columns = [
        'tx_ntaimark_status' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_status',
            'description' => $ll . 'sys_file_metadata.tx_ntaimark_status.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => $itemsFromEnum(AiStatus::cases()),
                'default' => AiStatus::Unreviewed->value,
            ],
        ],
        'tx_ntaimark_disclosure' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_disclosure',
            'description' => $ll . 'sys_file_metadata.tx_ntaimark_disclosure.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => $itemsFromEnum(DisclosureMode::cases()),
                'default' => DisclosureMode::Automatic->value,
            ],
        ],
        'tx_ntaimark_exempt_reason' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_exempt_reason',
            'description' => $ll . 'sys_file_metadata.tx_ntaimark_exempt_reason.description',
            // Also shown when the field already carries a value: automatic
            // detection may propose pre_cutoff while disclosure is still
            // "automatic", and that proposal has to stay visible.
            'displayCond' => [
                'OR' => [
                    'FIELD:tx_ntaimark_disclosure:=:' . DisclosureMode::Exempt->value,
                    'FIELD:tx_ntaimark_exempt_reason:REQ:true',
                ],
            ],
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $ll . 'exemptReason.none', 'value' => ''],
                    ...$itemsFromEnum(ExemptReason::cases()),
                ],
                'default' => '',
            ],
        ],
        'tx_ntaimark_icon' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_icon',
            'description' => $ll . 'sys_file_metadata.tx_ntaimark_icon.description',
            'displayCond' => [
                'OR' => [
                    'FIELD:tx_ntaimark_status:IN:' . implode(',', [
                        AiStatus::Generated->value,
                        AiStatus::Modified->value,
                        AiStatus::UnknownOrigin->value,
                    ]),
                    'FIELD:tx_ntaimark_disclosure:=:' . DisclosureMode::Forced->value,
                ],
            ],
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    // Empty means "derive from the status"; IconVariant::None is
                    // the deliberate choice to show no icon at all.
                    ['label' => $ll . 'icon.automatic', 'value' => ''],
                    ...$itemsFromEnum(IconVariant::cases()),
                ],
                'default' => '',
            ],
        ],
        'tx_ntaimark_label_text' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_label_text',
            'description' => $ll . 'sys_file_metadata.tx_ntaimark_label_text.description',
            'displayCond' => [
                'OR' => [
                    'FIELD:tx_ntaimark_status:IN:' . implode(',', [
                        AiStatus::Generated->value,
                        AiStatus::Modified->value,
                        AiStatus::UnknownOrigin->value,
                    ]),
                    'FIELD:tx_ntaimark_disclosure:=:' . DisclosureMode::Forced->value,
                ],
            ],
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'tx_ntaimark_system' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_system',
            'displayCond' => 'FIELD:tx_ntaimark_status:IN:' . implode(',', [
                AiStatus::Generated->value,
                AiStatus::Modified->value,
                AiStatus::Suggested->value,
            ]),
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 128,
                'eval' => 'trim',
            ],
        ],
        'tx_ntaimark_vendor' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_vendor',
            'displayCond' => 'FIELD:tx_ntaimark_status:IN:' . implode(',', [
                AiStatus::Generated->value,
                AiStatus::Modified->value,
                AiStatus::Suggested->value,
            ]),
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 128,
                'eval' => 'trim',
            ],
        ],
        'tx_ntaimark_prompt' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_prompt',
            'description' => $ll . 'sys_file_metadata.tx_ntaimark_prompt.description',
            'displayCond' => 'FIELD:tx_ntaimark_status:IN:' . implode(',', [
                AiStatus::Generated->value,
                AiStatus::Modified->value,
                AiStatus::Suggested->value,
            ]),
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 3,
            ],
        ],
        'tx_ntaimark_created_at' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_created_at',
            'description' => $ll . 'sys_file_metadata.tx_ntaimark_created_at.description',
            'config' => [
                'type' => 'datetime',
                'format' => 'date',
                'size' => 12,
                'default' => 0,
            ],
        ],
        'tx_ntaimark_reviewer' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_reviewer',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'be_users',
                'items' => [['label' => '', 'value' => 0]],
                'default' => 0,
                // Written by the application when a status is confirmed.
                'readOnly' => true,
            ],
        ],
        'tx_ntaimark_reviewed_at' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_reviewed_at',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'default' => 0,
                'readOnly' => true,
            ],
        ],
        'tx_ntaimark_c2pa_state' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_c2pa_state',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => $itemsFromEnum(C2paState::cases()),
                'default' => C2paState::None->value,
                'readOnly' => true,
            ],
        ],
        'tx_ntaimark_c2pa_manifest' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_c2pa_manifest',
            'displayCond' => 'FIELD:tx_ntaimark_c2pa_manifest:REQ:true',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 6,
                'readOnly' => true,
            ],
        ],
        'tx_ntaimark_source_type' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_source_type',
            'displayCond' => 'FIELD:tx_ntaimark_source_type:REQ:true',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'readOnly' => true,
            ],
        ],
        'tx_ntaimark_notes' => [
            'exclude' => true,
            'label' => $ll . 'sys_file_metadata.tx_ntaimark_notes',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 3,
            ],
        ],
    ];

    ExtensionManagementUtility::addTCAcolumns('sys_file_metadata', $columns);

    $GLOBALS['TCA']['sys_file_metadata']['palettes']['ntaimark_declaration'] = [
        'label' => $ll . 'palette.declaration',
        'showitem' => 'tx_ntaimark_status, tx_ntaimark_created_at, --linebreak--, tx_ntaimark_disclosure, tx_ntaimark_exempt_reason',
    ];
    $GLOBALS['TCA']['sys_file_metadata']['palettes']['ntaimark_label'] = [
        'label' => $ll . 'palette.label',
        'showitem' => 'tx_ntaimark_icon, tx_ntaimark_label_text',
    ];
    $GLOBALS['TCA']['sys_file_metadata']['palettes']['ntaimark_system'] = [
        'label' => $ll . 'palette.system',
        'showitem' => 'tx_ntaimark_system, tx_ntaimark_vendor, --linebreak--, tx_ntaimark_prompt',
    ];
    $GLOBALS['TCA']['sys_file_metadata']['palettes']['ntaimark_review'] = [
        'label' => $ll . 'palette.review',
        'showitem' => 'tx_ntaimark_reviewer, tx_ntaimark_reviewed_at, --linebreak--, tx_ntaimark_notes',
    ];
    $GLOBALS['TCA']['sys_file_metadata']['palettes']['ntaimark_provenance'] = [
        'label' => $ll . 'palette.provenance',
        'showitem' => 'tx_ntaimark_c2pa_state, tx_ntaimark_source_type, --linebreak--, tx_ntaimark_c2pa_manifest',
    ];

    ExtensionManagementUtility::addToAllTCAtypes(
        'sys_file_metadata',
        '--div--;' . $ll . 'tab.aimark,'
            . '--palette--;;ntaimark_declaration,'
            . '--palette--;;ntaimark_label,'
            . '--palette--;;ntaimark_system,'
            . '--palette--;;ntaimark_review,'
            . '--palette--;;ntaimark_provenance',
    );
})();

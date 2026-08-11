<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Unit\Service;

use NetThinks\NtAimark\Service\ExtensionSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ExtensionSettingsTest extends UnitTestCase
{
    /**
     * @param array<string, string> $configuration
     */
    private function withConfiguration(array $configuration): ExtensionSettings
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturnCallback(
            static fn(string $extension, string $path): mixed => $configuration[$path]
                ?? throw new \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException(),
        );

        return new ExtensionSettings($extensionConfiguration);
    }

    /**
     * The value ends up in an href in the backend, so anything that is not
     * plainly an https address is dropped rather than rendered. A backend user
     * cannot set it, but an import or a careless deployment can.
     */
    #[Test]
    #[DataProvider('addresses')]
    public function onlyAPlainHttpsAddressIsHandedToTheTemplate(string $configured, string $expected): void
    {
        self::assertSame($expected, $this->withConfiguration(['addOnInfoUrl' => $configured])->addOnInfoUrl());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function addresses(): array
    {
        return [
            'https address' => ['https://example.org/info', 'https://example.org/info'],
            'trimmed' => ['  https://example.org/info  ', 'https://example.org/info'],
            'empty switches the hint off' => ['', ''],
            'plain http' => ['http://example.org/info', ''],
            'javascript' => ['javascript:alert(1)', ''],
            'data uri' => ['data:text/html,<script>alert(1)</script>', ''],
            'breaking out of the attribute' => ['https://example.org" onclick="alert(1)', ''],
            'tag in the address' => ['https://example.org/<script>', ''],
            'relative path' => ['/leistungen/', ''],
        ];
    }

    /**
     * An unset key must not take the module down: the setting was added later
     * than the extension, so installations upgrading into it have no value.
     */
    #[Test]
    public function anAbsentSettingIsNoAddress(): void
    {
        self::assertSame('', $this->withConfiguration([])->addOnInfoUrl());
    }
}

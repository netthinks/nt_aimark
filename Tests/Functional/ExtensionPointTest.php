<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Tests\Functional;

use NetThinks\NtAimark\Domain\Enum\AiStatus;
use NetThinks\NtAimark\Domain\Enum\IconVariant;
use NetThinks\NtAimark\Domain\Model\AiDeclaration;
use NetThinks\NtAimark\Domain\Model\AiMarkSettings;
use NetThinks\NtAimark\Domain\Model\LabelDecision;
use NetThinks\NtAimark\Service\AuditService;
use NetThinks\NtAimark\Service\DisclosureRuleService;
use NetThinks\NtAimark\Service\IconCompositorInterface;
use NetThinks\NtAimark\Service\LabelDecisionModifierInterface;
use NetThinks\NtAimark\Service\LabelInjectorInterface;
use NetThinks\NtAimark\Service\NullIconCompositor;
use NetThinks\NtAimark\Service\NullLabelInjector;
use NetThinks\NtAimark\Service\ProcessedFileDeclarationResolver;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The extension points a second package hooks into.
 *
 * They are public API: they have to exist, be reachable from the container,
 * and behave as pass-throughs by default — the core package must be a complete
 * product on its own, with nothing switched off waiting for an upgrade.
 */
final class ExtensionPointTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['netthinks/nt-aimark'];

    #[Test]
    public function theIconCompositorIsReachableAndPassesThrough(): void
    {
        $service = $this->get(IconCompositorInterface::class);

        self::assertInstanceOf(NullIconCompositor::class, $service);
    }

    #[Test]
    public function theLabelInjectorIsReachableAndPassesThrough(): void
    {
        $service = $this->get(LabelInjectorInterface::class);

        self::assertInstanceOf(NullLabelInjector::class, $service);
        self::assertSame(
            '<p>unchanged</p>',
            $service->apply('<p>unchanged</p>', new \TYPO3\CMS\Core\Http\ServerRequest('https://example.com/')),
        );
    }

    /**
     * A second package has to be able to write to the trail, which only works
     * if the service is public.
     */
    #[Test]
    public function theAuditServiceIsPublic(): void
    {
        self::assertInstanceOf(AuditService::class, $this->get(AuditService::class));
    }

    #[Test]
    public function theProcessedFileResolverIsPublic(): void
    {
        self::assertInstanceOf(
            ProcessedFileDeclarationResolver::class,
            $this->get(ProcessedFileDeclarationResolver::class),
        );
    }

    #[Test]
    public function anUnknownPathResolvesToNothingRatherThanFailing(): void
    {
        $resolver = $this->get(ProcessedFileDeclarationResolver::class);

        self::assertNull($resolver->resolve('/fileadmin/_processed_/does/not/exist.jpg'));
        self::assertNull($resolver->resolve(''));
        self::assertNull($resolver->resolve('https://example.com/nope.jpg?v=1'));
    }

    /**
     * The core package registers no modifier, so the rules decide alone.
     */
    #[Test]
    public function theCorePackageShipsNoDecisionModifier(): void
    {
        $declaration = new AiDeclaration(
            tableName: 'sys_file_metadata',
            recordUid: 1,
            status: AiStatus::Generated,
            createdAt: \NetThinks\NtAimark\Domain\AiActCutoff::TIMESTAMP + 86400,
        );

        $decision = $this->get(DisclosureRuleService::class)->resolve($declaration, new AiMarkSettings());

        self::assertTrue($decision->shouldLabel);
        self::assertSame(IconVariant::Generated, $decision->iconVariant);
    }

    /**
     * A registered modifier sees the rules' outcome and its result is what
     * counts.
     */
    #[Test]
    public function aRegisteredModifierChangesTheOutcome(): void
    {
        $modifier = new class implements LabelDecisionModifierInterface {
            public function modify(AiDeclaration $declaration, LabelDecision $decision): LabelDecision
            {
                return new LabelDecision(
                    shouldLabel: false,
                    reason: $decision->reason,
                );
            }
        };

        $service = new DisclosureRuleService([$modifier]);

        $declaration = new AiDeclaration(
            tableName: 'sys_file_metadata',
            recordUid: 1,
            status: AiStatus::Generated,
            createdAt: \NetThinks\NtAimark\Domain\AiActCutoff::TIMESTAMP + 86400,
        );

        self::assertFalse($service->resolve($declaration, new AiMarkSettings())->shouldLabel);
    }

    /**
     * Modifiers run in order, each seeing what the previous one produced.
     */
    #[Test]
    public function modifiersRunInOrder(): void
    {
        $first = new class implements LabelDecisionModifierInterface {
            public function modify(AiDeclaration $declaration, LabelDecision $decision): LabelDecision
            {
                return new LabelDecision(true, $decision->reason, IconVariant::Basic);
            }
        };
        $second = new class implements LabelDecisionModifierInterface {
            public function modify(AiDeclaration $declaration, LabelDecision $decision): LabelDecision
            {
                return new LabelDecision(true, $decision->reason, IconVariant::Modified);
            }
        };

        $service = new DisclosureRuleService([$first, $second]);
        $declaration = new AiDeclaration(tableName: 'sys_file_metadata', recordUid: 1, status: AiStatus::Generated);

        self::assertSame(IconVariant::Modified, $service->resolve($declaration, new AiMarkSettings())->iconVariant);
    }

    /**
     * An interface nobody calls is documentation, not an extension point.
     *
     * The compositor was declared and registered but never reached from the
     * processing chain, so an implementation in a second package would simply
     * never have run.
     */
    #[Test]
    public function theCompositorIsReachedFromTheProcessingChain(): void
    {
        $listener = file_get_contents(
            dirname(__DIR__, 2) . '/Classes/EventListener/AfterFileProcessingListener.php',
        );

        self::assertIsString($listener);
        self::assertStringContainsString('iconCompositor->composite(', $listener);

        // And it must cost nothing while no second package is installed.
        self::assertStringContainsString('instanceof NullIconCompositor', $listener);
    }

    /**
     * The rule that shapes the whole distribution model: no licence check, no
     * activation, no domain binding, no phone-home — not even prepared.
     */
    #[Test]
    public function noLicenceOrActivationLogicExistsAnywhere(): void
    {
        $forbidden = [
            'licenseKey', 'license_key', 'licenceKey',
            'activationKey', 'activation_key',
            'checkLicense', 'checkLicence', 'validateLicense',
            'isLicensed', 'isActivated', 'domainCheck',
        ];

        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/Classes'),
        );

        foreach ($directory as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            foreach ($forbidden as $needle) {
                self::assertStringNotContainsStringIgnoringCase(
                    $needle,
                    $contents,
                    sprintf('%s must not contain licence logic (%s).', $file->getFilename(), $needle),
                );
            }
        }
    }
}

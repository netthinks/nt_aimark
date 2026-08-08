<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\Service;

/**
 * Builds the services that need values from the extension configuration.
 *
 * Reading that configuration during container compilation would bake the
 * values into the compiled container, so it happens here at instantiation
 * time instead.
 */
final readonly class ServiceFactory
{
    public function __construct(
        private ExtensionSettings $settings,
    ) {}

    public function createC2paService(): C2paService
    {
        return new C2paService(
            $this->settings->c2patoolPath(),
            $this->settings->c2patoolTimeout(),
        );
    }

    public function createExifSignatureService(): ExifSignatureService
    {
        return new ExifSignatureService($this->settings->additionalExifSignatures());
    }
}

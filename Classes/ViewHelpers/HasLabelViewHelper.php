<?php

declare(strict_types=1);

namespace NetThinks\NtAimark\ViewHelpers;

/**
 * Whether a file would be labelled — for templates that need to arrange the
 * surrounding markup differently.
 *
 * <f:if condition="{nt:hasLabel(file: file)}">…</f:if>
 */
final class HasLabelViewHelper extends AbstractLabelViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('file', 'object', 'FAL file or file reference', true);
    }

    public function render(): bool
    {
        $result = $this->decide($this->arguments['file']);

        return $result !== null && $result[1]->shouldLabel;
    }
}

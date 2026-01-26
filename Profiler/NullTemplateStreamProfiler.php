<?php

// packages/twig-streaming/src/Profiler/NullTemplateStreamProfiler.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Profiler;

/**
 * No-op profiler for production.
 */
final class NullTemplateStreamProfiler implements TemplateStreamProfilerInterface
{
    #[\Override]
    public function enterTemplate(string $templateName): void
    {
    }

    #[\Override]
    public function leaveTemplate(string $templateName): void
    {
    }

    #[\Override]
    public function enterBlock(string $templateName, string $blockName): void
    {
    }

    #[\Override]
    public function leaveBlock(string $templateName, string $blockName): void
    {
    }

    #[\Override]
    public function getEvents(): array
    {
        return [];
    }
}

<?php
// packages/twig-streaming/src/Twig/StreamingProfilerRuntime.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Twig;

use Toppy\TwigStreaming\Profiler\TemplateStreamProfilerInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Runtime extension that bridges Twig templates to TemplateStreamProfiler.
 */
final class StreamingProfilerRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly TemplateStreamProfilerInterface $profiler,
    ) {}

    public function enterTemplate(string $templateName): void
    {
        $this->profiler->enterTemplate($templateName);
    }

    public function leaveTemplate(string $templateName): void
    {
        $this->profiler->leaveTemplate($templateName);
    }

    public function enterBlock(string $templateName, string $blockName): void
    {
        $this->profiler->enterBlock($templateName, $blockName);
    }

    public function leaveBlock(string $templateName, string $blockName): void
    {
        $this->profiler->leaveBlock($templateName, $blockName);
    }
}

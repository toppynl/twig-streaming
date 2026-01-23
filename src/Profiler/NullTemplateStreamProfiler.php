<?php
// packages/twig-streaming/src/Profiler/NullTemplateStreamProfiler.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Profiler;

/**
 * No-op profiler for production.
 */
final class NullTemplateStreamProfiler implements TemplateStreamProfilerInterface
{
    public function enterTemplate(string $templateName): void {}
    public function leaveTemplate(string $templateName): void {}
    public function enterBlock(string $templateName, string $blockName): void {}
    public function leaveBlock(string $templateName, string $blockName): void {}
    public function getEvents(): array { return []; }
}

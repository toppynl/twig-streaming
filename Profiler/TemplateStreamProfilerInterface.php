<?php
// packages/twig-streaming/src/Profiler/TemplateStreamProfilerInterface.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Profiler;

/**
 * Collects timing data for template and block rendering.
 */
interface TemplateStreamProfilerInterface
{
    public function enterTemplate(string $templateName): void;

    public function leaveTemplate(string $templateName): void;

    public function enterBlock(string $templateName, string $blockName): void;

    public function leaveBlock(string $templateName, string $blockName): void;

    /**
     * @return array<StreamingTimelineEvent>
     */
    public function getEvents(): array;
}

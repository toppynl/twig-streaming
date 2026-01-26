<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Unit\Profiler;

use PHPUnit\Framework\TestCase;
use Toppy\TwigStreaming\Profiler\NullTemplateStreamProfiler;
use Toppy\TwigStreaming\Profiler\TemplateStreamProfilerInterface;

/** Tests for NullTemplateStreamProfiler */
final class NullTemplateStreamProfilerTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $profiler = new NullTemplateStreamProfiler();
        static::assertInstanceOf(TemplateStreamProfilerInterface::class, $profiler);
    }

    public function testGetEventsReturnsEmptyArray(): void
    {
        $profiler = new NullTemplateStreamProfiler();
        $profiler->enterTemplate('test.html.twig');
        $profiler->leaveTemplate('test.html.twig');

        static::assertSame([], $profiler->getEvents());
    }
}

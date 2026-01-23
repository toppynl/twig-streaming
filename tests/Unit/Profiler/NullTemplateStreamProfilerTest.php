<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Unit\Profiler;

use PHPUnit\Framework\TestCase;
use Toppy\TwigStreaming\Profiler\NullTemplateStreamProfiler;
use Toppy\TwigStreaming\Profiler\TemplateStreamProfilerInterface;

final class NullTemplateStreamProfilerTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $profiler = new NullTemplateStreamProfiler();
        $this->assertInstanceOf(TemplateStreamProfilerInterface::class, $profiler);
    }

    public function testGetEventsReturnsEmptyArray(): void
    {
        $profiler = new NullTemplateStreamProfiler();
        $profiler->enterTemplate('test.html.twig');
        $profiler->leaveTemplate('test.html.twig');

        $this->assertSame([], $profiler->getEvents());
    }
}

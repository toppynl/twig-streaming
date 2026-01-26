<?php

// packages/twig-streaming/tests/Unit/Profiler/StreamingTimelineEventTest.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Unit\Profiler;

use PHPUnit\Framework\TestCase;
use Toppy\TwigStreaming\Profiler\StreamingTimelineEvent;

/** Tests for StreamingTimelineEvent */
final class StreamingTimelineEventTest extends TestCase
{
    public function testEventHoldsAllProperties(): void
    {
        $event = new StreamingTimelineEvent(
            type: 'template_start',
            name: 'product.html.twig',
            timestamp: 10.5,
            parent: 'base.html.twig',
            metadata: ['key' => 'value'],
        );

        static::assertSame('template_start', $event->type);
        static::assertSame('product.html.twig', $event->name);
        static::assertSame(10.5, $event->timestamp);
        static::assertSame('base.html.twig', $event->parent);
        static::assertSame(['key' => 'value'], $event->metadata);
    }

    public function testGetShortNameExtractsFilename(): void
    {
        $event = new StreamingTimelineEvent(type: 'template_start', name: 'demo/product.html.twig', timestamp: 0.0);

        static::assertSame('product.html.twig', $event->getShortName());
    }

    public function testGetShortNameExtractsClassName(): void
    {
        $event = new StreamingTimelineEvent(
            type: 'viewmodel_start',
            name: 'App\\View\\ProductViewModel',
            timestamp: 0.0,
        );

        static::assertSame('ProductViewModel', $event->getShortName());
    }
}

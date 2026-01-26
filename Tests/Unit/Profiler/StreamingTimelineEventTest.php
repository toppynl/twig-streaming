<?php
// packages/twig-streaming/tests/Unit/Profiler/StreamingTimelineEventTest.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Unit\Profiler;

use PHPUnit\Framework\TestCase;
use Toppy\TwigStreaming\Profiler\StreamingTimelineEvent;

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

        $this->assertSame('template_start', $event->type);
        $this->assertSame('product.html.twig', $event->name);
        $this->assertSame(10.5, $event->timestamp);
        $this->assertSame('base.html.twig', $event->parent);
        $this->assertSame(['key' => 'value'], $event->metadata);
    }

    public function testGetShortNameExtractsFilename(): void
    {
        $event = new StreamingTimelineEvent(
            type: 'template_start',
            name: 'demo/product.html.twig',
            timestamp: 0.0,
        );

        $this->assertSame('product.html.twig', $event->getShortName());
    }

    public function testGetShortNameExtractsClassName(): void
    {
        $event = new StreamingTimelineEvent(
            type: 'viewmodel_start',
            name: 'App\\View\\ProductViewModel',
            timestamp: 0.0,
        );

        $this->assertSame('ProductViewModel', $event->getShortName());
    }
}

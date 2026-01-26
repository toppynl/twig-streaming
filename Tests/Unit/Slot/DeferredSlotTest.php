<?php

// packages/twig-streaming/tests/Unit/Slot/DeferredSlotTest.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Unit\Slot;

use PHPUnit\Framework\TestCase;
use Toppy\AsyncViewModel\Context\RequestContext;
use Toppy\TwigStreaming\Slot\DeferredSlot;

/** Tests for DeferredSlot */
final class DeferredSlotTest extends TestCase
{
    public function testCreateWithRequiredFields(): void
    {
        $slot = new DeferredSlot(
            id: 'slot_abc123',
            template: 'components/reviews.html.twig',
            skeleton: 'skeletons/reviews.html.twig',
        );

        static::assertSame('slot_abc123', $slot->id);
        static::assertSame('components/reviews.html.twig', $slot->template);
        static::assertSame('skeletons/reviews.html.twig', $slot->skeleton);
        static::assertNull($slot->fallback);
        static::assertFalse($slot->isInlineFallback);
    }

    public function testCreateWithTemplateFallback(): void
    {
        $slot = new DeferredSlot(
            id: 'slot_abc123',
            template: 'components/reviews.html.twig',
            skeleton: 'skeletons/reviews.html.twig',
            fallback: 'errors/reviews.html.twig',
            isInlineFallback: false,
        );

        static::assertSame('errors/reviews.html.twig', $slot->fallback);
        static::assertFalse($slot->isInlineFallback);
    }

    public function testCreateWithInlineStringFallback(): void
    {
        $slot = new DeferredSlot(
            id: 'slot_abc123',
            template: 'components/reviews.html.twig',
            skeleton: 'skeletons/reviews.html.twig',
            fallback: 'Unable to load reviews',
            isInlineFallback: true,
        );

        static::assertSame('Unable to load reviews', $slot->fallback);
        static::assertTrue($slot->isInlineFallback);
    }

    public function testGenerateIdFromTemplateAndContext(): void
    {
        $ctx1 = RequestContext::create(['productId' => 1], 'test-1');
        $ctx2 = RequestContext::create(['productId' => 2], 'test-2');
        $ctx3 = RequestContext::create(['productId' => 1], 'test-1');

        $id1 = DeferredSlot::generateId('components/reviews.html.twig', $ctx1);
        $id2 = DeferredSlot::generateId('components/reviews.html.twig', $ctx2);
        $id3 = DeferredSlot::generateId('components/reviews.html.twig', $ctx3);

        static::assertStringStartsWith('slot_', $id1);
        static::assertNotSame($id1, $id2); // Different context = different ID
        static::assertSame($id1, $id3); // Same template + context = same ID
    }
}

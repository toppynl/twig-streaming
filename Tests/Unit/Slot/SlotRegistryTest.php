<?php

// packages/twig-streaming/tests/Unit/Slot/SlotRegistryTest.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Unit\Slot;

use Amp\Future;
use PHPUnit\Framework\TestCase;
use Toppy\TwigStreaming\Slot\DeferredSlot;
use Toppy\TwigStreaming\Slot\SlotRegistry;

/** Tests for SlotRegistry */
final class SlotRegistryTest extends TestCase
{
    public function testRegisterAndGetSlot(): void
    {
        $registry = new SlotRegistry();
        $slot = new DeferredSlot('slot_1', 'template.twig', 'skeleton.twig');
        $future = Future::complete('<div>Content</div>');

        $registry->register($slot, $future);

        static::assertTrue($registry->has('slot_1'));
        static::assertSame($slot, $registry->getSlot('slot_1'));
        static::assertSame($future, $registry->getFuture('slot_1'));
    }

    public function testHasReturnsFalseForUnknownSlot(): void
    {
        $registry = new SlotRegistry();

        static::assertFalse($registry->has('unknown'));
    }

    public function testGetSlotThrowsForUnknownSlot(): void
    {
        $registry = new SlotRegistry();

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Slot "unknown" not found');

        $registry->getSlot('unknown');
    }

    public function testGetFutureThrowsForUnknownSlot(): void
    {
        $registry = new SlotRegistry();

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Slot "unknown" not found');

        $registry->getFuture('unknown');
    }

    public function testGetPendingReturnsAllRegisteredSlots(): void
    {
        $registry = new SlotRegistry();

        $slot1 = new DeferredSlot('slot_1', 't1.twig', 's1.twig');
        $slot2 = new DeferredSlot('slot_2', 't2.twig', 's2.twig');

        $future1 = Future::complete('<div>1</div>');
        $future2 = Future::complete('<div>2</div>');

        $registry->register($slot1, $future1);
        $registry->register($slot2, $future2);

        $pending = $registry->getPending();

        static::assertCount(2, $pending);
    }

    public function testResetClearsRegistry(): void
    {
        $registry = new SlotRegistry();
        $slot = new DeferredSlot('slot_1', 'template.twig', 'skeleton.twig');

        $registry->register($slot, Future::complete(''));
        static::assertTrue($registry->has('slot_1'));

        $registry->reset();
        static::assertFalse($registry->has('slot_1'));
    }
}

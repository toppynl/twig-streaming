<?php
// packages/twig-streaming/tests/Unit/Slot/SlotRegistryTest.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Unit\Slot;

use Amp\Future;
use PHPUnit\Framework\TestCase;
use Toppy\TwigStreaming\Slot\DeferredSlot;
use Toppy\TwigStreaming\Slot\SlotRegistry;

final class SlotRegistryTest extends TestCase
{
    public function testRegisterAndGetSlot(): void
    {
        $registry = new SlotRegistry();
        $slot = new DeferredSlot('slot_1', 'template.twig', 'skeleton.twig');
        $future = Future::complete('<div>Content</div>');

        $registry->register($slot, $future);

        $this->assertTrue($registry->has('slot_1'));
        $this->assertSame($slot, $registry->getSlot('slot_1'));
        $this->assertSame($future, $registry->getFuture('slot_1'));
    }

    public function testHasReturnsFalseForUnknownSlot(): void
    {
        $registry = new SlotRegistry();

        $this->assertFalse($registry->has('unknown'));
    }

    public function testGetSlotThrowsForUnknownSlot(): void
    {
        $registry = new SlotRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slot "unknown" not found');

        $registry->getSlot('unknown');
    }

    public function testGetFutureThrowsForUnknownSlot(): void
    {
        $registry = new SlotRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slot "unknown" not found');

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

        $this->assertCount(2, $pending);
    }

    public function testResetClearsRegistry(): void
    {
        $registry = new SlotRegistry();
        $slot = new DeferredSlot('slot_1', 'template.twig', 'skeleton.twig');

        $registry->register($slot, Future::complete(''));
        $this->assertTrue($registry->has('slot_1'));

        $registry->reset();
        $this->assertFalse($registry->has('slot_1'));
    }
}

<?php

// packages/twig-streaming/src/Slot/SlotRegistryInterface.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Slot;

use Amp\Future;

interface SlotRegistryInterface
{
    /**
     * Register a deferred slot with its content future.
     *
     * @param Future<string> $contentFuture
     */
    public function register(DeferredSlot $slot, Future $contentFuture): void;

    public function has(string $slotId): bool;

    /**
     * @throws \InvalidArgumentException When slot is not found.
     */
    public function getSlot(string $slotId): DeferredSlot;

    /**
     * @return Future<string>
     *
     * @throws \InvalidArgumentException When slot is not found.
     */
    public function getFuture(string $slotId): Future;

    /**
     * Get all pending slots with their futures.
     *
     * @return array<string, array{slot: DeferredSlot, future: Future<string>}>
     */
    public function getPending(): array;

    public function reset(): void;
}

<?php
// packages/twig-streaming/src/Slot/SlotRegistry.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Slot;

use Amp\Future;
use Symfony\Contracts\Service\ResetInterface;

final class SlotRegistry implements SlotRegistryInterface, ResetInterface
{
    /** @var array<string, DeferredSlot> */
    private array $slots = [];

    /** @var array<string, Future<string>> */
    private array $futures = [];

    public function register(DeferredSlot $slot, Future $contentFuture): void
    {
        $this->slots[$slot->id] = $slot;
        $this->futures[$slot->id] = $contentFuture;
    }

    public function has(string $slotId): bool
    {
        return isset($this->slots[$slotId]);
    }

    public function getSlot(string $slotId): DeferredSlot
    {
        if (!isset($this->slots[$slotId])) {
            throw new \InvalidArgumentException(sprintf('Slot "%s" not found', $slotId));
        }

        return $this->slots[$slotId];
    }

    public function getFuture(string $slotId): Future
    {
        if (!isset($this->futures[$slotId])) {
            throw new \InvalidArgumentException(sprintf('Slot "%s" not found', $slotId));
        }

        return $this->futures[$slotId];
    }

    public function getPending(): array
    {
        $pending = [];

        foreach ($this->slots as $id => $slot) {
            $pending[$id] = [
                'slot' => $slot,
                'future' => $this->futures[$id],
            ];
        }

        return $pending;
    }

    public function reset(): void
    {
        $this->slots = [];
        $this->futures = [];
    }
}

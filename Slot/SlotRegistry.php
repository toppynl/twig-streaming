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

    #[\Override]
    public function register(DeferredSlot $slot, Future $contentFuture): void
    {
        // ignore() so a rejected slot future dropped without being awaited
        // (stream aborted, or reset() between requests in worker mode) never
        // surfaces as an UnhandledFutureError in a later request. The slot
        // streamer still observes rejections by awaiting the future.
        $contentFuture->ignore();

        $this->slots[$slot->id] = $slot;
        $this->futures[$slot->id] = $contentFuture;
    }

    #[\Override]
    public function has(string $slotId): bool
    {
        return isset($this->slots[$slotId]);
    }

    /**
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function getSlot(string $slotId): DeferredSlot
    {
        if (!isset($this->slots[$slotId])) {
            throw new \InvalidArgumentException(sprintf('Slot "%s" not found', $slotId));
        }

        return $this->slots[$slotId];
    }

    /**
     * @throws \InvalidArgumentException
     */
    #[\Override]
    public function getFuture(string $slotId): Future
    {
        if (!isset($this->futures[$slotId])) {
            throw new \InvalidArgumentException(sprintf('Slot "%s" not found', $slotId));
        }

        return $this->futures[$slotId];
    }

    #[\Override]
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

    #[\Override]
    public function reset(): void
    {
        $this->slots = [];
        $this->futures = [];
    }
}

<?php

// packages/twig-streaming/src/Slot/DeferredSlot.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Slot;

use Toppy\AsyncViewModel\Context\RequestContext;

/**
 * Value object representing a deferred slot placeholder.
 */
final readonly class DeferredSlot
{
    public function __construct(
        public string $id,
        public string $template,
        public string $skeleton,
        public ?string $fallback = null,
        public bool $isInlineFallback = false,
    ) {}

    /**
     * Generate a deterministic slot ID from template path and request context.
     */
    public static function generateId(string $template, RequestContext $requestContext): string
    {
        $hash = substr(md5($template . serialize($requestContext->toArray())), offset: 0, length: 8);
        return 'slot_' . $hash;
    }
}

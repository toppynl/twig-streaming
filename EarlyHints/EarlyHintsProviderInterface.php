<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\EarlyHints;

/**
 * Provides Early Hints for HTTP 103 responses.
 *
 * Implementations can provide hints from various sources:
 * - ImportMap modules
 * - Configuration files
 * - Runtime discovery
 */
interface EarlyHintsProviderInterface
{
    /**
     * @return list<array{rel: string, href: string, attributes: array<string, mixed>}>
     */
    public function getHints(): array;
}

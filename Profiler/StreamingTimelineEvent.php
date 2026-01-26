<?php

// packages/twig-streaming/src/Profiler/StreamingTimelineEvent.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Profiler;

/**
 * Immutable event representing a point in the streaming timeline.
 */
final readonly class StreamingTimelineEvent
{
    public function __construct(
        public string $type,
        public string $name,
        public float $timestamp,
        public ?string $parent = null,
        public array $metadata = [],
    ) {}

    /**
     * Extract short name (filename without path, or class short name).
     */
    public function getShortName(): string
    {
        if (str_contains($this->name, '/')) {
            return basename($this->name);
        }
        if (str_contains($this->name, '\\')) {
            $parts = explode('\\', $this->name);
            $shortName = end($parts);
            return $shortName !== false ? $shortName : $this->name;
        }
        return $this->name;
    }
}

<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Twig;

use Toppy\TwigStreaming\Twig\NodeVisitor\StreamingProfilerNodeVisitor;
use Twig\Extension\AbstractExtension;

/**
 * Twig extension that registers the StreamingProfilerNodeVisitor.
 *
 * Node visitors must be registered through extensions, not standalone tags.
 */
final class StreamingProfilerExtension extends AbstractExtension
{
    public function getNodeVisitors(): array
    {
        return [new StreamingProfilerNodeVisitor()];
    }
}

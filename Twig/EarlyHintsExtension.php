<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Twig;

use Toppy\TwigStreaming\Twig\NodeVisitor\EarlyHintsDiscoveryVisitor;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Registers the EarlyHintsDiscoveryVisitor for compile-time hint discovery.
 *
 * Provides stub implementations of WebLink functions (preload, preconnect, etc.)
 * for environments where Symfony WebLink is not available. In production with
 * Symfony, these functions are provided by the WebLink bundle.
 */
final class EarlyHintsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        // Stub implementations for WebLink functions
        // These allow templates to parse and compile for hint discovery.
        // In production, Symfony's WebLink bundle provides the real implementations.
        return [
            new TwigFunction('preload', $this->stubHintFunction(...)),
            new TwigFunction('preconnect', $this->stubHintFunction(...)),
            new TwigFunction('dns_prefetch', $this->stubHintFunction(...)),
            new TwigFunction('prefetch', $this->stubHintFunction(...)),
        ];
    }

    public function getNodeVisitors(): array
    {
        return [
            new EarlyHintsDiscoveryVisitor(),
        ];
    }

    /**
     * Stub implementation that returns the URL unchanged.
     * The real WebLink functions add Link headers to the response.
     */
    private function stubHintFunction(string $url, array $attributes = []): string
    {
        return $url;
    }
}

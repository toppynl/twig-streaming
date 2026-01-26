<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Twig\NodeVisitor;

use Toppy\TwigStreaming\Twig\Node\DoEarlyHintsMethodNode;
use Twig\Environment;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Discovers WebLink function calls in templates at compile-time.
 *
 * Scans for preload(), preconnect(), dns_prefetch(), prefetch() calls
 * and generates a doEarlyHints() method on the compiled template that
 * returns all discovered hints.
 *
 * @see DoEarlyHintsMethodNode
 */
// @mago-ignore analysis:mixed-assignment - Twig Node::getAttribute() returns mixed; vendor limitation
final class EarlyHintsDiscoveryVisitor implements NodeVisitorInterface
{
    /** @var array<string, string> */
    private const array FUNCTION_REL_MAP = [
        'preload' => 'preload',
        'preconnect' => 'preconnect',
        'dns_prefetch' => 'dns-prefetch',
        'prefetch' => 'prefetch',
    ];

    /** @var list<array{rel: string, href: ?string, assetPath: ?string, attributes: array<string, mixed>}> */
    private array $discoveredHints = [];

    #[\Override]
    public function enterNode(Node $node, Environment $env): Node
    {
        // Reset state when entering a new template module
        if ($node instanceof ModuleNode) {
            $this->discoveredHints = [];
        }

        // Look for WebLink function calls
        if ($node instanceof FunctionExpression) {
            $functionName = $node->getAttribute('name');

            if (is_string($functionName) && isset(self::FUNCTION_REL_MAP[$functionName])) {
                $this->extractHint($node, self::FUNCTION_REL_MAP[$functionName]);
            }
        }

        return $node;
    }

    #[\Override]
    public function leaveNode(Node $node, Environment $env): ?Node
    {
        // Inject doEarlyHints() method when leaving ModuleNode
        if ($node instanceof ModuleNode) {
            $methodNode = new DoEarlyHintsMethodNode($this->discoveredHints);

            $existingClassEnd = $node->getNode('class_end');
            $node->setNode('class_end', new Nodes([$existingClassEnd, $methodNode]));
        }

        return $node;
    }

    #[\Override]
    public function getPriority(): int
    {
        return 0;
    }

    private function extractHint(FunctionExpression $node, string $rel): void
    {
        $arguments = $node->getNode('arguments');

        if (!$arguments->hasNode('0')) {
            return;
        }

        $uriNode = $arguments->getNode('0');

        // Extract attributes (second argument)
        $attributes = [];
        if ($arguments->hasNode('1')) {
            $attrNode = $arguments->getNode('1');
            if ($attrNode instanceof ArrayExpression) {
                $attributes = $this->extractArrayAttributes($attrNode);
            }
        }

        if ($uriNode instanceof ConstantExpression) {
            // Direct URL: preload('/app.css') or preconnect('https://...')
            $href = $uriNode->getAttribute('value');
            $this->discoveredHints[] = [
                'rel' => $rel,
                'href' => is_string($href) ? $href : null,
                'assetPath' => null,
                'attributes' => $attributes,
            ];
        } elseif ($uriNode instanceof FunctionExpression && $uriNode->getAttribute('name') === 'asset') {
            // Nested asset() call: preload(asset('styles/app.css'))
            $assetArgs = $uriNode->getNode('arguments');
            if ($assetArgs->hasNode('0')) {
                $pathNode = $assetArgs->getNode('0');
                if ($pathNode instanceof ConstantExpression) {
                    $assetPath = $pathNode->getAttribute('value');
                    $this->discoveredHints[] = [
                        'rel' => $rel,
                        'href' => null,
                        'assetPath' => is_string($assetPath) ? $assetPath : null,
                        'attributes' => $attributes,
                    ];
                }
            }
        }

        // Other dynamic expressions are skipped (can't discover at compile-time)
    }

    /**
     * @return array<string, mixed>
     */
    private function extractArrayAttributes(ArrayExpression $node): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = [];

        /** @var array{key: \Twig\Node\Node, value: \Twig\Node\Node} $pair */
        foreach ($node->getKeyValuePairs() as $pair) {
            $keyNode = $pair['key'];
            $valueNode = $pair['value'];

            // Only extract constant keys and values
            if ($keyNode instanceof ConstantExpression && $valueNode instanceof ConstantExpression) {
                $key = $keyNode->getAttribute('value');
                if (is_string($key)) {
                    $attributes[$key] = $valueNode->getAttribute('value');
                }
            }
        }

        return $attributes;
    }
}

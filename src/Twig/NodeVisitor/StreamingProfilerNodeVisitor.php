<?php
// packages/twig-streaming/src/Twig/NodeVisitor/StreamingProfilerNodeVisitor.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Twig\NodeVisitor;

use Toppy\TwigStreaming\Twig\Node\StreamingProfileEnterNode;
use Toppy\TwigStreaming\Twig\Node\StreamingProfileLeaveNode;
use Twig\Environment;
use Twig\Node\BlockNode;
use Twig\Node\BodyNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Injects streaming profiler calls around templates and blocks.
 */
final class StreamingProfilerNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if ($node instanceof ModuleNode) {
            $templateName = $node->getTemplateName();

            $node->setNode('display_start', new Nodes([
                new StreamingProfileEnterNode('template', $templateName),
                $node->getNode('display_start'),
            ]));

            $node->setNode('display_end', new Nodes([
                new StreamingProfileLeaveNode('template', $templateName),
                $node->getNode('display_end'),
            ]));
        } elseif ($node instanceof BlockNode) {
            $blockName = $node->getAttribute('name');
            $templateName = $node->getSourceContext()?->getName() ?? 'unknown';

            $node->setNode('body', new BodyNode([
                new StreamingProfileEnterNode('block', $blockName, $templateName),
                $node->getNode('body'),
                new StreamingProfileLeaveNode('block', $blockName, $templateName),
            ]));
        }

        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}

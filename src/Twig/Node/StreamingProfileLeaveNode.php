<?php
// packages/twig-streaming/src/Twig/Node/StreamingProfileLeaveNode.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * Compiles to profiler leaveTemplate/leaveBlock call.
 */
#[YieldReady]
final class StreamingProfileLeaveNode extends Node
{
    public function __construct(
        string $type,
        string $name,
        ?string $templateName = null,
        int $lineno = 0,
    ) {
        parent::__construct([], [
            'profile_type' => $type,
            'name' => $name,
            'template_name' => $templateName,
        ], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $type = $this->getAttribute('profile_type');
        $name = $this->getAttribute('name');

        $compiler->write("\$this->env->getRuntime('Toppy\\\\TwigStreaming\\\\Twig\\\\StreamingProfilerRuntime')->");

        if ($type === 'template') {
            $compiler
                ->raw('leaveTemplate(')
                ->repr($name)
                ->raw(");\n");
        } else {
            $templateName = $this->getAttribute('template_name');
            $compiler
                ->raw('leaveBlock(')
                ->repr($templateName)
                ->raw(', ')
                ->repr($name)
                ->raw(");\n");
        }
    }
}

<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * Generates doEarlyHints() method on compiled Twig templates.
 *
 * Returns an array of hint data discovered at compile-time from
 * preload(), preconnect(), dns_prefetch(), prefetch() function calls.
 * Chains to parent template's doEarlyHints() if present.
 *
 * @see EarlyHintsDiscoveryVisitor
 */
#[YieldReady]
final class DoEarlyHintsMethodNode extends Node
{
    /**
     * @param list<array{rel: string, href: ?string, assetPath: ?string, attributes: array<string, mixed>}> $hints
     */
    public function __construct(
        private readonly array $hints,
    ) {
        parent::__construct();
    }

    /**
     * @throws \LogicException
     */
    #[\Override]
    public function compile(Compiler $compiler): void
    {
        $compiler
            ->write("\n")
            ->write("/**\n")
            ->write(" * Returns Early Hints discovered at compile-time.\n")
            ->write(" *\n")
            ->write(
                " * @return list<array{rel: string, href: ?string, assetPath: ?string, attributes: array<string, mixed>}>\n",
            )
            ->write(" */\n")
            ->write("public function doEarlyHints(): array\n")
            ->write("{\n")
            ->indent()
            ->write('$hints = ')
            ->repr($this->hints)
            ->raw(";\n\n")
            ->write("// Chain to parent template if it exists\n")
            ->write("\$parentName = \$this->doGetParent([]);\n")
            ->write("if (\$parentName !== false) {\n")
            ->indent()
            ->write("\$parent = \$this->load(\$parentName, 0)->unwrap();\n")
            ->write("if (method_exists(\$parent, 'doEarlyHints')) {\n")
            ->indent()
            ->write("\$hints = array_merge(\$parent->doEarlyHints(), \$hints);\n")
            ->outdent()
            ->write("}\n")
            ->outdent()
            ->write("}\n\n")
            ->write("return \$hints;\n")
            ->outdent()
            ->write("}\n");
    }
}

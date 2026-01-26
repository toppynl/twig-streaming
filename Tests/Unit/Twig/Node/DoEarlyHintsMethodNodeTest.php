<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Unit\Twig\Node;

use PHPUnit\Framework\TestCase;
use Toppy\TwigStreaming\Twig\Node\DoEarlyHintsMethodNode;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @mago-expect analysis:possibly-invalid-argument
 */
final class DoEarlyHintsMethodNodeTest extends TestCase
{
    public function testCompilesMethodWithHints(): void
    {
        $hints = [
            ['rel' => 'preload', 'href' => '/css/app.css', 'attributes' => ['as' => 'style']],
            ['rel' => 'preconnect', 'href' => 'https://fonts.googleapis.com', 'attributes' => []],
        ];

        $node = new DoEarlyHintsMethodNode($hints);

        $loader = new ArrayLoader([]);
        $twig = new Environment($loader);
        $compiler = new Compiler($twig);

        $node->compile($compiler);
        $source = $compiler->getSource();

        static::assertStringContainsString('public function doEarlyHints(): array', $source);
        static::assertStringContainsString('/css/app.css', $source);
        static::assertStringContainsString('preconnect', $source);
        static::assertStringContainsString('fonts.googleapis.com', $source);
        static::assertStringContainsString('doGetParent', $source);
    }

    public function testCompilesEmptyHints(): void
    {
        $node = new DoEarlyHintsMethodNode([]);

        $loader = new ArrayLoader([]);
        $twig = new Environment($loader);
        $compiler = new Compiler($twig);

        $node->compile($compiler);
        $source = $compiler->getSource();

        static::assertStringContainsString('$hints = []', $source);
    }
}

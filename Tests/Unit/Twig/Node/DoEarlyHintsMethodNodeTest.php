<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Unit\Twig\Node;

use PHPUnit\Framework\TestCase;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Toppy\TwigStreaming\Twig\Node\DoEarlyHintsMethodNode;

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

        $this->assertStringContainsString('public function doEarlyHints(): array', $source);
        $this->assertStringContainsString('/css/app.css', $source);
        $this->assertStringContainsString('preconnect', $source);
        $this->assertStringContainsString('fonts.googleapis.com', $source);
        $this->assertStringContainsString('doGetParent', $source);
    }

    public function testCompilesEmptyHints(): void
    {
        $node = new DoEarlyHintsMethodNode([]);

        $loader = new ArrayLoader([]);
        $twig = new Environment($loader);
        $compiler = new Compiler($twig);

        $node->compile($compiler);
        $source = $compiler->getSource();

        $this->assertStringContainsString('$hints = []', $source);
    }
}

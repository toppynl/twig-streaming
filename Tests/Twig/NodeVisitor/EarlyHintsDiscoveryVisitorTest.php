<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Twig\NodeVisitor;

use PHPUnit\Framework\TestCase;
use Toppy\TwigStreaming\Twig\EarlyHintsExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @mago-expect analysis:mixed-argument
 * @mago-expect analysis:mixed-array-access
 * @mago-expect analysis:mixed-assignment
 * @mago-expect analysis:non-existent-method
 */
final class EarlyHintsDiscoveryVisitorTest extends TestCase
{
    public function testDiscoversPreloadCalls(): void
    {
        $loader = new ArrayLoader([
            'test.html.twig' => '{{ preload("/css/app.css", {as: "style"}) }}',
        ]);
        $twig = new Environment($loader);
        $twig->addExtension(new EarlyHintsExtension());

        $template = $twig->load('test.html.twig');
        $innerTemplate = $template->unwrap();

        static::assertTrue(method_exists($innerTemplate, 'doEarlyHints'));

        $hints = $innerTemplate->doEarlyHints();
        static::assertCount(1, $hints);
        static::assertSame('preload', $hints[0]['rel']);
        static::assertSame('/css/app.css', $hints[0]['href']);
        static::assertSame('style', $hints[0]['attributes']['as']);
    }

    public function testDiscoversMultipleFunctionTypes(): void
    {
        $loader = new ArrayLoader([
            'test.html.twig' => <<<'TWIG'
                {{ preload("/app.js", {as: "script"}) }}
                {{ preconnect("https://fonts.googleapis.com") }}
                {{ dns_prefetch("https://cdn.example.com") }}
                {{ prefetch("/images/hero.jpg", {as: "image"}) }}
                TWIG,
        ]);
        $twig = new Environment($loader);
        $twig->addExtension(new EarlyHintsExtension());

        $template = $twig->load('test.html.twig');
        $hints = $template->unwrap()->doEarlyHints();

        static::assertCount(4, $hints);

        $rels = array_column($hints, 'rel');
        static::assertContains('preload', $rels);
        static::assertContains('preconnect', $rels);
        static::assertContains('dns-prefetch', $rels);
        static::assertContains('prefetch', $rels);
    }

    public function testInheritsFromParentTemplate(): void
    {
        $loader = new ArrayLoader([
            'parent.html.twig' => '{{ preconnect("https://fonts.googleapis.com") }}{% block content %}{% endblock %}',
            'child.html.twig' => '{% extends "parent.html.twig" %}{% block content %}{{ preload("/app.css", {as: "style"}) }}{% endblock %}',
        ]);
        $twig = new Environment($loader);
        $twig->addExtension(new EarlyHintsExtension());

        $template = $twig->load('child.html.twig');
        $hints = $template->unwrap()->doEarlyHints();

        // Parent hints come first, then child
        static::assertCount(2, $hints);
        static::assertSame('preconnect', $hints[0]['rel']);
        static::assertSame('preload', $hints[1]['rel']);
    }

    public function testSkipsDynamicUris(): void
    {
        $loader = new ArrayLoader([
            'test.html.twig' => '{{ preload(dynamicPath, {as: "style"}) }}{{ preload("/static.css", {as: "style"}) }}',
        ]);
        $twig = new Environment($loader);
        $twig->addExtension(new EarlyHintsExtension());

        $template = $twig->load('test.html.twig');
        $hints = $template->unwrap()->doEarlyHints();

        // Only static path is discovered
        static::assertCount(1, $hints);
        static::assertSame('/static.css', $hints[0]['href']);
    }

    public function testNoHintsGeneratesEmptyMethod(): void
    {
        $loader = new ArrayLoader([
            'test.html.twig' => '<html><body>Hello</body></html>',
        ]);
        $twig = new Environment($loader);
        $twig->addExtension(new EarlyHintsExtension());

        $template = $twig->load('test.html.twig');
        $innerTemplate = $template->unwrap();

        // Method should still exist for parent chaining
        static::assertTrue(method_exists($innerTemplate, 'doEarlyHints'));
        static::assertSame([], $innerTemplate->doEarlyHints());
    }

    public function testDiscoversAssetFunctionCalls(): void
    {
        $loader = new ArrayLoader([
            'test.html.twig' => '{{ preload(asset("styles/app.css"), {as: "style"}) }}',
        ]);
        $twig = new Environment($loader);
        $twig->addExtension(new EarlyHintsExtension());
        // Register stub asset function for test
        $twig->addFunction(new \Twig\TwigFunction('asset', static fn(string $path) => '/' . $path));

        $template = $twig->load('test.html.twig');
        $hints = $template->unwrap()->doEarlyHints();

        static::assertCount(1, $hints);
        static::assertSame('preload', $hints[0]['rel']);
        static::assertNull($hints[0]['href']);
        static::assertSame('styles/app.css', $hints[0]['assetPath']);
        static::assertSame('style', $hints[0]['attributes']['as']);
    }

    public function testDiscoversMixedDirectAndAssetUrls(): void
    {
        $loader = new ArrayLoader([
            'test.html.twig' => <<<'TWIG'
                {{ preconnect("https://fonts.googleapis.com") }}
                {{ preload(asset("app.js"), {as: "script"}) }}
                TWIG,
        ]);
        $twig = new Environment($loader);
        $twig->addExtension(new EarlyHintsExtension());
        $twig->addFunction(new \Twig\TwigFunction('asset', static fn(string $path) => '/' . $path));

        $template = $twig->load('test.html.twig');
        $hints = $template->unwrap()->doEarlyHints();

        static::assertCount(2, $hints);

        // Direct URL
        static::assertSame('https://fonts.googleapis.com', $hints[0]['href']);
        static::assertNull($hints[0]['assetPath']);

        // Asset path
        static::assertNull($hints[1]['href']);
        static::assertSame('app.js', $hints[1]['assetPath']);
    }
}

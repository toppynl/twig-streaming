<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Toppy\AsyncViewModel\ViewModelManagerInterface;
use Toppy\TwigStreaming\Twig\EarlyHintsExtension;
use Toppy\TwigStreaming\Twig\StreamingTemplateRenderer;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @mago-expect analysis:mixed-argument
 * @mago-expect analysis:mixed-array-access
 * @mago-expect analysis:mixed-assignment
 */
final class StreamingTemplateRendererEarlyHintsTest extends TestCase
{
    public function testExtractsEarlyHintsFromTemplate(): void
    {
        $loader = new ArrayLoader([
            'test.html.twig' => '{{ preload("/app.css", {as: "style"}) }}<html></html>',
        ]);
        $twig = new Environment($loader, ['use_yield' => true]);
        $twig->addExtension(new EarlyHintsExtension());

        $manager = $this->createStub(ViewModelManagerInterface::class);
        $renderer = new StreamingTemplateRenderer($twig, $manager);

        $method = new \ReflectionMethod($renderer, 'extractEarlyHints');
        $hints = $method->invoke($renderer, 'test.html.twig');

        static::assertCount(1, $hints);
        static::assertSame('preload', $hints[0]['rel']);
        static::assertSame('/app.css', $hints[0]['href']);
    }

    public function testResolvesAssetPaths(): void
    {
        $loader = new ArrayLoader([
            'test.html.twig' => '<html></html>',
        ]);
        $twig = new Environment($loader, ['use_yield' => true]);

        $packages = $this->createStub(Packages::class);
        $packages->method('getUrl')->with('styles/app.css')->willReturn('/assets/styles/app-abc123.css');

        $manager = $this->createStub(ViewModelManagerInterface::class);
        $renderer = new StreamingTemplateRenderer($twig, $manager, assetPackages: $packages);

        $method = new \ReflectionMethod($renderer, 'resolveHints');
        $resolved = $method->invoke($renderer, [
            ['rel' => 'preload', 'href' => null, 'assetPath' => 'styles/app.css', 'attributes' => ['as' => 'style']],
            ['rel' => 'preconnect', 'href' => 'https://fonts.googleapis.com', 'assetPath' => null, 'attributes' => []],
        ]);

        static::assertCount(2, $resolved);
        static::assertSame('/assets/styles/app-abc123.css', $resolved[0]['href']);
        static::assertSame('https://fonts.googleapis.com', $resolved[1]['href']);
    }

    public function testBuildLinkHeader(): void
    {
        $loader = new ArrayLoader(['test.html.twig' => '']);
        $twig = new Environment($loader, ['use_yield' => true]);

        $manager = $this->createStub(ViewModelManagerInterface::class);
        $renderer = new StreamingTemplateRenderer($twig, $manager);

        $method = new \ReflectionMethod($renderer, 'buildLinkHeader');
        $header = $method->invoke($renderer, [
            ['rel' => 'preload', 'href' => '/app.css', 'assetPath' => null, 'attributes' => ['as' => 'style']],
            ['rel' => 'preconnect', 'href' => 'https://fonts.googleapis.com', 'assetPath' => null, 'attributes' => []],
        ]);

        static::assertStringContainsString('</app.css>; rel=preload; as=style', $header);
        static::assertStringContainsString('<https://fonts.googleapis.com>; rel=preconnect', $header);
    }

    public function testSkipsHintsWithoutHref(): void
    {
        $loader = new ArrayLoader(['test.html.twig' => '']);
        $twig = new Environment($loader, ['use_yield' => true]);

        $manager = $this->createStub(ViewModelManagerInterface::class);
        // No assetPackages - can't resolve asset paths
        $renderer = new StreamingTemplateRenderer($twig, $manager);

        $method = new \ReflectionMethod($renderer, 'resolveHints');
        $resolved = $method->invoke($renderer, [
            ['rel' => 'preload', 'href' => null, 'assetPath' => 'styles/app.css', 'attributes' => []],
        ]);

        static::assertSame([], $resolved);
    }
}

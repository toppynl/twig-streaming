<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Toppy\TwigStreaming\Twig\EarlyHintsExtension;
use Toppy\TwigStreaming\Twig\StreamingTemplateRenderer;
use Toppy\AsyncViewModel\ViewModelManagerInterface;

final class StreamingTemplateRendererEarlyHintsTest extends TestCase
{
    public function testExtractsEarlyHintsFromTemplate(): void
    {
        $loader = new ArrayLoader([
            'test.html.twig' => '{{ preload("/app.css", {as: "style"}) }}<html></html>',
        ]);
        $twig = new Environment($loader, ['use_yield' => true]);
        $twig->addExtension(new EarlyHintsExtension());

        $manager = $this->createMock(ViewModelManagerInterface::class);
        $renderer = new StreamingTemplateRenderer($twig, $manager);

        $method = new \ReflectionMethod($renderer, 'extractEarlyHints');
        $hints = $method->invoke($renderer, 'test.html.twig');

        $this->assertCount(1, $hints);
        $this->assertSame('preload', $hints[0]['rel']);
        $this->assertSame('/app.css', $hints[0]['href']);
    }

    public function testResolvesAssetPaths(): void
    {
        $loader = new ArrayLoader([
            'test.html.twig' => '<html></html>',
        ]);
        $twig = new Environment($loader, ['use_yield' => true]);

        $packages = $this->createMock(Packages::class);
        $packages->method('getUrl')
            ->with('styles/app.css')
            ->willReturn('/assets/styles/app-abc123.css');

        $manager = $this->createMock(ViewModelManagerInterface::class);
        $renderer = new StreamingTemplateRenderer($twig, $manager, assetPackages: $packages);

        $method = new \ReflectionMethod($renderer, 'resolveHints');
        $resolved = $method->invoke($renderer, [
            ['rel' => 'preload', 'href' => null, 'assetPath' => 'styles/app.css', 'attributes' => ['as' => 'style']],
            ['rel' => 'preconnect', 'href' => 'https://fonts.googleapis.com', 'assetPath' => null, 'attributes' => []],
        ]);

        $this->assertCount(2, $resolved);
        $this->assertSame('/assets/styles/app-abc123.css', $resolved[0]['href']);
        $this->assertSame('https://fonts.googleapis.com', $resolved[1]['href']);
    }

    public function testBuildLinkHeader(): void
    {
        $loader = new ArrayLoader(['test.html.twig' => '']);
        $twig = new Environment($loader, ['use_yield' => true]);

        $manager = $this->createMock(ViewModelManagerInterface::class);
        $renderer = new StreamingTemplateRenderer($twig, $manager);

        $method = new \ReflectionMethod($renderer, 'buildLinkHeader');
        $header = $method->invoke($renderer, [
            ['rel' => 'preload', 'href' => '/app.css', 'assetPath' => null, 'attributes' => ['as' => 'style']],
            ['rel' => 'preconnect', 'href' => 'https://fonts.googleapis.com', 'assetPath' => null, 'attributes' => []],
        ]);

        $this->assertStringContainsString('</app.css>; rel=preload; as=style', $header);
        $this->assertStringContainsString('<https://fonts.googleapis.com>; rel=preconnect', $header);
    }

    public function testSkipsHintsWithoutHref(): void
    {
        $loader = new ArrayLoader(['test.html.twig' => '']);
        $twig = new Environment($loader, ['use_yield' => true]);

        $manager = $this->createMock(ViewModelManagerInterface::class);
        // No assetPackages - can't resolve asset paths
        $renderer = new StreamingTemplateRenderer($twig, $manager);

        $method = new \ReflectionMethod($renderer, 'resolveHints');
        $resolved = $method->invoke($renderer, [
            ['rel' => 'preload', 'href' => null, 'assetPath' => 'styles/app.css', 'attributes' => []],
        ]);

        $this->assertSame([], $resolved);
    }
}

<?php

// packages/twig-streaming/tests/Unit/Twig/StreamingTemplateRendererTest.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Toppy\AsyncViewModel\ViewModelManagerInterface;
use Toppy\TwigStreaming\Slot\SlotRegistry;
use Toppy\TwigStreaming\Slot\SlotRenderer;
use Toppy\TwigStreaming\Twig\PendingResponse;
use Toppy\TwigStreaming\Twig\StreamingTemplateRenderer;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @mago-expect analysis:possibly-null-argument
 */
final class StreamingTemplateRendererTest extends TestCase
{
    private function createTwigEnvironment(array $templates = []): Environment
    {
        $defaultTemplates = [
            'test.html.twig' => '<html></html>',
        ];

        $loader = new ArrayLoader(array_merge($defaultTemplates, $templates));

        return new Environment($loader, [
            'use_yield' => true,
            'cache' => false,
        ]);
    }

    public function testRenderDirectReturnsStreamedResponse(): void
    {
        $twig = $this->createTwigEnvironment();
        $manager = $this->createStub(ViewModelManagerInterface::class);

        $renderer = new StreamingTemplateRenderer($twig, $manager);

        $response = $renderer->renderDirect('test.html.twig');

        static::assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testResponseHasStreamingHeaders(): void
    {
        $twig = $this->createTwigEnvironment();
        $manager = $this->createStub(ViewModelManagerInterface::class);

        $renderer = new StreamingTemplateRenderer($twig, $manager);

        $response = $renderer->renderDirect('test.html.twig');

        static::assertSame('no', $response->headers->get('X-Accel-Buffering'));
        static::assertStringContainsString('text/html', $response->headers->get('Content-Type'));
    }

    public function testResponseStatusCanBeCustomized(): void
    {
        $twig = $this->createTwigEnvironment();
        $manager = $this->createStub(ViewModelManagerInterface::class);

        $renderer = new StreamingTemplateRenderer($twig, $manager);

        $response = $renderer->renderDirect('test.html.twig', [], 201);

        static::assertSame(201, $response->getStatusCode());
    }

    public function testCustomHeadersAreMerged(): void
    {
        $twig = $this->createTwigEnvironment();
        $manager = $this->createStub(ViewModelManagerInterface::class);

        $renderer = new StreamingTemplateRenderer($twig, $manager);

        $response = $renderer->renderDirect('test.html.twig', [], 200, ['X-Custom' => 'value']);

        static::assertSame('value', $response->headers->get('X-Custom'));
        static::assertSame('no', $response->headers->get('X-Accel-Buffering')); // Default still present
    }

    public function testPreloadAllNotCalledWhenTemplateHasNoDoPreload(): void
    {
        // Standard template without doPreload method
        $twig = $this->createTwigEnvironment([
            'simple.html.twig' => '<html><body>Simple content</body></html>',
        ]);
        $manager = $this->createMock(ViewModelManagerInterface::class);

        // preloadAll should NOT be called since doPreload doesn't exist
        $manager->expects($this->never())->method('preloadAll');

        $renderer = new StreamingTemplateRenderer($twig, $manager);
        $response = $renderer->renderDirect('simple.html.twig');

        // Execute the callback to trigger streaming
        // We need to capture output at a level below PHPUnit's output handler
        // by using output buffering with a custom handler that ignores flush
        $output = '';
        ob_start(static function (string $buffer) use (&$output): string {
            $output .= $buffer;
            return '';
        });
        $response->sendContent();
        ob_end_clean();

        // Assert content was rendered (implicit - mock verification happens after test)
        static::assertStringContainsString('Simple content', $output);
    }

    public function testStreamOutputsTemplateContent(): void
    {
        $twig = $this->createTwigEnvironment([
            'multi.html.twig' => '<html><body>content</body></html>',
        ]);
        $manager = $this->createStub(ViewModelManagerInterface::class);

        $renderer = new StreamingTemplateRenderer($twig, $manager);
        $response = $renderer->renderDirect('multi.html.twig');

        // Capture output using a custom handler that accumulates chunks
        // This works around ob_flush() sending to parent buffer
        $output = '';
        ob_start(static function (string $buffer) use (&$output): string {
            $output .= $buffer;
            return '';
        });
        $response->sendContent();
        ob_end_clean();

        static::assertSame('<html><body>content</body></html>', $output);
    }

    public function testRenderReturnsPendingResponse(): void
    {
        $twig = $this->createTwigEnvironment();
        $manager = $this->createStub(ViewModelManagerInterface::class);
        $slotRegistry = new SlotRegistry();
        $slotRenderer = new SlotRenderer();

        $renderer = new StreamingTemplateRenderer($twig, $manager, $slotRegistry, $slotRenderer);

        $pending = $renderer->render('test.html.twig');

        static::assertInstanceOf(PendingResponse::class, $pending);
    }
}

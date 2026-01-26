<?php

// packages/twig-streaming/tests/Unit/Slot/SlotRendererTest.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Unit\Slot;

use PHPUnit\Framework\TestCase;
use Toppy\TwigStreaming\Slot\DeferredSlot;
use Toppy\TwigStreaming\Slot\SlotRenderer;

/** Tests for SlotRenderer */
final class SlotRendererTest extends TestCase
{
    public function testRenderPlaceholder(): void
    {
        $renderer = new SlotRenderer();
        $slot = new DeferredSlot('slot_abc', 'template.twig', 'skeleton.twig');

        $html = $renderer->renderPlaceholder($slot, '<div class="loading">...</div>');

        static::assertStringContainsString('id="slot_abc"', $html);
        static::assertStringContainsString('<div class="loading">...</div>', $html);
    }

    public function testRenderFragment(): void
    {
        $renderer = new SlotRenderer();
        $slot = new DeferredSlot('slot_abc', 'template.twig', 'skeleton.twig');

        $html = $renderer->renderFragment($slot, '<div class="reviews">Content</div>');

        // Template with content
        static::assertStringContainsString('<template id="tmpl_abc">', $html);
        static::assertStringContainsString('<div class="reviews">Content</div>', $html);
        static::assertStringContainsString('</template>', $html);

        // Self-removing script
        static::assertStringContainsString('<script id="script_abc">', $html);
        static::assertStringContainsString("getElementById('tmpl_abc')", $html);
        static::assertStringContainsString("getElementById('slot_abc')", $html);
        static::assertStringContainsString('replaceChildren', $html);
        static::assertStringContainsString("getElementById('script_abc')?.remove()", $html);
    }

    public function testRenderFragmentExtractsIdSuffix(): void
    {
        $renderer = new SlotRenderer();
        $slot = new DeferredSlot('slot_xyz789', 'template.twig', 'skeleton.twig');

        $html = $renderer->renderFragment($slot, 'Content');

        static::assertStringContainsString('id="tmpl_xyz789"', $html);
        static::assertStringContainsString('id="script_xyz789"', $html);
    }

    public function testRenderFragmentHandlesCustomId(): void
    {
        $renderer = new SlotRenderer();
        $slot = new DeferredSlot('my-custom-id', 'template.twig', 'skeleton.twig');

        $html = $renderer->renderFragment($slot, 'Content');

        // Custom ID uses md5 hash for suffix
        $expectedSuffix = substr(md5('my-custom-id'), offset: 0, length: 8);
        static::assertStringContainsString('id="tmpl_' . $expectedSuffix . '"', $html);
        static::assertStringContainsString('id="script_' . $expectedSuffix . '"', $html);
        // But slot reference uses the original ID
        static::assertStringContainsString("getElementById('my-custom-id')", $html);
    }

    public function testRenderPlaceholderEscapesId(): void
    {
        $renderer = new SlotRenderer();
        // Malicious ID with XSS attempt
        $slot = new DeferredSlot('"><script>alert(1)</script><div id="', 'template.twig', 'skeleton.twig');

        $html = $renderer->renderPlaceholder($slot, '<div>loading</div>');

        // ID should be escaped, not rendered as-is
        static::assertStringNotContainsString('<script>alert(1)</script>', $html);
        static::assertStringContainsString('&quot;', $html);
    }
}

<?php

// packages/twig-streaming/tests/Unit/Twig/NodeVisitor/StreamingProfilerNodeVisitorTest.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Unit\Twig\NodeVisitor;

use PHPUnit\Framework\TestCase;
use Toppy\TwigStreaming\Twig\NodeVisitor\StreamingProfilerNodeVisitor;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/** Tests for StreamingProfilerNodeVisitor */
final class StreamingProfilerNodeVisitorTest extends TestCase
{
    public function testInjectsProfilerCallsIntoTemplate(): void
    {
        $loader = new ArrayLoader([
            'test.html.twig' => '<h1>Hello</h1>',
        ]);
        $twig = new Environment($loader);
        $twig->addNodeVisitor(new StreamingProfilerNodeVisitor());

        $source = $twig->compileSource($twig->getLoader()->getSourceContext('test.html.twig'));

        static::assertStringContainsString('enterTemplate', $source);
        static::assertStringContainsString('leaveTemplate', $source);
        static::assertStringContainsString('test.html.twig', $source);
    }

    public function testInjectsBlockProfilerCalls(): void
    {
        $loader = new ArrayLoader([
            'test.html.twig' => '{% block content %}Hello{% endblock %}',
        ]);
        $twig = new Environment($loader);
        $twig->addNodeVisitor(new StreamingProfilerNodeVisitor());

        $source = $twig->compileSource($twig->getLoader()->getSourceContext('test.html.twig'));

        static::assertStringContainsString('enterBlock', $source);
        static::assertStringContainsString('leaveBlock', $source);
        static::assertStringContainsString('content', $source);
    }
}

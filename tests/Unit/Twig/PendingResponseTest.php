<?php
// packages/twig-streaming/tests/Unit/Twig/PendingResponseTest.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Unit\Twig;

use Amp\Future;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Toppy\TwigStreaming\Twig\PendingResponse;

final class PendingResponseTest extends TestCase
{
    /**
     * Helper to create a render callback that executes gates (mimics StreamingTemplateRenderer).
     */
    private function createRenderCallback(?bool &$renderCalled = null): \Closure
    {
        return function (array $gates) use (&$renderCalled) {
            // Execute gates (like StreamingTemplateRenderer does)
            foreach ($gates as $gate) {
                $result = $gate['future']->await();
                ($gate['gate'])($result);
            }

            $renderCalled = true;
            return new StreamedResponse(fn() => null);
        };
    }

    public function testAwaitBeforeWithPassingGate(): void
    {
        $future = Future::complete(['id' => 1, 'name' => 'Test']);

        $renderCalled = false;
        $pending = new PendingResponse($this->createRenderCallback($renderCalled));

        $response = $pending->awaitBefore($future, function ($data) {
            if ($data === null) {
                throw new NotFoundHttpException();
            }
        })->getResponse();

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertTrue($renderCalled);
    }

    public function testAwaitBeforeWithFailingGate(): void
    {
        $future = Future::complete(null);

        $pending = new PendingResponse($this->createRenderCallback());

        $this->expectException(NotFoundHttpException::class);

        // Gate is now deferred - exception thrown when getResponse() executes the callback
        $pending->awaitBefore($future, function ($data) {
            if ($data === null) {
                throw new NotFoundHttpException();
            }
        })->getResponse();
    }

    public function testAwaitBeforeWithMultipleFutures(): void
    {
        $future1 = Future::complete(['id' => 1]);
        $future2 = Future::complete(['id' => 2]);

        $renderCalled = false;
        $pending = new PendingResponse($this->createRenderCallback($renderCalled));

        $response = $pending
            ->awaitBefore($future1, fn($d) => $d !== null ?: throw new NotFoundHttpException())
            ->awaitBefore($future2, fn($d) => $d !== null ?: throw new NotFoundHttpException())
            ->getResponse();

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertTrue($renderCalled);
    }

    public function testGatesAreDeferred(): void
    {
        $gateExecuted = false;
        $future = Future::complete('data');

        $pending = new PendingResponse(function (array $gates) use (&$gateExecuted) {
            // At this point, gates should be passed but not yet executed
            $this->assertCount(1, $gates);

            // Execute gate now
            foreach ($gates as $gate) {
                $result = $gate['future']->await();
                ($gate['gate'])($result);
            }
            $gateExecuted = true;

            return new StreamedResponse(fn() => null);
        });

        // Adding gate should NOT execute it immediately
        $pending->awaitBefore($future, fn($d) => null);
        $this->assertFalse($gateExecuted);

        // Gate should execute when getResponse() is called
        $pending->getResponse();
        $this->assertTrue($gateExecuted);
    }
}

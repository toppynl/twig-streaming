<?php

// packages/twig-streaming/tests/Unit/Twig/PendingResponseTest.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Tests\Unit\Twig;

use Amp\Future;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Toppy\TwigStreaming\Twig\PendingResponse;

/**
 * @mago-expect analysis:impossible-condition
 * @mago-expect analysis:impossible-type-comparison
 * @mago-expect analysis:invalid-callable
 * @mago-expect analysis:less-specific-nested-argument-type
 * @mago-expect analysis:mixed-array-access
 * @mago-expect analysis:mixed-assignment
 * @mago-expect analysis:mixed-method-access
 * @mago-expect analysis:redundant-comparison
 * @mago-expect analysis:redundant-condition
 */
final class PendingResponseTest extends TestCase
{
    /**
     * Helper to create a render callback that executes gates (mimics StreamingTemplateRenderer).
     */
    private function createRenderCallback(?bool &$renderCalled = null): \Closure
    {
        return static function (array $gates) use (&$renderCalled): StreamedResponse {
            // Execute gates (like StreamingTemplateRenderer does)
            foreach ($gates as $gate) {
                $result = $gate['future']->await();
                $gate['gate']($result);
            }

            $renderCalled = true;
            return new StreamedResponse(static fn() => null);
        };
    }

    public function testAwaitBeforeWithPassingGate(): void
    {
        $future = Future::complete(['id' => 1, 'name' => 'Test']);

        $renderCalled = false;
        $pending = new PendingResponse($this->createRenderCallback($renderCalled));

        $response = $pending
            ->awaitBefore($future, static function (mixed $data): void {
                if ($data === null) {
                    throw new NotFoundHttpException();
                }
            })
            ->getResponse();

        static::assertInstanceOf(StreamedResponse::class, $response);
        static::assertTrue($renderCalled);
    }

    public function testAwaitBeforeWithFailingGate(): void
    {
        $future = Future::complete(null);

        $pending = new PendingResponse($this->createRenderCallback());

        static::expectException(NotFoundHttpException::class);

        // Gate is now deferred - exception thrown when getResponse() executes the callback
        $pending
            ->awaitBefore($future, static function (mixed $data): void {
                if ($data === null) {
                    throw new NotFoundHttpException();
                }
            })
            ->getResponse();
    }

    public function testAwaitBeforeWithMultipleFutures(): void
    {
        $future1 = Future::complete(['id' => 1]);
        $future2 = Future::complete(['id' => 2]);

        $renderCalled = false;
        $pending = new PendingResponse($this->createRenderCallback($renderCalled));

        $response = $pending
            ->awaitBefore($future1, static fn($d) => $d !== null ? true : throw new NotFoundHttpException())
            ->awaitBefore($future2, static fn($d) => $d !== null ? true : throw new NotFoundHttpException())
            ->getResponse();

        static::assertInstanceOf(StreamedResponse::class, $response);
        static::assertTrue($renderCalled);
    }

    public function testGatesAreDeferred(): void
    {
        $gateExecuted = false;
        $future = Future::complete('data');

        $pending = new PendingResponse(static function (array $gates) use (&$gateExecuted): StreamedResponse {
            // At this point, gates should be passed but not yet executed
            static::assertCount(1, $gates);

            // Execute gate now
            foreach ($gates as $gate) {
                $result = $gate['future']->await();
                $gate['gate']($result);
            }
            $gateExecuted = true;

            return new StreamedResponse(static fn() => null);
        });

        // Adding gate should NOT execute it immediately
        $pending->awaitBefore($future, static fn($d) => null);
        static::assertFalse($gateExecuted);

        // Gate should execute when getResponse() is called
        $pending->getResponse();
        static::assertTrue($gateExecuted);
    }
}

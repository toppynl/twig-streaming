<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Twig;

use Amp\Future;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fluent builder for streaming responses with gate conditions.
 *
 * Allows awaiting critical Futures before output starts streaming.
 * Gates are deferred until AFTER ViewModels start loading (preloadAll),
 * but BEFORE any output is sent - enabling proper HTTP status codes.
 */
final class PendingResponse
{
    /** @var list<array{future: Future<mixed>, gate: callable}> */
    private array $gates = [];

    /**
     * @param \Closure(list<array{future: Future<mixed>, gate: callable}>): StreamedResponse $renderCallback
     * @param ?\Closure(): void $earlyHintsCallback Called to send 103 Early Hints before response
     */
    public function __construct(
        private readonly \Closure $renderCallback,
        private readonly ?\Closure $earlyHintsCallback = null,
    ) {}

    /**
     * Add a gate condition that must pass before streaming starts.
     *
     * Gates are NOT awaited immediately - they're deferred until after
     * preloadAll() starts all ViewModels in parallel. This ensures the
     * critical ViewModel is already in-flight when we await it.
     *
     * The gate callback receives the awaited Future result and can throw
     * to abort the response (e.g., NotFoundHttpException).
     *
     * @template T
     * @param Future<T> $future
     * @param callable(T): void $gate Called with future result; throw to abort
     * @return self Returns self for chaining; call getResponse() to finalize
     */
    public function awaitBefore(Future $future, callable $gate): self
    {
        $this->gates[] = ['future' => $future, 'gate' => $gate];

        return $this;
    }

    /**
     * Get the final StreamedResponse after all gates pass.
     *
     * Sends 103 Early Hints before returning the response if configured.
     * Gates are executed inside the streaming callback, after preloadAll()
     * but before any output - allowing proper HTTP status on failure.
     */
    public function getResponse(): StreamedResponse
    {
        // Send 103 Early Hints BEFORE returning the response
        // This ensures hints are sent before Symfony sends 200 headers
        if ($this->earlyHintsCallback !== null) {
            ($this->earlyHintsCallback)();
        }

        return ($this->renderCallback)($this->gates);
    }
}

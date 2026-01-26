<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Twig;

use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Toppy\AsyncViewModel\ViewModelManagerInterface;
use Toppy\TwigStreaming\EarlyHints\EarlyHintsProviderInterface;
use Toppy\TwigStreaming\Slot\DeferredSlot;
use Toppy\TwigStreaming\Slot\SlotRegistryInterface;
use Toppy\TwigStreaming\Slot\SlotRenderer;
use Twig\Environment;
use Twig\TemplateWrapper;

/**
 * Renders templates with streaming output and deferred slot support.
 *
 * Uses Twig's use_yield generator mode to stream HTML chunks
 * as they become available. Supports defer(true) slots that stream
 * content fragments after the initial shell.
 */
// @mago-ignore analysis:mixed-assignment - Twig Future::await() returns mixed; vendor limitation
final class StreamingTemplateRenderer implements StreamingTemplateRendererInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ViewModelManagerInterface $viewModelManager,
        private readonly ?SlotRegistryInterface $slotRegistry = null,
        private readonly ?SlotRenderer $slotRenderer = null,
        private readonly ?Packages $assetPackages = null,
        /** @var iterable<EarlyHintsProviderInterface> */
        private readonly iterable $earlyHintsProviders = [],
        private readonly ?RequestStack $requestStack = null,
    ) {}

    /**
     * Render a template as a pending streaming response.
     *
     * @param string $templateName Template path (e.g., 'product/show.html.twig')
     * @param array<string, mixed> $context Template variables
     * @param int $status HTTP status code
     * @param array<string, string> $headers Additional headers
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    #[\Override]
    public function render(
        string $templateName,
        array $context = [],
        int $status = 200,
        array $headers = [],
    ): PendingResponse {
        // Extract Early Hints now (template is compiled/cached, this is fast)
        $hints = $this->collectEarlyHints($templateName);

        // CRITICAL: Start all ViewModels in parallel NOW, not in the streaming callback.
        // This ensures ViewModels from awaitBefore() and template discovery run concurrently.
        // If we wait until the callback, the gate's ViewModel may already be complete
        // before others start, destroying parallelism.
        $template = $this->twig->load($templateName);
        $innerTemplate = $template->unwrap();

        if (method_exists($innerTemplate, 'doPreload')) {
            /** @var list<class-string<\Toppy\AsyncViewModel\AsyncViewModel<object>>> $viewModelClasses */
            $viewModelClasses = $innerTemplate->doPreload();
            $this->viewModelManager->preloadAll($viewModelClasses);
        }

        return new PendingResponse(
            renderCallback: function (array $gates) use ($template, $context, $status, $headers): StreamedResponse {
                return new StreamedResponse(
                    callbackOrChunks: function () use ($template, $context, $gates): void {
                        $this->streamTemplateWithTemplate($template, $context, $gates);
                    },
                    status: $status,
                    headers: array_merge([
                        'X-Accel-Buffering' => 'no',
                        'Content-Type' => 'text/html; charset=UTF-8',
                    ], $headers),
                );
            },
            earlyHintsCallback: fn() => $this->sendEarlyHints($hints),
        );
    }

    /**
     * Direct render without PendingResponse (for simple cases).
     *
     * Note: Early Hints are sent immediately before returning the response.
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    #[\Override]
    public function renderDirect(
        string $templateName,
        array $context = [],
        int $status = 200,
        array $headers = [],
    ): StreamedResponse {
        // Send Early Hints before returning the response
        $hints = $this->collectEarlyHints($templateName);
        $this->sendEarlyHints($hints);

        return new StreamedResponse(
            callbackOrChunks: function () use ($templateName, $context): void {
                $this->streamTemplate($templateName, $context);
            },
            status: $status,
            headers: array_merge([
                'X-Accel-Buffering' => 'no',
                'Content-Type' => 'text/html; charset=UTF-8',
            ], $headers),
        );
    }

    /**
     * Stream a template that was pre-loaded (used by render() for early preloading).
     *
     * @param TemplateWrapper $template Pre-loaded template
     * @param array<string, mixed> $context
     * @param list<array{future: \Amp\Future<mixed>, gate: callable}> $gates
     */
    private function streamTemplateWithTemplate(TemplateWrapper $template, array $context, array $gates = []): void
    {
        // Early Hints are sent BEFORE this callback runs (in PendingResponse::getResponse)
        // ViewModels are already started via preloadAll() in render()

        // Execute gates AFTER preloadAll (futures are in-flight) but BEFORE output
        // This allows proper HTTP status codes on failure (e.g., 404)
        foreach ($gates as $gate) {
            $result = $gate['future']->await();
            $gate['gate']($result);
        }

        // Stream shell chunks as they become available
        /** @var string $chunk */
        foreach ($template->stream($context) as $chunk) {
            echo $chunk;

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }

        // Stream deferred slot fragments
        $this->streamSlotFragments();

        // Clear WebLink _links to prevent duplicate Link headers
        // (we already sent them in 103 Early Hints)
        $this->clearWebLinks();
    }

    /**
     * @param array<string, mixed> $context
     * @param list<array{future: \Amp\Future<mixed>, gate: callable}> $gates
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private function streamTemplate(string $templateName, array $context, array $gates = []): void
    {
        $template = $this->twig->load($templateName);
        $innerTemplate = $template->unwrap();

        // Early Hints are sent BEFORE this callback runs (in PendingResponse::getResponse)

        // Start all futures in parallel (non-blocking)
        if (method_exists($innerTemplate, 'doPreload')) {
            /** @var list<class-string<\Toppy\AsyncViewModel\AsyncViewModel<object>>> $viewModelClasses */
            $viewModelClasses = $innerTemplate->doPreload();
            $this->viewModelManager->preloadAll($viewModelClasses);
        }

        // Execute gates AFTER preloadAll (futures are in-flight) but BEFORE output
        // This allows proper HTTP status codes on failure (e.g., 404)
        foreach ($gates as $gate) {
            $result = $gate['future']->await();
            $gate['gate']($result);
        }

        // Stream shell chunks as they become available
        /** @var string $chunk */
        foreach ($template->stream($context) as $chunk) {
            echo $chunk;

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }

        // Stream deferred slot fragments
        $this->streamSlotFragments();

        // Clear WebLink _links to prevent duplicate Link headers
        // (we already sent them in 103 Early Hints)
        $this->clearWebLinks();
    }

    /**
     * Clear WebLink _links attribute from request.
     *
     * When we send HTTP 103 Early Hints, we've already sent the Link headers.
     * Symfony's AddLinkHeaderListener would add them again to the 200 response
     * based on _links attribute populated by preload() calls during rendering.
     * Clear it to prevent duplicates.
     */
    private function clearWebLinks(): void
    {
        $request = $this->requestStack?->getMainRequest();
        $request?->attributes->remove('_links');
    }

    /**
     * Extract Early Hints from compiled template.
     *
     * @return list<array{rel: string, href: ?string, assetPath: ?string, attributes: array<string, mixed>}>
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private function extractEarlyHints(string $templateName): array
    {
        $template = $this->twig->load($templateName);
        $innerTemplate = $template->unwrap();

        if (method_exists($innerTemplate, 'doEarlyHints')) {
            /** @var list<array{rel: string, href: ?string, assetPath: ?string, attributes: array<string, mixed>}> */
            return $innerTemplate->doEarlyHints();
        }

        return [];
    }

    /**
     * Resolve asset paths to URLs.
     *
     * @param list<array{rel: string, href: ?string, assetPath: ?string, attributes: array<string, mixed>}> $hints
     * @return list<array{rel: string, href: string, assetPath: ?string, attributes: array<string, mixed>}>
     */
    private function resolveHints(array $hints): array
    {
        $resolved = [];

        foreach ($hints as $hint) {
            if ($hint['href'] === null && $hint['assetPath'] !== null) {
                if ($this->assetPackages !== null) {
                    $hint['href'] = $this->assetPackages->getUrl($hint['assetPath']);
                } else {
                    // Can't resolve without Packages service - skip this hint
                    continue;
                }
            }

            if ($hint['href'] !== null) {
                $resolved[] = $hint;
            }
        }

        return $resolved;
    }

    /**
     * Build Link header value from hints.
     *
     * @param list<array{rel: string, href: string, attributes: array<string, mixed>}> $hints
     */
    private function buildLinkHeader(array $hints): string
    {
        $links = [];

        foreach ($hints as $hint) {
            $parts = [\sprintf('<%s>', $hint['href']), \sprintf('rel=%s', $hint['rel'])];

            foreach ($hint['attributes'] as $key => $value) {
                $parts[] = \sprintf('%s=%s', $key, (string) $value);
            }

            $links[] = implode('; ', $parts);
        }

        return implode(', ', $links);
    }

    /**
     * Collect and resolve all Early Hints from template + providers.
     *
     * @return list<array{rel: string, href: string, attributes: array<string, mixed>}>
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private function collectEarlyHints(string $templateName): array
    {
        // Get hints from compiled template
        $allHints = $this->extractEarlyHints($templateName);

        // Add hints from all providers (e.g., ImportMapEarlyHintsProvider)
        foreach ($this->earlyHintsProviders as $provider) {
            foreach ($provider->getHints() as $hint) {
                $allHints[] = [
                    'rel' => $hint['rel'],
                    'href' => $hint['href'],
                    'assetPath' => null,
                    'attributes' => $hint['attributes'],
                ];
            }
        }

        if ($allHints === []) {
            return [];
        }

        // Resolve asset paths to URLs and deduplicate by href
        $resolvedHints = $this->resolveHints($allHints);
        $uniqueHints = [];
        foreach ($resolvedHints as $hint) {
            $key = $hint['href'];
            if (!isset($uniqueHints[$key])) {
                // Strip assetPath (only needed for resolution, not for output)
                $uniqueHints[$key] = [
                    'rel' => $hint['rel'],
                    'href' => $hint['href'],
                    'attributes' => $hint['attributes'],
                ];
            }
        }

        return array_values($uniqueHints);
    }

    /**
     * Send HTTP 103 Early Hints.
     *
     * Uses PHP's native header functions to avoid Symfony's Response overhead
     * and prevent automatic header copying from 103 to final response.
     *
     * @param list<array{rel: string, href: string, attributes: array<string, mixed>}> $hints Already resolved and deduplicated
     */
    private function sendEarlyHints(array $hints): void
    {
        if ($hints === []) {
            return;
        }

        // Skip if headers already sent or SAPI doesn't support informational responses
        if (headers_sent() || !\function_exists('headers_send')) {
            return;
        }

        $linkHeader = $this->buildLinkHeader($hints);
        if ($linkHeader === '') {
            return;
        }

        // Send 103 Early Hints using PHP's native functions
        // This avoids Symfony's Response which adds default headers
        header('Link: ' . $linkHeader, replace: false);
        headers_send(103);

        // Remove Link header to prevent PHP from copying it to the final 200 response
        // (RFC 8297 says PHP should copy headers, but we don't want duplicates)
        header_remove('Link');
    }

    private function streamSlotFragments(): void
    {
        if ($this->slotRegistry === null || $this->slotRenderer === null) {
            return;
        }

        $pending = $this->slotRegistry->getPending();

        if ($pending === []) {
            return;
        }

        // Stream fragments as futures complete (out-of-order)
        foreach ($pending as ['slot' => $slot, 'future' => $future]) {
            try {
                $content = $future->await();
                $fragment = $this->slotRenderer->renderFragment($slot, $content);
            } catch (\Throwable $e) {
                // Render fallback on error
                $fragment = $this->renderFallbackFragment($slot, $e);
            }

            echo $fragment;

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }

        // Clear registry for next request (worker mode)
        $this->slotRegistry->reset();
    }

    private function renderFallbackFragment(DeferredSlot $slot, \Throwable $error): string
    {
        if ($this->slotRenderer === null) {
            return '';
        }

        if ($slot->fallback === null) {
            // Silent fail - render empty content to clear skeleton
            return $this->slotRenderer->renderFragment($slot, '');
        }

        if ($slot->isInlineFallback) {
            // Inline string fallback
            $content = htmlspecialchars($slot->fallback, ENT_QUOTES);
        } else {
            // Template fallback
            try {
                $content = $this->twig->render($slot->fallback, ['error' => $error]);
            } catch (\Throwable) {
                $content = '<div class="error">Error loading content</div>';
            }
        }

        return $this->slotRenderer->renderFragment($slot, $content);
    }
}

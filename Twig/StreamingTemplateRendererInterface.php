<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Twig;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface StreamingTemplateRendererInterface
{
    /**
     * Render a template as a pending streaming response.
     *
     * Returns PendingResponse to allow awaitBefore() gates.
     *
     * @param string $templateName Template path
     * @param array<string, mixed> $context Template variables
     * @param int $status HTTP status code
     * @param array<string, string> $headers Additional headers
     */
    public function render(
        string $templateName,
        array $context = [],
        int $status = 200,
        array $headers = [],
    ): PendingResponse;

    /**
     * Direct render without gate support.
     *
     * @param string $templateName Template path
     * @param array<string, mixed> $context Template variables
     * @param int $status HTTP status code
     * @param array<string, string> $headers Additional headers
     */
    public function renderDirect(
        string $templateName,
        array $context = [],
        int $status = 200,
        array $headers = [],
    ): StreamedResponse;
}

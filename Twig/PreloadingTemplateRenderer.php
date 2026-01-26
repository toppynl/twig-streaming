<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Twig;

use Toppy\AsyncViewModel\ViewModelManagerInterface;
use Twig\Environment;

/**
 * Renders templates with automatic view model preloading.
 *
 * Before rendering, checks if the template has a doPreload() method
 * and calls it to start parallel data fetching.
 */
final readonly class PreloadingTemplateRenderer
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ViewModelManagerInterface $viewModelManager,
    ) {}

    /**
     * @param array<string, mixed> $context
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function render(string $templateName, array $context): string
    {
        $template = $this->twig->load($templateName);
        $innerTemplate = $template->unwrap();

        // Preload all discovered view models before rendering
        if (method_exists($innerTemplate, 'doPreload')) {
            /** @var list<class-string<\Toppy\AsyncViewModel\AsyncViewModel<object>>> $viewModelClasses */
            $viewModelClasses = $innerTemplate->doPreload();
            $this->viewModelManager->preloadAll($viewModelClasses);
        }

        return $template->render($context);
    }
}

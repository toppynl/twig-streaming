<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Bundle\DependencyInjection;

use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Toppy\AsyncViewModel\ViewModelManagerInterface;
use Toppy\TwigStreaming\EarlyHints\EarlyHintsProviderInterface;
use Toppy\TwigStreaming\Profiler\NullTemplateStreamProfiler;
use Toppy\TwigStreaming\Profiler\TemplateStreamProfilerInterface;
use Toppy\TwigStreaming\Slot\SlotRegistry;
use Toppy\TwigStreaming\Slot\SlotRegistryInterface;
use Toppy\TwigStreaming\Slot\SlotRenderer;
use Toppy\TwigStreaming\Twig\EarlyHintsExtension;
use Toppy\TwigStreaming\Twig\PreloadingTemplateRenderer;
use Toppy\TwigStreaming\Twig\StreamingProfilerExtension;
use Toppy\TwigStreaming\Twig\StreamingProfilerRuntime;
use Toppy\TwigStreaming\Twig\StreamingTemplateRenderer;
use Toppy\TwigStreaming\Twig\StreamingTemplateRendererInterface;

class ToppyTwigStreamingExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // Register slot services
        $container->setDefinition(SlotRegistry::class, new Definition(SlotRegistry::class))
            ->setAutoconfigured(true);
        $container->setAlias(SlotRegistryInterface::class, SlotRegistry::class);

        $container->setDefinition(SlotRenderer::class, new Definition(SlotRenderer::class));

        // Register template stream profiler
        $this->registerTemplateStreamProfiler($container);

        // Register StreamingProfilerRuntime with twig.runtime tag
        $container->setDefinition(StreamingProfilerRuntime::class, new Definition(StreamingProfilerRuntime::class))
            ->setAutowired(true)
            ->addTag('twig.runtime');

        // Register StreamingProfilerNodeVisitor (only in debug mode)
        $this->registerStreamingNodeVisitor($container);

        // Register EarlyHintsExtension with twig.extension tag
        $container->setDefinition(EarlyHintsExtension::class, new Definition(EarlyHintsExtension::class))
            ->addTag('twig.extension');

        // Register PreloadingTemplateRenderer (autowired)
        $container->setDefinition(PreloadingTemplateRenderer::class, new Definition(PreloadingTemplateRenderer::class))
            ->setAutowired(true)
            ->setAutoconfigured(true);

        // Register StreamingTemplateRenderer (autowired) with slot dependencies
        $container->setDefinition(StreamingTemplateRenderer::class, new Definition(StreamingTemplateRenderer::class))
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArgument('$viewModelManager', new Reference(ViewModelManagerInterface::class))
            ->setArgument('$slotRegistry', new Reference(SlotRegistryInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$slotRenderer', new Reference(SlotRenderer::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$assetPackages', new Reference(Packages::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$earlyHintsProviders', new TaggedIteratorArgument('toppy.early_hints_provider'))
            ->setArgument('$requestStack', new Reference(RequestStack::class));
        $container->setAlias(StreamingTemplateRendererInterface::class, StreamingTemplateRenderer::class);
    }

    private function registerTemplateStreamProfiler(ContainerBuilder $container): void
    {
        $isDebug = $container->hasParameter('kernel.debug')
            ? $container->getParameter('kernel.debug')
            : true;

        // Register null profiler (used in prod, or when full profiler not available)
        $container->setDefinition(NullTemplateStreamProfiler::class, new Definition(NullTemplateStreamProfiler::class));

        // Default to NullTemplateStreamProfiler - full profiler can be registered by parent bundle
        $container->setAlias(TemplateStreamProfilerInterface::class, NullTemplateStreamProfiler::class);
    }

    private function registerStreamingNodeVisitor(ContainerBuilder $container): void
    {
        $isDebug = $container->hasParameter('kernel.debug')
            ? $container->getParameter('kernel.debug')
            : true;

        if (!$isDebug) {
            return;
        }

        // Node visitors must be registered through extensions, not standalone
        $container->setDefinition(StreamingProfilerExtension::class, new Definition(StreamingProfilerExtension::class))
            ->addTag('twig.extension');
    }
}

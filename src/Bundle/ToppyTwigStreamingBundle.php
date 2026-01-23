<?php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Bundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Toppy\TwigStreaming\Bundle\DependencyInjection\ToppyTwigStreamingExtension;

class ToppyTwigStreamingBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new ToppyTwigStreamingExtension();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }
}

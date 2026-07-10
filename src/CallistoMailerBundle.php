<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class CallistoMailerBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Automatically register the ORM entity mappings for Doctrine
        $container->extension('doctrine', [
            'orm' => [
                'mappings' => [
                    'CallistoMailerBundle' => [
                        'type' => 'attribute',
                        'dir' => __DIR__ . '/Entity',
                        'prefix' => 'Callisto\CallistoMailer\Entity',
                        'alias' => 'CallistoMailer',
                    ],
                ],
            ],
        ]);

        // Automatically register Twig namespace path to look up layouts easily via @CallistoMailer/...
        $container->extension('twig', [
            'paths' => [
                \dirname(__DIR__) . '/templates' => 'CallistoMailer',
            ],
        ]);
    }
}

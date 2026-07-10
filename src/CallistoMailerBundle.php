<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Callisto\CallistoMailer\Service\DatabaseMailerService;

class CallistoMailerBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('layouts')
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                ->end()
                ->scalarNode('default_locale')
                    ->defaultValue('fr')
                ->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');

        $container->services()
            ->get(DatabaseMailerService::class)
            ->arg('$customLayouts', $config['layouts'] ?? [])
            ->arg('$defaultLocale', $config['default_locale'] ?? 'fr');
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

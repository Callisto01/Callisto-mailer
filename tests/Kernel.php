<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Tests;

use Callisto\CallistoMailer\CallistoMailerBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (str_starts_with($id, 'Callisto\\CallistoMailer\\')) {
                $definition->setPublic(true);
            }
        }
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new DoctrineBundle(),
            new CallistoMailerBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return __DIR__ . '/Fixtures';
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/CallistoMailerBundle/cache/' . $this->getEnvironment();
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/CallistoMailerBundle/logs/' . $this->getEnvironment();
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        // Configure basic framework settings
        $container->loadFromExtension('framework', [
            'secret' => 'test_secret',
            'test' => true,
            'http_method_override' => false,
            'php_errors' => [
                'log' => true,
            ],
            'mailer' => [
                'dsn' => 'null://default',
            ],
        ]);

        // Configure twig
        $container->loadFromExtension('twig', [
            'default_path' => '%kernel.project_dir%/templates',
            'strict_variables' => true,
        ]);

        // Configure Doctrine to use in-memory SQLite
        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ],
            'orm' => [
                'default_entity_manager' => 'default',
                'entity_managers' => [
                    'default' => [
                        'naming_strategy' => 'doctrine.orm.naming_strategy.default',
                        'auto_mapping' => true,
                    ],
                ],
            ],
        ]);
    }
}

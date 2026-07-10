<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Tests;

use Callisto\CallistoMailer\Service\DatabaseMailerService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

class CallistoMailerBundleTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testBundleIsLoadedAndRegistered(): void
    {
        $kernel = self::$kernel;
        $bundles = $kernel->getBundles();
        $this->assertArrayHasKey('CallistoMailerBundle', $bundles);
    }

    public function testServicesAreRegisteredInContainer(): void
    {
        $container = self::getContainer();

        $this->assertTrue($container->has(DatabaseMailerService::class));
    }

    public function testTwigPathsAreConfigured(): void
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get(Environment::class);
        $loader = $twig->getLoader();

        if ($loader instanceof \Twig\Loader\FilesystemLoader) {
            $paths = $loader->getPaths('CallistoMailer');
            $this->assertNotEmpty($paths);
            
            // Check that the layout templates can be resolved
            $this->assertTrue($loader->exists('@CallistoMailer/layouts/base_bootstrap.html.twig'));
            $this->assertTrue($loader->exists('@CallistoMailer/layouts/base_tailwind.html.twig'));
        } else {
            $this->fail('Twig loader is not an instance of FilesystemLoader.');
        }
    }
}

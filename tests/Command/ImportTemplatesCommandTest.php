<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Tests\Command;

use Callisto\CallistoMailer\Command\ImportTemplatesCommand;
use Callisto\CallistoMailer\Repository\MailTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ImportTemplatesCommandTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?MailTemplateRepository $repository = null;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get('doctrine.orm.default_entity_manager');
        $this->repository = $container->get(MailTemplateRepository::class);

        // Create the database schema
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->entityManager) {
            $this->entityManager->close();
            $this->entityManager = null;
        }
    }

    public function testExecuteImportsTemplatesCorrectly(): void
    {
        $kernel = self::$kernel;
        $application = new Application($kernel);

        $command = $application->find('callisto:mailer:import-templates');
        $commandTester = new CommandTester($command);

        // Run command scanning our fixture folder relative to %kernel.project_dir%
        $commandTester->execute([
            'directory' => 'templates/emails',
        ]);

        $commandTester->assertCommandIsSuccessful();
        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('welcome', $output);
        $this->assertStringContainsString('Welcome, {{ name }}!', $output);
        $this->assertStringContainsString('bootstrap', $output);
        $this->assertStringContainsString('Synchronization complete. 1 templates created, 0 templates updated.', $output);

        // Verify that the template was stored in the database
        $template = $this->repository->findOneBy(['code' => 'welcome']);
        $this->assertNotNull($template);
        $this->assertSame('Welcome, {{ name }}!', $template->getSubject());
        $this->assertSame('bootstrap', $template->getLayout());
        $this->assertStringContainsString('Thank you for signing up for Callisto Mailer!', $template->getContent());
    }

    public function testExecuteDryRunDoesNotPersistToDatabase(): void
    {
        $kernel = self::$kernel;
        $application = new Application($kernel);

        $command = $application->find('callisto:mailer:import-templates');
        $commandTester = new CommandTester($command);

        // Run command in dry-run mode
        $commandTester->execute([
            'directory' => 'templates/emails',
            '--dry-run' => true,
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Dry run mode enabled: no database writes will be executed.', $output);
        $this->assertStringContainsString('Dry run complete. Found 1 templates to import/update.', $output);

        // Verify that the template was NOT stored in the database
        $template = $this->repository->findOneBy(['code' => 'welcome']);
        $this->assertNull($template);
    }
}

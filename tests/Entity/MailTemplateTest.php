<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Tests\Entity;

use Callisto\CallistoMailer\Entity\MailTemplate;
use Callisto\CallistoMailer\Repository\MailTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MailTemplateTest extends KernelTestCase
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

    public function testCreateAndRetrieveMailTemplate(): void
    {
        $template = new MailTemplate();
        $template->setCode('welcome_test')
            ->setSubject('Welcome {{ name }}')
            ->setLayout('bootstrap')
            ->setContent('<p>Hello {{ name }}</p>');

        $this->repository->save($template, true);

        /** @var MailTemplate $retrieved */
        $retrieved = $this->repository->findOneBy(['code' => 'welcome_test']);

        $this->assertNotNull($retrieved);
        $this->assertSame('welcome_test', $retrieved->getCode());
        $this->assertSame('Welcome {{ name }}', $retrieved->getSubject());
        $this->assertSame('bootstrap', $retrieved->getLayout());
        $this->assertSame('<p>Hello {{ name }}</p>', $retrieved->getContent());
        $this->assertNotNull($retrieved->getId());
    }

    public function testDeleteMailTemplate(): void
    {
        $template = new MailTemplate();
        $template->setCode('to_delete')
            ->setSubject('Delete me')
            ->setLayout('tailwind')
            ->setContent('To be deleted');

        $this->repository->save($template, true);

        $retrieved = $this->repository->findOneBy(['code' => 'to_delete']);
        $this->assertNotNull($retrieved);

        $this->repository->remove($retrieved, true);

        $retrievedAfterDelete = $this->repository->findOneBy(['code' => 'to_delete']);
        $this->assertNull($retrievedAfterDelete);
    }
}

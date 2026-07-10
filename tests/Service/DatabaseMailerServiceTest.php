<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Tests\Service;

use Callisto\CallistoMailer\Entity\MailTemplate;
use Callisto\CallistoMailer\Repository\MailTemplateRepository;
use Callisto\CallistoMailer\Service\DatabaseMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class DatabaseMailerServiceTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?MailTemplateRepository $repository = null;
    private ?DatabaseMailerService $mailerService = null;
    private $mailerMock;

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

        // Mock Symfony Mailer
        $this->mailerMock = $this->createMock(MailerInterface::class);

        // Instantiate DatabaseMailerService with our mock mailer, real Twig environment and repository
        $twig = $container->get(Environment::class);
        $this->mailerService = new DatabaseMailerService(
            $this->mailerMock,
            $twig,
            $this->repository
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        if ($this->entityManager) {
            $this->entityManager->close();
            $this->entityManager = null;
        }
    }

    public function testRenderThrowsExceptionIfTemplateNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mail template with code "non_existent" not found in the database.');

        $this->mailerService->render('non_existent');
    }

    public function testRenderCompilesSubjectAndBodyCorrectly(): void
    {
        $template = new MailTemplate();
        $template->setCode('welcome_test')
            ->setSubject('Welcome {{ name }}!')
            ->setLayout('bootstrap')
            ->setContent('<p>Thank you for joining, {{ name }}!</p>');

        $this->repository->save($template, true);

        $result = $this->mailerService->render('welcome_test', ['name' => 'Alice']);

        $this->assertSame('Welcome Alice!', $result['subject']);
        $this->assertStringContainsString('Welcome Alice!', $result['html']);
        $this->assertStringContainsString('<p>Thank you for joining, Alice!</p>', $result['html']);
        
        // Assert that Bootstrap styling exists in the returned layout
        $this->assertStringContainsString('#0d6efd', $result['html']);
        $this->assertStringContainsString('btn-primary', $result['html']);
    }

    public function testSendSendsEmailWithCorrectParameters(): void
    {
        $template = new MailTemplate();
        $template->setCode('activation_test')
            ->setSubject('Activate your account, {{ username }}')
            ->setLayout('tailwind')
            ->setContent('Your code is {{ code }}');

        $this->repository->save($template, true);

        // Expect the mailer send() method to be called exactly once
        $this->mailerMock->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getTo()[0]->getAddress() === 'user@example.com'
                    && $email->getSubject() === 'Activate your account, Bob'
                    && str_contains($email->getHtmlBody(), 'Your code is 12345')
                    && str_contains($email->getHtmlBody(), '#4f46e5') // Tailwind layout color
                    && str_contains($email->getHtmlBody(), 'btn-indigo') // Tailwind layout btn
                    && $email->getHeaders()->get('X-Custom-Header')->getBody() === 'CustomValue'
                    && $email->getHeaders()->get('X-Callback-Header')->getBody() === 'Executed'
                    && $email->getFrom()[0]->getAddress() === 'sender@example.com';
            }));

        $this->mailerService->send(
            'activation_test',
            'user@example.com',
            ['username' => 'Bob', 'code' => '12345'],
            'sender@example.com',
            ['X-Custom-Header' => 'CustomValue'],
            function (Email $email) {
                // Modifying callback
                $email->getHeaders()->addTextHeader('X-Callback-Header', 'Executed');
            }
        );
    }
}

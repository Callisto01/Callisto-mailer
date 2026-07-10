<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Tests\Service;

use Callisto\CallistoMailer\Entity\MailTemplate;
use Callisto\CallistoMailer\Event\AfterTemplateMailSendEvent;
use Callisto\CallistoMailer\Event\BeforeTemplateMailSendEvent;
use Callisto\CallistoMailer\Exception\MissingTemplateVariablesException;
use Callisto\CallistoMailer\Repository\MailTemplateRepository;
use Callisto\CallistoMailer\Service\DatabaseMailerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class DatabaseMailerServiceTest extends TestCase
{
    private MailerInterface $mailerMock;
    private Environment $twig;
    private MailTemplateRepository $repositoryMock;
    private EventDispatcherInterface $eventDispatcherMock;
    private DatabaseMailerService $service;

    protected function setUp(): void
    {
        $this->mailerMock = $this->createMock(MailerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        
        $loader = new ArrayLoader([
            '@CallistoMailer/layouts/base_tailwind.html.twig' => '<html>Layout {{ subject }} - {{ content }}</html>',
            '@CallistoMailer/layouts/base_bootstrap.html.twig' => '<html>Layout {{ subject }} - {{ content }}</html>',
            'emails/layouts/modern.html.twig' => '<html>Custom Layout {{ subject }} - {{ content }}</html>',
            '@App/layouts/my_custom_direct.html.twig' => '<html>Direct Layout {{ subject }} - {{ content }}</html>',
        ]);
        $this->twig = new Environment($loader);
        
        $this->repositoryMock = $this->createMock(MailTemplateRepository::class);

        $this->service = new DatabaseMailerService(
            $this->mailerMock,
            $this->twig,
            $this->repositoryMock,
            $this->eventDispatcherMock,
            [
                'custom_modern' => 'emails/layouts/modern.html.twig'
            ],
            'fr'
        );
    }

    public function testRenderWithDefaultLayout(): void
    {
        $template = new MailTemplate();
        $template->setCode('test_code');
        $template->setSubject('Hello {{ name }}');
        $template->setLayout('tailwind');
        $template->setContent('Welcome to {{ platform }}');
        $template->setLocale('fr');

        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'test_code', 'locale' => 'fr'])
            ->willReturn($template);

        $result = $this->service->render('test_code', ['name' => 'John', 'platform' => 'Callisto'], 'fr');

        $this->assertSame('Hello John', $result['subject']);
        $this->assertSame('<html>Layout Hello John - Welcome to Callisto</html>', $result['html']);
    }

    public function testRenderWithLocaleFallback(): void
    {
        $template = new MailTemplate();
        $template->setCode('test_code');
        $template->setSubject('Hello');
        $template->setLayout('tailwind');
        $template->setContent('Body');
        $template->setLocale('fr');

        // First findBy for 'en' should return null, second findBy for default 'fr' returns template
        $this->repositoryMock
            ->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnMap([
                [['code' => 'test_code', 'locale' => 'en'], null],
                [['code' => 'test_code', 'locale' => 'fr'], $template]
            ]);

        $result = $this->service->render('test_code', [], 'en');
        $this->assertSame('Hello', $result['subject']);
    }

    public function testRenderThrowsExceptionOnMissingExpectedVariables(): void
    {
        $template = new MailTemplate();
        $template->setCode('test_code');
        $template->setSubject('Hello');
        $template->setLayout('tailwind');
        $template->setContent('Body');
        $template->setExpectedVariables(['user.firstName', 'orderId']);

        $this->repositoryMock
            ->method('findOneBy')
            ->willReturn($template);

        $this->expectException(MissingTemplateVariablesException::class);
        $this->expectExceptionMessage('Missing expected variables for mail template "test_code" (locale: "fr"): user.firstName, orderId');

        // Context contains "user" array but lacks "firstName" nested key, and lacks "orderId" entirely
        $this->service->render('test_code', ['user' => ['lastName' => 'Doe']]);
    }

    public function testRenderWithCustomRegisteredLayout(): void
    {
        $template = new MailTemplate();
        $template->setCode('custom_code');
        $template->setSubject('Subject');
        $template->setLayout('custom_modern');
        $template->setContent('Body');

        $this->repositoryMock
            ->method('findOneBy')
            ->willReturn($template);

        $result = $this->service->render('custom_code');
        
        $this->assertSame('Subject', $result['subject']);
        $this->assertSame('<html>Custom Layout Subject - Body</html>', $result['html']);
    }

    public function testRenderWithDirectPathLayout(): void
    {
        $template = new MailTemplate();
        $template->setCode('direct_code');
        $template->setSubject('Subject');
        $template->setLayout('@App/layouts/my_custom_direct.html.twig');
        $template->setContent('Body');

        $this->repositoryMock
            ->method('findOneBy')
            ->willReturn($template);

        $result = $this->service->render('direct_code');
        
        $this->assertSame('Subject', $result['subject']);
        $this->assertSame('<html>Direct Layout Subject - Body</html>', $result['html']);
    }

    public function testRenderThrowsExceptionOnInvalidLayout(): void
    {
        $template = new MailTemplate();
        $template->setCode('invalid_code');
        $template->setSubject('Subject');
        $template->setLayout('unknown_layout');
        $template->setContent('Body');

        $this->repositoryMock
            ->method('findOneBy')
            ->willReturn($template);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Layout "unknown_layout" is not registered.');

        $this->service->render('invalid_code');
    }

    public function testSendDispatchesEventsAndProcessesAttachments(): void
    {
        $template = new MailTemplate();
        $template->setCode('send_code');
        $template->setSubject('Mail Subject');
        $template->setLayout('bootstrap');
        $template->setContent('Mail Body');
        $template->setLocale('fr');

        $this->repositoryMock
            ->method('findOneBy')
            ->willReturn($template);

        $tempFile = tempnam(sys_get_temp_dir(), 'mail_attach');
        file_put_contents($tempFile, 'file contents');

        $dataPart = new DataPart('part contents', 'raw.txt', 'text/plain');

        // Expect EventDispatcher to receive Before and After events
        $this->eventDispatcherMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof BeforeTemplateMailSendEvent || $event instanceof AfterTemplateMailSendEvent;
            }));

        $this->mailerMock
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $attachments = $email->getAttachments();
                return $email->getTo()[0]->getAddress() === 'recipient@example.com'
                    && $email->getSubject() === 'Mail Subject'
                    && count($attachments) === 2;
            }));

        $this->service->send(
            code: 'send_code',
            recipient: 'recipient@example.com',
            context: [],
            sender: 'sender@example.com',
            locale: 'fr',
            attachments: [$tempFile, $dataPart]
        );

        @unlink($tempFile);
    }

    public function testListTemplates(): void
    {
        $template1 = new MailTemplate();
        $template1->setCode('tpl_1');
        $template2 = new MailTemplate();
        $template2->setCode('tpl_2');

        $this->repositoryMock
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$template1, $template2]);

        $result = $this->service->listTemplates();
        $this->assertCount(2, $result);
        $this->assertSame($template1, $result[0]);
        $this->assertSame($template2, $result[1]);
    }

    public function testListTemplatesAsArray(): void
    {
        $template = new MailTemplate();
        $template->setCode('tpl_1');
        $template->setSubject('Subject 1');
        $template->setLayout('tailwind');
        $template->setContent('Content 1');
        $template->setLocale('en');
        $template->setExpectedVariables(['var1']);

        $this->repositoryMock
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$template]);

        $result = $this->service->listTemplatesAsArray();
        $this->assertCount(1, $result);
        $this->assertSame('tpl_1', $result[0]['code']);
        $this->assertSame('en', $result[0]['locale']);
        $this->assertSame(['var1'], $result[0]['expectedVariables']);
    }

    public function testSaveTemplateCreatesNew(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'new_tpl', 'locale' => 'en'])
            ->willReturn(null);

        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (MailTemplate $template) {
                return $template->getCode() === 'new_tpl'
                    && $template->getLocale() === 'en'
                    && $template->getSubject() === 'Subject'
                    && $template->getExpectedVariables() === ['var'];
            }), true);

        $result = $this->service->saveTemplate('new_tpl', 'Subject', 'Content', 'bootstrap', 'en', ['var']);
        $this->assertSame('new_tpl', $result->getCode());
        $this->assertSame('en', $result->getLocale());
    }

    public function testSaveTemplateUpdatesExisting(): void
    {
        $template = new MailTemplate();
        $template->setCode('existing_tpl');
        $template->setLocale('fr');

        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'existing_tpl', 'locale' => 'fr'])
            ->willReturn($template);

        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($template, true);

        $result = $this->service->saveTemplate('existing_tpl', 'Updated Subject', 'Updated Content', 'tailwind', 'fr', []);
        $this->assertSame('existing_tpl', $result->getCode());
        $this->assertSame('Updated Subject', $result->getSubject());
    }

    public function testUpdateTemplate(): void
    {
        $template = new MailTemplate();
        $template->setCode('tpl_to_update');
        $template->setLocale('fr');

        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'tpl_to_update', 'locale' => 'fr'])
            ->willReturn($template);

        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($template, true);

        $result = $this->service->updateTemplate('tpl_to_update', [
            'subject' => 'New Subject',
            'expectedVariables' => ['a', 'b']
        ], 'fr');

        $this->assertSame('New Subject', $result->getSubject());
        $this->assertSame(['a', 'b'], $result->getExpectedVariables());
    }

    public function testUpdateTemplateThrowsExceptionOnNotFound(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'not_found', 'locale' => 'fr'])
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mail template with code "not_found" and locale "fr" not found.');

        $this->service->updateTemplate('not_found', ['subject' => 'Subject'], 'fr');
    }

    public function testDeleteTemplateReturnsTrue(): void
    {
        $template = new MailTemplate();
        $template->setCode('tpl_to_delete');
        $template->setLocale('en');

        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'tpl_to_delete', 'locale' => 'en'])
            ->willReturn($template);

        $this->repositoryMock
            ->expects($this->once())
            ->method('remove')
            ->with($template, true);

        $result = $this->service->deleteTemplate('tpl_to_delete', 'en');
        $this->assertTrue($result);
    }

    public function testDeleteTemplateReturnsFalse(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'not_found', 'locale' => 'en'])
            ->willReturn(null);

        $result = $this->service->deleteTemplate('not_found', 'en');
        $this->assertFalse($result);
    }
}

<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Tests\Service;

use Callisto\CallistoMailer\Entity\MailTemplate;
use Callisto\CallistoMailer\Repository\MailTemplateRepository;
use Callisto\CallistoMailer\Service\DatabaseMailerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class DatabaseMailerServiceTest extends TestCase
{
    private MailerInterface $mailerMock;
    private Environment $twig;
    private MailTemplateRepository $repositoryMock;
    private DatabaseMailerService $service;

    protected function setUp(): void
    {
        $this->mailerMock = $this->createMock(MailerInterface::class);
        
        // Use a real Twig Environment with ArrayLoader to avoid mocking final classes
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
            [
                'custom_modern' => 'emails/layouts/modern.html.twig'
            ]
        );
    }

    public function testRenderWithDefaultLayout(): void
    {
        $template = new MailTemplate();
        $template->setCode('test_code');
        $template->setSubject('Hello {{ name }}');
        $template->setLayout('tailwind');
        $template->setContent('Welcome to {{ platform }}');

        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'test_code'])
            ->willReturn($template);

        $result = $this->service->render('test_code', ['name' => 'John', 'platform' => 'Callisto']);

        $this->assertSame('Hello John', $result['subject']);
        $this->assertSame('<html>Layout Hello John - Welcome to Callisto</html>', $result['html']);
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

    public function testSendSendsEmailWithRenderedContent(): void
    {
        $template = new MailTemplate();
        $template->setCode('send_code');
        $template->setSubject('Mail Subject');
        $template->setLayout('bootstrap');
        $template->setContent('Mail Body');

        $this->repositoryMock
            ->method('findOneBy')
            ->willReturn($template);

        $this->mailerMock
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getTo()[0]->getAddress() === 'recipient@example.com'
                    && $email->getSubject() === 'Mail Subject'
                    && $email->getHtmlBody() === '<html>Layout Mail Subject - Mail Body</html>'
                    && $email->getFrom()[0]->getAddress() === 'sender@example.com'
                    && $email->getHeaders()->get('X-Custom-Header') !== null;
            }));

        $this->service->send(
            code: 'send_code',
            recipient: 'recipient@example.com',
            context: [],
            sender: 'sender@example.com',
            extraHeaders: ['X-Custom-Header' => 'Value']
        );
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

        $this->repositoryMock
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$template]);

        $result = $this->service->listTemplatesAsArray();
        $this->assertCount(1, $result);
        $this->assertSame('tpl_1', $result[0]['code']);
        $this->assertSame('Subject 1', $result[0]['subject']);
        $this->assertSame('tailwind', $result[0]['layout']);
        $this->assertSame('Content 1', $result[0]['content']);
    }

    public function testSaveTemplateCreatesNew(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'new_tpl'])
            ->willReturn(null);

        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (MailTemplate $template) {
                return $template->getCode() === 'new_tpl'
                    && $template->getSubject() === 'Subject'
                    && $template->getContent() === 'Content'
                    && $template->getLayout() === 'bootstrap';
            }), true);

        $result = $this->service->saveTemplate('new_tpl', 'Subject', 'Content', 'bootstrap');
        $this->assertSame('new_tpl', $result->getCode());
    }

    public function testSaveTemplateUpdatesExisting(): void
    {
        $template = new MailTemplate();
        $template->setCode('existing_tpl');

        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'existing_tpl'])
            ->willReturn($template);

        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($template, true);

        $result = $this->service->saveTemplate('existing_tpl', 'Updated Subject', 'Updated Content', 'tailwind');
        $this->assertSame('existing_tpl', $result->getCode());
        $this->assertSame('Updated Subject', $result->getSubject());
        $this->assertSame('Updated Content', $result->getContent());
        $this->assertSame('tailwind', $result->getLayout());
    }

    public function testUpdateTemplate(): void
    {
        $template = new MailTemplate();
        $template->setCode('tpl_to_update');
        $template->setSubject('Old Subject');
        $template->setLayout('bootstrap');
        $template->setContent('Old Content');

        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'tpl_to_update'])
            ->willReturn($template);

        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($template, true);

        $result = $this->service->updateTemplate('tpl_to_update', [
            'subject' => 'New Subject',
            'content' => 'New Content'
        ]);

        $this->assertSame('New Subject', $result->getSubject());
        $this->assertSame('New Content', $result->getContent());
        $this->assertSame('bootstrap', $result->getLayout()); // Unchanged
    }

    public function testUpdateTemplateThrowsExceptionOnNotFound(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'not_found'])
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mail template with code "not_found" not found.');

        $this->service->updateTemplate('not_found', ['subject' => 'Subject']);
    }

    public function testDeleteTemplateReturnsTrue(): void
    {
        $template = new MailTemplate();
        $template->setCode('tpl_to_delete');

        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'tpl_to_delete'])
            ->willReturn($template);

        $this->repositoryMock
            ->expects($this->once())
            ->method('remove')
            ->with($template, true);

        $result = $this->service->deleteTemplate('tpl_to_delete');
        $this->assertTrue($result);
    }

    public function testDeleteTemplateReturnsFalse(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'not_found'])
            ->willReturn(null);

        $result = $this->service->deleteTemplate('not_found');
        $this->assertFalse($result);
    }
}

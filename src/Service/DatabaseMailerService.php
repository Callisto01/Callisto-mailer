<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Service;

use Callisto\CallistoMailer\Repository\MailTemplateRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class DatabaseMailerService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly MailTemplateRepository $templateRepository
    ) {}

    /**
     * Renders a mail template (subject and content) using the database template and Twig context,
     * then wraps it into the configured layout (Bootstrap or Tailwind).
     *
     * @param string $code The unique code of the mail template
     * @param array<string, mixed> $context The dynamic variables to compile
     *
     * @return array{subject: string, html: string} The compiled subject and the fully layouted HTML body
     *
     * @throws \InvalidArgumentException if the template does not exist
     */
    public function render(string $code, array $context = []): array
    {
        $mailTemplate = $this->templateRepository->findOneBy(['code' => $code]);

        if (!$mailTemplate) {
            throw new \InvalidArgumentException(sprintf('Mail template with code "%s" not found in the database.', $code));
        }

        // Compile and render the subject using Twig
        $subjectTemplate = $this->twig->createTemplate($mailTemplate->getSubject());
        $compiledSubject = $subjectTemplate->render($context);

        // Compile and render the body content using Twig
        $contentTemplate = $this->twig->createTemplate($mailTemplate->getContent());
        $compiledContent = $contentTemplate->render($context);

        // Determine layout file template name
        $layoutName = strtolower($mailTemplate->getLayout());
        $layoutFile = sprintf('@CallistoMailer/layouts/base_%s.html.twig', $layoutName);

        // Render the wrapper layout passing the compiled content, subject, and original context
        $htmlBody = $this->twig->render($layoutFile, array_merge($context, [
            'content' => $compiledContent,
            'subject' => $compiledSubject,
        ]));

        return [
            'subject' => $compiledSubject,
            'html' => $htmlBody,
        ];
    }

    /**
     * Renders and sends an email based on a database-stored template.
     *
     * @param string $code The unique code of the mail template
     * @param string $recipient The email address of the recipient
     * @param array<string, mixed> $context The dynamic variables to compile
     * @param string|null $sender The sender email address (if null, Symfony Mailer's default from is used)
     * @param array<string, string> $extraHeaders Additional text headers to add to the email
     * @param callable(Email): void|null $callback Optional callback to modify the Email object before sending (e.g. to add attachments or CC)
     *
     * @throws \InvalidArgumentException if the template does not exist
     */
    public function send(
        string $code,
        string $recipient,
        array $context = [],
        ?string $sender = null,
        array $extraHeaders = [],
        ?callable $callback = null
    ): void {
        $rendered = $this->render($code, $context);

        $email = (new Email())
            ->to($recipient)
            ->subject($rendered['subject'])
            ->html($rendered['html']);

        if ($sender) {
            $email->from($sender);
        }

        foreach ($extraHeaders as $name => $value) {
            $email->getHeaders()->addTextHeader($name, $value);
        }

        if ($callback !== null) {
            $callback($email);
        }

        $this->mailer->send($email);
    }
}

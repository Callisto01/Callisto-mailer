<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Service;

use Callisto\CallistoMailer\Entity\MailTemplate;
use Callisto\CallistoMailer\Repository\MailTemplateRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class DatabaseMailerService
{
    /**
     * @param array<string, string> $customLayouts Map of layout name => Twig template path
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly MailTemplateRepository $templateRepository,
        private readonly array $customLayouts = []
    ) {}

    /**
     * Renders a mail template (subject and content) using the database template and Twig context,
     * then wraps it into the configured layout (Bootstrap, Tailwind, or Custom).
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

        // Resolve layout template path
        $layoutName = strtolower($mailTemplate->getLayout());
        $availableLayouts = array_merge([
            'bootstrap' => '@CallistoMailer/layouts/base_bootstrap.html.twig',
            'tailwind' => '@CallistoMailer/layouts/base_tailwind.html.twig',
        ], array_change_key_case($this->customLayouts, CASE_LOWER));

        if (isset($availableLayouts[$layoutName])) {
            $layoutFile = $availableLayouts[$layoutName];
        } elseif (str_starts_with($layoutName, '@') || str_contains($layoutName, '/') || str_contains($layoutName, '.twig')) {
            $layoutFile = $mailTemplate->getLayout(); // Treat as direct Twig template path
        } else {
            throw new \InvalidArgumentException(sprintf(
                'Layout "%s" is not registered. Registered layouts: %s. Alternatively, provide a direct Twig template path.',
                $layoutName,
                implode(', ', array_keys($availableLayouts))
            ));
        }

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

    /**
     * Lists all mail templates from the database.
     *
     * @return MailTemplate[]
     */
    public function listTemplates(): array
    {
        return $this->templateRepository->findAll();
    }

    /**
     * Lists all mail templates as simple associative arrays.
     *
     * @return array<int, array{code: string, subject: string, layout: string, content: string}>
     */
    public function listTemplatesAsArray(): array
    {
        $templates = $this->listTemplates();
        $result = [];

        foreach ($templates as $template) {
            $result[] = [
                'code' => $template->getCode(),
                'subject' => $template->getSubject(),
                'layout' => $template->getLayout(),
                'content' => $template->getContent(),
            ];
        }

        return $result;
    }

    /**
     * Creates or updates a mail template in the database.
     *
     * @param string $code The unique template code
     * @param string $subject The subject of the email (supports Twig)
     * @param string $content The HTML body content of the email (supports Twig)
     * @param string $layout The base layout to wrap the email (e.g. tailwind, bootstrap or custom)
     */
    public function saveTemplate(string $code, string $subject, string $content, string $layout = 'tailwind'): MailTemplate
    {
        $template = $this->templateRepository->findOneBy(['code' => $code]);

        if (!$template) {
            $template = new MailTemplate();
            $template->setCode($code);
        }

        $template->setSubject($subject);
        $template->setContent($content);
        $template->setLayout($layout);

        $this->templateRepository->save($template, true);

        return $template;
    }

    /**
     * Updates an existing mail template.
     *
     * @param string $code The unique template code
     * @param array{subject?: string, layout?: string, content?: string} $data Fields to update
     *
     * @throws \InvalidArgumentException if the template does not exist
     */
    public function updateTemplate(string $code, array $data): MailTemplate
    {
        $template = $this->templateRepository->findOneBy(['code' => $code]);

        if (!$template) {
            throw new \InvalidArgumentException(sprintf('Mail template with code "%s" not found.', $code));
        }

        if (isset($data['subject'])) {
            $template->setSubject($data['subject']);
        }
        if (isset($data['layout'])) {
            $template->setLayout($data['layout']);
        }
        if (isset($data['content'])) {
            $template->setContent($data['content']);
        }

        $this->templateRepository->save($template, true);

        return $template;
    }

    /**
     * Deletes a mail template by its unique code.
     *
     * @param string $code The unique template code
     *
     * @return bool True if the template was deleted, false if it did not exist
     */
    public function deleteTemplate(string $code): bool
    {
        $template = $this->templateRepository->findOneBy(['code' => $code]);

        if (!$template) {
            return false;
        }

        $this->templateRepository->remove($template, true);

        return true;
    }
}

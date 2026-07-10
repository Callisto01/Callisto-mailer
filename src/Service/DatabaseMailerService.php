<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Service;

use Callisto\CallistoMailer\Entity\MailTemplate;
use Callisto\CallistoMailer\Event\AfterTemplateMailSendEvent;
use Callisto\CallistoMailer\Event\BeforeTemplateMailSendEvent;
use Callisto\CallistoMailer\Exception\MissingTemplateVariablesException;
use Callisto\CallistoMailer\Repository\MailTemplateRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly array $customLayouts = [],
        private readonly string $defaultLocale = 'fr'
    ) {}

    /**
     * Renders a mail template (subject and content) using the database template and Twig context,
     * then wraps it into the configured layout (Bootstrap, Tailwind, or Custom).
     *
     * @param string $code The unique code of the mail template
     * @param array<string, mixed> $context The dynamic variables to compile
     * @param string|null $locale The locale of the template (defaults to bundle default locale)
     *
     * @return array{subject: string, html: string} The compiled subject and the fully layouted HTML body
     *
     * @throws \InvalidArgumentException if the template does not exist
     * @throws MissingTemplateVariablesException if expected variables are missing from context
     */
    public function render(string $code, array $context = [], ?string $locale = null): array
    {
        $targetLocale = $locale ?? $this->defaultLocale;
        $mailTemplate = $this->templateRepository->findOneBy([
            'code' => $code,
            'locale' => $targetLocale,
        ]);

        // Fallback to default locale if not found
        if (!$mailTemplate && $targetLocale !== $this->defaultLocale) {
            $mailTemplate = $this->templateRepository->findOneBy([
                'code' => $code,
                'locale' => $this->defaultLocale,
            ]);
        }

        if (!$mailTemplate) {
            throw new \InvalidArgumentException(sprintf(
                'Mail template with code "%s" and locale "%s" not found in the database.',
                $code,
                $targetLocale
            ));
        }

        // Validate the expected variables contract
        $expected = $mailTemplate->getExpectedVariables();
        $missing = [];
        foreach ($expected as $var) {
            if (!array_key_exists($var, $context)) {
                // Support dot notation (e.g., "user.firstName")
                if (str_contains($var, '.')) {
                    $parts = explode('.', $var);
                    $temp = $context;
                    $hasNested = true;
                    foreach ($parts as $part) {
                        if (is_array($temp) && array_key_exists($part, $temp)) {
                            $temp = $temp[$part];
                        } else {
                            $hasNested = false;
                            break;
                        }
                    }
                    if (!$hasNested) {
                        $missing[] = $var;
                    }
                } else {
                    $missing[] = $var;
                }
            }
        }

        if (!empty($missing)) {
            throw new MissingTemplateVariablesException($code, $mailTemplate->getLocale(), $missing);
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
     * @param string|null $locale The locale of the template (defaults to bundle default locale)
     * @param array<int, string|\Symfony\Component\Mime\Part\DataPart> $attachments List of file paths or DataPart instances to attach
     * @param callable(Email): void|null $callback Optional callback to modify the Email object before sending (e.g. to add attachments or CC)
     *
     * @throws \InvalidArgumentException if the template does not exist
     * @throws MissingTemplateVariablesException if expected variables are missing from context
     */
    public function send(
        string $code,
        string $recipient,
        array $context = [],
        ?string $sender = null,
        array $extraHeaders = [],
        ?string $locale = null,
        array $attachments = [],
        ?callable $callback = null
    ): void {
        $targetLocale = $locale ?? $this->defaultLocale;
        $rendered = $this->render($code, $context, $targetLocale);

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

        // Attachments handling
        foreach ($attachments as $attachment) {
            if ($attachment instanceof \Symfony\Component\Mime\Part\DataPart) {
                $email->addPart($attachment);
            } elseif (is_string($attachment)) {
                $email->attachFromPath($attachment);
            } else {
                throw new \InvalidArgumentException('Attachments must be a file path string or a Symfony DataPart instance.');
            }
        }

        if ($callback !== null) {
            $callback($email);
        }

        // Dispatch before send event
        $beforeEvent = new BeforeTemplateMailSendEvent($email, $code, $targetLocale, $context);
        $this->eventDispatcher->dispatch($beforeEvent);
        $email = $beforeEvent->getEmail();

        $this->mailer->send($email);

        // Dispatch after send event
        $afterEvent = new AfterTemplateMailSendEvent($email, $code, $targetLocale, $context);
        $this->eventDispatcher->dispatch($afterEvent);
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
     * @return array<int, array{code: string, locale: string, subject: string, layout: string, content: string, expectedVariables: string[]}>
     */
    public function listTemplatesAsArray(): array
    {
        $templates = $this->listTemplates();
        $result = [];

        foreach ($templates as $template) {
            $result[] = [
                'code' => $template->getCode(),
                'locale' => $template->getLocale(),
                'subject' => $template->getSubject(),
                'layout' => $template->getLayout(),
                'content' => $template->getContent(),
                'expectedVariables' => $template->getExpectedVariables(),
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
     * @param string $locale The locale language code of the template
     * @param string[] $expectedVariables Array of Twig variable names expected in context
     */
    public function saveTemplate(
        string $code,
        string $subject,
        string $content,
        string $layout = 'tailwind',
        string $locale = 'fr',
        array $expectedVariables = []
    ): MailTemplate {
        $template = $this->templateRepository->findOneBy([
            'code' => $code,
            'locale' => $locale,
        ]);

        if (!$template) {
            $template = new MailTemplate();
            $template->setCode($code);
            $template->setLocale($locale);
        }

        $template->setSubject($subject);
        $template->setContent($content);
        $template->setLayout($layout);
        $template->setExpectedVariables($expectedVariables);

        $this->templateRepository->save($template, true);

        return $template;
    }

    /**
     * Updates an existing mail template.
     *
     * @param string $code The unique template code
     * @param array{subject?: string, layout?: string, content?: string, locale?: string, expectedVariables?: string[]} $data Fields to update
     * @param string $locale The locale language code of the template
     *
     * @throws \InvalidArgumentException if the template does not exist
     */
    public function updateTemplate(string $code, array $data, string $locale = 'fr'): MailTemplate
    {
        $template = $this->templateRepository->findOneBy([
            'code' => $code,
            'locale' => $locale,
        ]);

        if (!$template) {
            throw new \InvalidArgumentException(sprintf('Mail template with code "%s" and locale "%s" not found.', $code, $locale));
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
        if (isset($data['locale'])) {
            $template->setLocale($data['locale']);
        }
        if (isset($data['expectedVariables'])) {
            $template->setExpectedVariables($data['expectedVariables']);
        }

        $this->templateRepository->save($template, true);

        return $template;
    }

    /**
     * Deletes a mail template by its unique code and locale.
     *
     * @param string $code The unique template code
     * @param string $locale The locale language code of the template
     *
     * @return bool True if the template was deleted, false if it did not exist
     */
    public function deleteTemplate(string $code, string $locale = 'fr'): bool
    {
        $template = $this->templateRepository->findOneBy([
            'code' => $code,
            'locale' => $locale,
        ]);

        if (!$template) {
            return false;
        }

        $this->templateRepository->remove($template, true);

        return true;
    }
}

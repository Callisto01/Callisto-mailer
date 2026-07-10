<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Command;

use Callisto\CallistoMailer\Repository\MailTemplateRepository;
use Callisto\CallistoMailer\Service\DatabaseMailerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'callisto:mailer:test-send',
    description: 'Sends a test email based on a database template, generating mock data for expected variables.'
)]
class TestSendCommand extends Command
{
    public function __construct(
        private readonly MailTemplateRepository $templateRepository,
        private readonly DatabaseMailerService $mailerService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('code', InputArgument::REQUIRED, 'The unique code of the mail template')
            ->addArgument('locale', InputArgument::REQUIRED, 'The language locale code of the template (e.g. fr, en)')
            ->addArgument('recipient', InputArgument::REQUIRED, 'The destination email address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Callisto Mailer: Test Send Command');

        $code = $input->getArgument('code');
        $locale = $input->getArgument('locale');
        $recipient = $input->getArgument('recipient');

        $template = $this->templateRepository->findOneBy([
            'code' => $code,
            'locale' => $locale,
        ]);

        if (!$template) {
            $io->error(sprintf('Mail template with code "%s" and locale "%s" not found in the database.', $code, $locale));
            return Command::FAILURE;
        }

        $io->text(sprintf('Found template <info>%s</info> (<comment>%s</comment>) with subject: "%s"', $code, $locale, $template->getSubject()));

        $expectedVariables = $template->getExpectedVariables();
        $mockContext = $this->buildMockContext($expectedVariables);

        if (!empty($expectedVariables)) {
            $io->text('Generating mock data for expected variables:');
            foreach ($expectedVariables as $var) {
                $io->text(sprintf(' - <comment>%s</comment>', $var));
            }
        } else {
            $io->text('No expected variables declared in this template.');
        }

        try {
            $this->mailerService->send(
                code: $code,
                recipient: $recipient,
                context: $mockContext,
                locale: $locale
            );
            $io->success(sprintf('Test email sent successfully to "%s" using template "%s" (%s)!', $recipient, $code, $locale));
        } catch (\Exception $e) {
            $io->error(sprintf('Error occurred while sending email: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function buildMockContext(array $expectedVariables): array
    {
        $context = [];
        foreach ($expectedVariables as $var) {
            $mockValue = $this->generateMockValue($var);
            
            // Support dot notation: e.g. "user.firstName"
            $parts = explode('.', $var);
            $temp = &$context;
            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    $temp[$part] = $mockValue;
                } else {
                    if (!isset($temp[$part]) || !is_array($temp[$part])) {
                        $temp[$part] = [];
                    }
                    $temp = &$temp[$part];
                }
            }
        }
        return $context;
    }

    private function generateMockValue(string $variableName): string
    {
        $varLower = strtolower($variableName);
        if (str_contains($varLower, 'email')) {
            return 'recipient@example.com';
        }
        if (str_contains($varLower, 'url') || str_contains($varLower, 'link') || str_contains($varLower, 'href')) {
            return 'https://callisto.example.com/test-dashboard';
        }
        if (str_contains($varLower, 'date')) {
            return date('d/m/Y');
        }
        if (str_contains($varLower, 'ref') || str_contains($varLower, 'id') || str_contains($varLower, 'code')) {
            return 'REF-TEST-99999';
        }
        if (str_contains($varLower, 'name') || str_contains($varLower, 'user') || str_contains($varLower, 'client')) {
            return 'John Doe';
        }
        
        return sprintf('[Mock %s]', $variableName);
    }
}

<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Command;

use Callisto\CallistoMailer\Entity\MailTemplate;
use Callisto\CallistoMailer\Repository\MailTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'callisto:mailer:import-templates',
    description: 'Imports or synchronizes Twig email templates from a directory into the database.'
)]
class ImportTemplatesCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly MailTemplateRepository $templateRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::OPTIONAL, 'The directory containing the Twig files', 'templates/emails')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate the import without persisting changes to the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Callisto Mailer: Template Synchronizer');

        $directoryArg = $input->getArgument('directory');
        
        // Resolve target directory path (absolute or relative to project root)
        $targetDir = str_starts_with($directoryArg, '/') || (strlen($directoryArg) > 1 && $directoryArg[1] === ':')
            ? $directoryArg
            : $this->projectDir . DIRECTORY_SEPARATOR . $directoryArg;

        $targetDir = rtrim($targetDir, DIRECTORY_SEPARATOR . '/');

        if (!is_dir($targetDir)) {
            $io->error(sprintf('The directory "%s" does not exist.', $targetDir));
            return Command::FAILURE;
        }

        $io->text(sprintf('Scanning directory: <info>%s</info>', $targetDir));

        // Find all Twig files recursively
        $twigFiles = [];
        try {
            $directoryIterator = new \RecursiveDirectoryIterator($targetDir);
            $iterator = new \RecursiveIteratorIterator($directoryIterator);
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'twig') {
                    $twigFiles[] = $file->getRealPath();
                }
            }
        } catch (\Exception $e) {
            $io->error(sprintf('Error reading files: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        if (empty($twigFiles)) {
            $io->warning('No Twig template files found.');
            return Command::SUCCESS;
        }

        $dryRun = $input->getOption('dry-run');
        if ($dryRun) {
            $io->note('Dry run mode enabled: no database writes will be executed.');
        }

        $imported = 0;
        $updated = 0;
        $tableData = [];

        foreach ($twigFiles as $filePath) {
            // Calculate template code based on relative path from scanning folder
            $relativePath = substr($filePath, strlen($targetDir));
            $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR . '/');

            $code = $relativePath;
            if (str_ends_with($code, '.html.twig')) {
                $code = substr($code, 0, -10);
            } elseif (str_ends_with($code, '.twig')) {
                $code = substr($code, 0, -5);
            }

            $code = str_replace([DIRECTORY_SEPARATOR, '/'], '_', $code);
            $code = strtolower($code);

            // Read file contents
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                $io->warning(sprintf('Could not read file: %s', $filePath));
                continue;
            }

            // Parse metadata and content from Twig blocks
            $subject = $this->parseBlock($fileContent, 'subject');
            $layout = $this->parseBlock($fileContent, 'layout') ?? 'tailwind';
            $content = $this->parseBlock($fileContent, 'content');

            // Fallbacks if blocks are not found
            if ($subject === null) {
                $subject = str_replace('_', ' ', ucfirst(pathinfo($filePath, PATHINFO_FILENAME)));
            }

            if ($content === null) {
                $content = $fileContent;
                // Strip raw blocks if they are present in file content
                $content = preg_replace('/{%\s*block\s+subject\s*%}.*?{%\s*endblock\s*%}/is', '', $content);
                $content = preg_replace('/{%\s*block\s+layout\s*%}.*?{%\s*endblock\s*%}/is', '', $content);
                $content = trim($content);
            }

            // Find existing MailTemplate in database or instantiate a new one
            $mailTemplate = $this->templateRepository->findOneBy(['code' => $code]);
            $isNew = false;

            if (!$mailTemplate) {
                $mailTemplate = new MailTemplate();
                $mailTemplate->setCode($code);
                $isNew = true;
            }

            $mailTemplate->setSubject($subject);
            $mailTemplate->setLayout($layout);
            $mailTemplate->setContent($content);

            if (!$dryRun) {
                $this->entityManager->persist($mailTemplate);
                if ($isNew) {
                    $imported++;
                } else {
                    $updated++;
                }
            }

            $tableData[] = [
                $code,
                $subject,
                $layout,
                $isNew ? '<fg=green>New (Created)</>' : '<fg=yellow>Existing (Updated)</>'
            ];
        }

        if (!$dryRun && ($imported > 0 || $updated > 0)) {
            $this->entityManager->flush();
        }

        $io->table(['Code', 'Subject', 'Layout', 'Status'], $tableData);

        if ($dryRun) {
            $io->success(sprintf('Dry run complete. Found %d templates to import/update.', count($tableData)));
        } else {
            $io->success(sprintf('Synchronization complete. %d templates created, %d templates updated.', $imported, $updated));
        }

        return Command::SUCCESS;
    }

    private function parseBlock(string $content, string $blockName): ?string
    {
        $pattern = '/{%\s*block\s+' . preg_quote($blockName, '/') . '\s*%}(.*?){%\s*endblock\s*%}/is';
        if (preg_match($pattern, $content, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
}

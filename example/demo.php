<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Callisto\CallistoMailer\Tests\Kernel;
use Callisto\CallistoMailer\Entity\MailTemplate;
use Callisto\CallistoMailer\Repository\MailTemplateRepository;
use Callisto\CallistoMailer\Service\DatabaseMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

echo "--- Bootstrapping Demo Kernel ---\n";
// Instantiate the Kernel in test environment to use the mock configurations
$kernel = new Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();

echo "--- Initializing SQLite database schema ---\n";
/** @var EntityManagerInterface $entityManager */
$entityManager = $container->get('doctrine.orm.default_entity_manager');
$schemaTool = new SchemaTool($entityManager);
$metadata = $entityManager->getMetadataFactory()->getAllMetadata();
// Drop schema if exists and recreate
$schemaTool->dropSchema($metadata);
$schemaTool->createSchema($metadata);

echo "--- Creating and saving MailTemplate 'welcome_user' ---\n";
/** @var MailTemplateRepository $repository */
$repository = $container->get(MailTemplateRepository::class);

$template = new MailTemplate();
$template->setCode('welcome_user')
    ->setSubject('Welcome to Callisto, {{ user.firstName }}!')
    ->setLayout('tailwind')
    ->setContent('
        <h1 style="color: #0f172a; font-size: 24px; font-weight: 800; margin-bottom: 16px;">Delighted to have you with us!</h1>
        <p>Hello {{ user.firstName }} {{ user.lastName }},</p>
        <p>Your account has been successfully created. You can now log in to our platform and explore all our features.</p>
        <div style="margin: 32px 0; text-align: center;">
            <a href="{{ loginUrl }}" class="btn-indigo">Access my space</a>
        </div>
        <p style="font-size: 14px; color: #64748b;">If the button above does not work, copy and paste this link: <a href="{{ loginUrl }}">{{ loginUrl }}</a></p>
    ');

$repository->save($template, true);
echo "Template saved successfully!\n\n";

echo "--- Rendering template with context ---\n";
/** @var DatabaseMailerService $mailerService */
$mailerService = $container->get(DatabaseMailerService::class);

$context = [
    'user' => [
        'firstName' => 'John',
        'lastName' => 'Doe',
    ],
    'loginUrl' => 'https://callisto.example.com/login',
];

$rendered = $mailerService->render('welcome_user', $context);

echo "Compiled Subject:\n";
echo "=> " . $rendered['subject'] . "\n\n";
echo "HTML Output Preview (first 700 chars):\n";
echo "======================================================================\n";
echo substr($rendered['html'], 0, 700) . "...\n";
echo "======================================================================\n\n";
echo "Demo execution finished successfully!\n";

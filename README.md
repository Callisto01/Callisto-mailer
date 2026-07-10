# Callisto Mailer Bundle (`callisto/callisto-mailer`)

The **Callisto Mailer** bundle is a modern Symfony 8 extension allowing you to store, compile, and send email templates managed in a database. It integrates premium responsive layouts (based on Tailwind CSS and Bootstrap design guidelines) with inlined styles for maximum compatibility with all email clients (Gmail, Outlook, Apple Mail, etc.).

---

## Features
- 🗄️ **Database Storage**: Complete management of email subjects and bodies as ORM entities.
- ⚡ **Dynamic Twig Compilation**: Automatic on-the-fly Twig variable parsing in both the subject and the content.
- 🎨 **Built-in Premium Layouts**: Two integrated and optimized high-fidelity graphic layouts (`Bootstrap` and `Tailwind`).
- ⚙️ **Native Symfony Integration**: Uses the native `Symfony\Component\Mailer` component and standard Symfony 8 dependency injection.

---

## Installation

### 1. Add private repository to project `composer.json`
Since this is a private package, you must declare its location (for example, a Git repository) in your main application's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:your-organization/callisto-mailer.git"
        }
    ],
    "require": {
        "callisto/callisto-mailer": "dev-main"
    }
}
```

Then run the installation:
```bash
composer require callisto/callisto-mailer
```

### 2. Register the Bundle
If you are using **Symfony Flex**, the bundle is automatically enabled. Otherwise, manually add it to `config/bundles.php`:

```php
// config/bundles.php

return [
    // ...
    Callisto\CallistoMailer\CallistoMailerBundle::class => ['all' => true],
];
```

---

## Database & Migration

The bundle exposes the `MailTemplate` entity mapped to the `callisto_mail_template` table. To generate and apply the migration:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

---

## Creating and Synchronizing Templates via Twig (Console Command)

The bundle provides a Symfony console command that automatically synchronizes/imports physical Twig files into the database. This is the easiest way to design templates while enjoying your IDE's comfort.

### 1. Expected Twig File Structure
Create Twig files (for example, in the `templates/emails/` folder) and define email metadata using standard Twig blocks:

```twig
{# templates/emails/welcome_user.html.twig #}
{% block subject %}Welcome to Callisto, {{ user.firstName }}!{% endblock %}
{% block layout %}tailwind{% endblock %}
{% block content %}
    <h1 style="color: #0f172a; font-size: 24px; font-weight: 800; margin-bottom: 16px;">Delighted to have you with us!</h1>
    <p>Hello {{ user.firstName }} {{ user.lastName }},</p>
    <p>Your account has been successfully created. You can now log in to our platform and explore all our features.</p>
    <div style="margin: 32px 0; text-align: center;">
        <a href="{{ loginUrl }}" class="btn-indigo">Access my space</a>
    </div>
{% endblock %}
```

> [!TIP]
> If the `subject` or `layout` blocks are not specified in the file, default values will be applied (the subject will be deduced from the file name, and the layout will be `tailwind` by default). If no blocks are defined in the Twig file, the entire file is treated as the message body.

### 2. Run Import or Synchronization
To run the import from the default `templates/emails/` folder:

```bash
php bin/console callisto:mailer:import-templates
```

You can specify a different folder as an argument:

```bash
php bin/console callisto:mailer:import-templates templates/custom_folder
```

To simulate the import without modifying the database, use the `--dry-run` option:

```bash
php bin/console callisto:mailer:import-templates --dry-run
```

Each Twig file found generates or updates a template in the database. The unique model `code` is automatically deduced from the relative path of the file (for example, `templates/emails/users/welcome.html.twig` will produce the code `users_welcome`).

---

## Usage

### 1. Creating an Email Template (MailTemplate)
You can add a template to the database via a fixture, an admin controller, or directly via SQL.

Example of saving a template via Doctrine:
```php
use Callisto\CallistoMailer\Entity\MailTemplate;
use Doctrine\ORM\EntityManagerInterface;

// ... in a controller or command:
$template = new MailTemplate();
$template->setCode('welcome_user');
$template->setSubject('Welcome to Callisto, {{ user.firstName }}!');
$template->setLayout('tailwind'); // 'tailwind' or 'bootstrap'
$template->setContent('
    <h1 style="color: #0f172a; font-size: 24px; font-weight: 800; margin-bottom: 16px;">Delighted to have you with us!</h1>
    <p>Hello {{ user.firstName }} {{ user.lastName }},</p>
    <p>Your account has been successfully created. You can now log in to our platform and explore all our features.</p>
    <div style="margin: 32px 0; text-align: center;">
        <a href="{{ loginUrl }}" class="btn-indigo">Access my space</a>
    </div>
    <p style="font-size: 14px; color: #64748b;">If the button above does not work, copy and paste this link: <a href="{{ loginUrl }}">{{ loginUrl }}</a></p>
');

$entityManager->persist($template);
$entityManager->flush();
```

> [!NOTE]  
> Notice the usage of the `.btn-indigo` CSS class included in the Tailwind layout, or `.btn-primary` if you had chosen the Bootstrap layout.

---

### 2. Sending Email via the Service
Simply inject `DatabaseMailerService` into your Symfony services or controllers.

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Callisto\CallistoMailer\Service\DatabaseMailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(DatabaseMailerService $databaseMailerService): Response
    {
        // Dummy registration logic ...
        $user = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com'
        ];

        // Send email to the user
        $databaseMailerService->send(
            code: 'welcome_user',
            recipient: $user['email'],
            context: [
                'user' => $user,
                'loginUrl' => 'https://callisto.example.com/login'
            ]
        );

        return new Response('Registration successful and email sent!');
    }
}
```

---

### 3. Advanced Features

#### Adding attachments or modifying the `Email` object before sending
The `send` method accepts a callback argument allowing you to manipulate the Symfony `Email` object before it is passed to the SMTP transport:

```php
use Symfony\Component\Mime\Email;

$databaseMailerService->send(
    code: 'invoice_template',
    recipient: 'client@example.com',
    context: ['invoice' => $invoice],
    sender: 'billing@callisto.com',
    extraHeaders: ['X-Custom-Header' => 'CallistoApp'],
    callback: function (Email $email) {
        $email->attachFromPath('/path/to/invoice.pdf', 'Invoice.pdf', 'application/pdf');
    }
);
```

#### Render Only (without sending)
If you want to preview an email or manipulate the raw content:
```php
$rendered = $databaseMailerService->render('welcome_user', [
    'user' => ['firstName' => 'John', 'lastName' => 'Doe'],
    'loginUrl' => 'https://callisto.example.com/login'
]);

// $rendered contains:
// [
//     'subject' => 'Welcome to Callisto, John!',
//     'html'    => '<!DOCTYPE html><html>... [The fully compiled and layout-wrapped body] ...</html>'
// ]
```

---

## Custom Layouts Management

The bundle allows you to use custom base layouts other than `bootstrap` and `tailwind`. You can do this in two ways:

### Option A: Global Declaration in Configuration (Recommended)
You can register your custom layouts under aliases in the Symfony configuration of your main project (e.g. `config/packages/callisto_mailer.yaml`):

```yaml
# config/packages/callisto_mailer.yaml
callisto_mailer:
    layouts:
        custom_modern: 'emails/layouts/modern.html.twig'
        custom_dark: '@App/emails/layouts/dark.html.twig'
```

In your database (entity `MailTemplate`), you can then simply fill the configured alias in the `layout` field (e.g. `custom_modern` or `custom_dark`). The service will automatically resolve the corresponding file path.

### Option B: Direct Twig Path in Database
If you do not want to declare your layouts in the configuration, you can save the full Twig path directly in the `layout` field of your `MailTemplate` in the database. 
The service will automatically detect if it is a direct path if the value starts with `@` or contains `.twig` or `/`.

Valid values for the `layout` field in the database:
- `@App/emails/layouts/premium.html.twig`
- `emails/layouts/notification.html.twig`

---

## Overriding Default Layouts
If you use the built-in layouts (`bootstrap` or `tailwind`) but want to modify their base HTML/CSS code without changing your entity data, you can override them in your main application by creating files at the following locations:

- `templates/bundles/CallistoMailerBundle/layouts/base_tailwind.html.twig`
- `templates/bundles/CallistoMailerBundle/layouts/base_bootstrap.html.twig`

---

## Template Management and Administration

The `DatabaseMailerService` service also exposes utility methods to administer email templates (list, create/save, edit, and delete) directly from your PHP code (for example, in an admin controller or console command).

### 1. List Templates
You can retrieve the list of all email templates as object entities or as simple associative arrays:

```php
// Retrieves an array of MailTemplate[] entities
$templates = $databaseMailerService->listTemplates();

// Retrieves a simple associative array (e.g., for JSON APIs)
$templatesArray = $databaseMailerService->listTemplatesAsArray();
```

### 2. Create or Update a Template (Save)
The `saveTemplate` method creates the template if it does not exist or updates it if it already exists in the database, and applies the changes immediately (flush). It now supports the locale (i18n) and the expected variables contract:

```php
$databaseMailerService->saveTemplate(
    code: 'user_reset_password',
    subject: 'Reset your password',
    content: '<p>Hello {{ user.firstName }}, click here...</p>',
    layout: 'tailwind',
    locale: 'en',
    expectedVariables: ['user.firstName', 'resetUrl'] // Expected variables contract
);
```

### 3. Edit an Existing Template (Update)
If you want to modify specific fields of an existing template for a specific locale:

```php
$databaseMailerService->updateTemplate(
    code: 'welcome_user',
    data: [
        'subject' => 'New welcome subject!',
        'expectedVariables' => ['user.firstName', 'loginUrl']
    ],
    locale: 'en'
);
```

### 4. Delete a Template
You can delete an email template for a specific locale:

```php
$deleted = $databaseMailerService->deleteTemplate('old_obsolete_template', 'en');
```

---

## Advanced Features

### 1. Multi-language Support (i18n)
The bundle natively handles multi-language support via a `locale` field in the `MailTemplate` entity. A composite unique constraint on `[code, locale]` ensures you can have multiple language variations for the same template code (e.g. `welcome_user` in `fr`, `en`, `es`).

You can configure the fallback locale of your bundle in `config/packages/callisto_mailer.yaml`:
```yaml
# config/packages/callisto_mailer.yaml
callisto_mailer:
    default_locale: 'en'
```

When sending or rendering, the service uses the provided locale. If not found, it automatically falls back to the default locale of the bundle.
```php
$databaseMailerService->send(
    code: 'welcome_user',
    recipient: 'user@example.com',
    context: ['user' => $user],
    locale: 'en' // Finds template with locale 'en' (falls back to default locale if not found)
);
```

### 2. Variables Contract (expectedVariables)
To secure your emails and avoid Twig rendering errors, you can bind an "expected variables" contract to each template (e.g. `['user.firstName', 'order.ref']`).

If any of these variables (or nested keys using dotted notation) is missing from the provided context at sending time, the service will throw a custom exception: `Callisto\CallistoMailer\Exception\MissingTemplateVariablesException`.

### 3. Attachments Management
The `send` method accepts an `$attachments` argument containing an array of physical file paths (as strings) or instances of `Symfony\Component\Mime\Part\DataPart`:

```php
use Symfony\Component\Mime\Part\DataPart;

$databaseMailerService->send(
    code: 'invoice_template',
    recipient: 'client@example.com',
    context: ['order' => $order],
    attachments: [
        '/path/to/invoices/INV-123.pdf', // Path to physical file
        new DataPart('Raw content', 'notes.txt', 'text/plain') // DataPart instance
    ]
);
```

### 4. Custom Events (EventDispatcher)
Two specific events are dispatched by the bundle to allow you to hook into the sending logic.

#### `BeforeTemplateMailSendEvent`
Triggered right before sending. Allows you to analyze or modify the Symfony `Email` object or the Twig context:
```php
namespace App\EventSubscriber;

use Callisto\CallistoMailer\Event\BeforeTemplateMailSendEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MailMailerSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeTemplateMailSendEvent::class => 'onBeforeSend',
        ];
    }

    public function onBeforeSend(BeforeTemplateMailSendEvent $event): void
    {
        $email = $event->getEmail();
        // Add a global test CC or header
        $email->addCc('monitoring@example.com');
    }
}
```

#### `AfterTemplateMailSendEvent`
Triggered right after sending (useful to track history or log sent emails):
```php
use Callisto\CallistoMailer\Event\AfterTemplateMailSendEvent;

public function onAfterSend(AfterTemplateMailSendEvent $event): void
{
    // Log successful send
    $code = $event->getCode();
    $recipient = $event->getEmail()->getTo()[0]->getAddress();
    // Your logging logic...
}
```

### 5. "Send Test" Command
You can test the rendering and deliverability of your emails directly from the console using the command:

```bash
php bin/console callisto:mailer:test-send {code} {locale} {recipient}
```

Example:
```bash
php bin/console callisto:mailer:test-send welcome_user en admin@callisto.com
```
This command inspects the `expectedVariables` contract declared on the template, automatically generates appropriate mock data (including nested dot notation), and calls the mailer service.

### 6. EasyAdmin 4 Integration
The bundle includes a pre-configured CRUD controller ready to manage your email templates.

To enable it in your EasyAdmin panel, simply add the controller to your main `DashboardController`:

```php
namespace App\Controller\Admin;

use Callisto\CallistoMailer\Controller\Admin\MailTemplateCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

class DashboardController extends AbstractDashboardController
{
    // ...

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Email Templates', 'fas fa-envelope', MailTemplateCrudController::class);
    }
}
```
The interface offers a modern editing form with Twig code editors (WYSIWYG/CodeEditor) and intuitive management of expected variables.

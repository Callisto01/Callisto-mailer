# Callisto Mailer Bundle (`callisto/callisto-mailer`)

Le bundle **Callisto Mailer** est une extension moderne pour Symfony 8 permettant de stocker, compiler et envoyer des modèles d'e-mails gérés en base de données. Il intègre des layouts responsive premium (basés sur les chartes graphiques de Tailwind CSS et Bootstrap) avec styles inlinés pour une compatibilité maximale avec tous les clients de messagerie (Gmail, Outlook, Apple Mail, etc.).

---

## Fonctionnalités
- 🗄️ **Stockage en base de données** : Gestion complète des sujets et corps d'e-mails sous forme d'entités ORM.
- ⚡ **Compilation Twig Dynamique** : Interprétation automatique des variables Twig à la volée dans le sujet et le contenu.
- 🎨 **Layouts Premium intégrés** : Deux layouts intégrés et optimisés (`Bootstrap` et `Tailwind`) à haute fidélité graphique.
- ⚙️ **Intégration Symfony native** : Utilise le composant `Symfony\Component\Mailer` natif et l'injection de dépendances standard de Symfony 8.

---

## Installation

### 1. Ajout du dépôt privé dans le `composer.json` du projet
Étant un package privé, vous devez déclarer son emplacement (par exemple, un dépôt Git) dans le `composer.json` de votre application principale :

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:votre-organisation/callisto-mailer.git"
        }
    ],
    "require": {
        "callisto/callisto-mailer": "dev-main"
    }
}
```

Lancez ensuite l'installation :
```bash
composer require callisto/callisto-mailer
```

### 2. Déclaration du Bundle
Si vous utilisez **Symfony Flex**, le bundle est activé automatiquement. Sinon, ajoutez-le manuellement dans `config/bundles.php` :

```php
// config/bundles.php

return [
    // ...
    Callisto\CallistoMailer\CallistoMailerBundle::class => ['all' => true],
];
```

---

## Base de données & Migration

Le bundle expose l'entité `MailTemplate` mappée sur la table `callisto_mail_template`. Pour générer et appliquer la migration :

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

---

## Création et Synchronisation de Templates via Twig (Console Command)

Le bundle propose une commande console Symfony qui permet de synchroniser/importer automatiquement des fichiers Twig physiques vers la base de données. C'est le moyen le plus simple pour concevoir des templates tout en profitant du confort de l'IDE.

### 1. Structure du fichier Twig attendue
Créez des fichiers Twig (par exemple dans le dossier `templates/emails/`) et définissez les métadonnées de l'e-mail à l'aide de blocs Twig standard :

```twig
{# templates/emails/welcome_user.html.twig #}
{% block subject %}Bienvenue chez Callisto, {{ user.firstName }} !{% endblock %}
{% block layout %}tailwind{% endblock %}
{% block content %}
    <h1 style="color: #0f172a; font-size: 24px; font-weight: 800; margin-bottom: 16px;">Ravi de vous compter parmi nous !</h1>
    <p>Bonjour {{ user.firstName }} {{ user.lastName }},</p>
    <p>Votre compte a bien été créé. Vous pouvez désormais vous connecter à notre plateforme et explorer toutes nos fonctionnalités.</p>
    <div style="margin: 32px 0; text-align: center;">
        <a href="{{ loginUrl }}" class="btn-indigo">Accéder à mon espace</a>
    </div>
{% endblock %}
```

> [!TIP]
> Si les blocs `subject` ou `layout` ne sont pas spécifiés dans le fichier, des valeurs par défaut seront appliquées (le sujet sera déduit du nom du fichier et le layout sera `tailwind` par défaut). Si aucun bloc n'est défini dans le fichier Twig, le fichier entier est traité comme le corps du message.

### 2. Lancer l'importation ou la synchronisation
Pour exécuter l'importation depuis le dossier par défaut `templates/emails/` :

```bash
php bin/console callisto:mailer:import-templates
```

Vous pouvez spécifier un dossier différent en argument :

```bash
php bin/console callisto:mailer:import-templates templates/dossier_personnalise
```

Pour simuler l'importation sans modifier la base de données, utilisez l'option `--dry-run` :

```bash
php bin/console callisto:mailer:import-templates --dry-run
```

Chaque fichier Twig trouvé génère ou met à jour un template en base de données. Le `code` unique du modèle est automatiquement déduit du chemin relatif du fichier (par exemple, `templates/emails/users/welcome.html.twig` donnera le code `users_welcome`).

---

## Utilisation

### 1. Création d'un modèle d'e-mail (MailTemplate)
Vous pouvez ajouter un modèle en base de données via une fixture, un contrôleur d'administration, ou directement via SQL.

Exemple d'enregistrement d'un template via Doctrine :
```php
use Callisto\CallistoMailer\Entity\MailTemplate;
use Doctrine\ORM\EntityManagerInterface;

// ... dans un controlleur ou une commande :
$template = new MailTemplate();
$template->setCode('welcome_user');
$template->setSubject('Bienvenue chez Callisto, {{ user.firstName }} !');
$template->setLayout('tailwind'); // 'tailwind' ou 'bootstrap'
$template->setContent('
    <h1 style="color: #0f172a; font-size: 24px; font-weight: 800; margin-bottom: 16px;">Ravi de vous compter parmi nous !</h1>
    <p>Bonjour {{ user.firstName }} {{ user.lastName }},</p>
    <p>Votre compte a bien été créé. Vous pouvez désormais vous connecter à notre plateforme et explorer toutes nos fonctionnalités.</p>
    <div style="margin: 32px 0; text-align: center;">
        <a href="{{ loginUrl }}" class="btn-indigo">Accéder à mon espace</a>
    </div>
    <p style="font-size: 14px; color: #64748b;">Si le bouton ci-dessus ne fonctionne pas, copiez-collez ce lien : <a href="{{ loginUrl }}">{{ loginUrl }}</a></p>
');

$entityManager->persist($template);
$entityManager->flush();
```

> [!NOTE]  
> Remarquez l'usage de la classe CSS `.btn-indigo` incluse dans le layout Tailwind, ou `.btn-primary` si vous aviez choisi le layout Bootstrap.

---

### 2. Envoi de l'e-mail via le service
Injectez simplement `DatabaseMailerService` dans vos services ou contrôleurs Symfony.

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
        // Logique d'inscription fictive ...
        $user = [
            'firstName' => 'Jean',
            'lastName' => 'Dupont',
            'email' => 'jean.dupont@example.com'
        ];

        // Envoi de l'email à l'utilisateur
        $databaseMailerService->send(
            code: 'welcome_user',
            recipient: $user['email'],
            context: [
                'user' => $user,
                'loginUrl' => 'https://callisto.example.com/login'
            ]
        );

        return new Response('Inscription réussie et e-mail envoyé !');
    }
}
```

---

### 3. Fonctionnalités Avancées

#### Ajouter des pièces jointes ou modifier l'objet `Email` avant envoi
La méthode `send` accepte un argument de rappel (callback) permettant de manipuler l'objet `Email` de Symfony avant sa transmission au transporteur SMTP :

```php
use Symfony\Component\Mime\Email;

$databaseMailerService->send(
    code: 'invoice_template',
    recipient: 'client@example.com',
    context: ['invoice' => $invoice],
    sender: 'facturation@callisto.com',
    extraHeaders: ['X-Custom-Header' => 'CallistoApp'],
    callback: function (Email $email) {
        $email->attachFromPath('/path/to/invoice.pdf', 'Facture.pdf', 'application/pdf');
    }
);
```

#### Rendu seul (sans envoi)
Si vous souhaitez prévisualiser un mail ou manipuler le contenu brut :
```php
$rendered = $databaseMailerService->render('welcome_user', [
    'user' => ['firstName' => 'Jean', 'lastName' => 'Dupont'],
    'loginUrl' => 'https://callisto.example.com/login'
]);

// $rendered contient :
// [
//     'subject' => 'Bienvenue chez Callisto, Jean !',
//     'html'    => '<!DOCTYPE html><html>... [Le corps complet compilé et enveloppé] ...</html>'
// ]
```

---

## Gestion des Layouts de Base Personnalisés (Custom Layouts)

Le bundle permet d'utiliser des layouts de base personnalisés en dehors de `bootstrap` et `tailwind`. Vous avez deux façons de procéder :

### Option A : Déclaration Globale dans la Configuration (Recommandé)
Vous pouvez enregistrer vos layouts personnalisés sous des alias dans la configuration Symfony de votre projet principal (ex: `config/packages/callisto_mailer.yaml`) :

```yaml
# config/packages/callisto_mailer.yaml
callisto_mailer:
    layouts:
        custom_modern: 'emails/layouts/modern.html.twig'
        custom_dark: '@App/emails/layouts/dark.html.twig'
```

Dans votre base de données (entité `MailTemplate`), vous pouvez alors simplement renseigner l'alias configuré dans le champ `layout` (ex: `custom_modern` ou `custom_dark`). Le service résoudra automatiquement le chemin du fichier correspondant.

### Option B : Chemin Direct Twig en Base de Données
Si vous ne souhaitez pas déclarer vos layouts dans la configuration, vous pouvez enregistrer directement le chemin Twig complet dans le champ `layout` de votre `MailTemplate` en base de données. 
Le service détectera automatiquement s'il s'agit d'un chemin direct si la valeur commence par `@` ou contient `.twig` ou `/`.

Exemples de valeurs valides pour le champ `layout` en base de données :
- `@App/emails/layouts/premium.html.twig`
- `emails/layouts/notification.html.twig`

---

## Surcharge des Layouts par défaut
Si vous utilisez les layouts intégrés (`bootstrap` ou `tailwind`) mais que vous souhaitez modifier leur code HTML/CSS de base sans changer le code de vos entités, vous pouvez les surcharger dans votre application principale en créant les fichiers aux emplacements suivants :

- `templates/bundles/CallistoMailerBundle/layouts/base_tailwind.html.twig`
- `templates/bundles/CallistoMailerBundle/layouts/base_bootstrap.html.twig`

---

## Gestion et Administration des Templates

---

## Gestion et Administration des Templates

Le service `DatabaseMailerService` expose également des méthodes utilitaires permettant d'administrer les modèles de mails (lister, créer/sauvegarder, modifier et supprimer) directement depuis votre code PHP (par exemple, dans un contrôleur d'administration ou une commande console).

### 1. Lister les templates
Vous pouvez récupérer la liste de tous les modèles d'e-mails sous forme d'entités d'objets ou de simples tableaux associatifs :

```php
// Récupère un tableau d'entités MailTemplate[]
$templates = $databaseMailerService->listTemplates();

// Récupère un tableau associatif simple (ex: pour des API JSON)
$templatesArray = $databaseMailerService->listTemplatesAsArray();
```

### 2. Créer ou Mettre à Jour un template (Save)
La méthode `saveTemplate` crée le modèle s'il n'existe pas ou le met à jour s'il existe déjà en base de données, puis applique immédiatement les modifications (flush). Elle supporte désormais la locale (i18n) et le contrat de variables attendues :

```php
$databaseMailerService->saveTemplate(
    code: 'user_reset_password',
    subject: 'Réinitialisez votre mot de passe',
    content: '<p>Bonjour {{ user.firstName }}, cliquez ici...</p>',
    layout: 'tailwind',
    locale: 'fr',
    expectedVariables: ['user.firstName', 'resetUrl'] // Contrat de variables attendues
);
```

### 3. Modifier un template existant (Update)
Si vous souhaitez modifier des champs spécifiques d'un modèle existant pour une locale précise :

```php
$databaseMailerService->updateTemplate(
    code: 'welcome_user',
    data: [
        'subject' => 'Nouveau sujet de bienvenue !',
        'expectedVariables' => ['user.firstName', 'loginUrl']
    ],
    locale: 'fr'
);
```

### 4. Supprimer un template
Vous pouvez supprimer un modèle d'e-mail pour une locale spécifique :

```php
$deleted = $databaseMailerService->deleteTemplate('old_obsolete_template', 'fr');
```

---

## Fonctionnalités Avancées

### 1. Support Multi-langue (i18n)
Le bundle gère nativement le multi-langue via un champ `locale` dans l'entité `MailTemplate`. Une contrainte d'unicité composite sur `[code, locale]` garantit que vous pouvez avoir plusieurs déclinaisons linguistiques pour le même code de template (ex: `welcome_user` en `fr`, `en`, `es`).

Vous pouvez configurer la locale par défaut de votre bundle (fallback) dans `config/packages/callisto_mailer.yaml` :
```yaml
callisto_mailer:
    default_locale: 'fr'
```

Lors de l'envoi ou du rendu, le service récupère la locale passée. Si celle-ci n'est pas trouvée, il basculera automatiquement sur la locale par défaut du bundle.
```php
$databaseMailerService->send(
    code: 'welcome_user',
    recipient: 'user@example.com',
    context: ['user' => $user],
    locale: 'en' // Recherche du template avec locale 'en' (fallback sur la locale par défaut 'fr')
);
```

### 2. Contrat de Variables (expectedVariables)
Afin de sécuriser vos envois et d'éviter des erreurs de rendu Twig, vous pouvez lier un "contrat" de variables attendues à chaque template (ex: `['user.firstName', 'order.ref']`).

Si l'une de ces variables (ou clés imbriquées via notation pointée) est absente du contexte fourni à l'envoi, le service lèvera une exception personnalisée : `Callisto\CallistoMailer\Exception\MissingTemplateVariablesException`.

### 3. Gestion des Pièces Jointes
La méthode `send` accepte un argument `$attachments` contenant un tableau de chemins de fichiers physiques (sous forme de chaînes de caractères) ou d'instances de `Symfony\Component\Mime\Part\DataPart` :

```php
use Symfony\Component\Mime\Part\DataPart;

$databaseMailerService->send(
    code: 'invoice_template',
    recipient: 'client@example.com',
    context: ['order' => $order],
    attachments: [
        '/path/to/invoices/INV-123.pdf', // Chemin vers fichier physique
        new DataPart('Contenu brut', 'notes.txt', 'text/plain') // Instance de DataPart
    ]
);
```

### 4. Événements Personnalisés (EventDispatcher)
Deux événements spécifiques sont émis par le bundle pour vous permettre de hooker la logique d'envoi.

#### `BeforeTemplateMailSendEvent`
Déclenché juste avant l'envoi. Permet d'analyser ou de modifier l'objet `Email` Symfony ou le contexte Twig :
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
        // Ajouter un en-tête ou CC global de test
        $email->addCc('monitoring@example.com');
    }
}
```

#### `AfterTemplateMailSendEvent`
Déclenché juste après l'envoi (utile pour historiser ou logger les e-mails envoyés) :
```php
use Callisto\CallistoMailer\Event\AfterTemplateMailSendEvent;

public function onAfterSend(AfterTemplateMailSendEvent $event): void
{
    // Log de l'envoi réussi
    $code = $event->getCode();
    $recipient = $event->getEmail()->getTo()[0]->getAddress();
    // Votre logique de log...
}
```

### 5. Commande "Envoyer un test"
Vous pouvez tester visuellement le rendu et la délivrabilité de vos e-mails directement depuis la console grâce à la commande :

```bash
php bin/console callisto:mailer:test-send {code} {locale} {destinataire}
```

Exemple :
```bash
php bin/console callisto:mailer:test-send welcome_user fr admin@callisto.com
```
Cette commande inspecte le contrat `expectedVariables` déclaré sur le template, génère automatiquement des données factices adaptées (y compris pour la notation pointée imbriquée), et appelle le service d'envoi.

### 6. Intégration EasyAdmin 4
Le bundle inclut un contrôleur CRUD pré-configuré prêt à l'emploi pour gérer vos modèles d'e-mails.

Pour l'activer dans votre panneau EasyAdmin, ajoutez simplement le contrôleur dans votre `DashboardController` principal :

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
        yield MenuItem::linkToCrud('Modèles de mails', 'fas fa-envelope', MailTemplateCrudController::class);
    }
}
```
L'interface propose un formulaire d'édition moderne avec des éditeurs de code Twig (WYSIWYG/CodeEditor) et la gestion intuitive des variables attendues.


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

## Customisation et Surcharge des layouts
Vous pouvez facilement surcharger les layouts de base du bundle dans votre application principale en créant les fichiers correspondants dans le répertoire de templates global de votre application Symfony (sous le même namespace virtuel Twig `@CallistoMailer`) :

- `templates/bundles/CallistoMailerBundle/layouts/base_tailwind.html.twig`
- `templates/bundles/CallistoMailerBundle/layouts/base_bootstrap.html.twig`

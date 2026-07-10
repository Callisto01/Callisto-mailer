<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Controller\Admin;

use Callisto\CallistoMailer\Entity\MailTemplate;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;

class MailTemplateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MailTemplate::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        
        yield TextField::new('code', 'Code de référence')
            ->setHelp('Identifiant unique du modèle de mail (ex: user_welcome).');
        
        yield TextField::new('locale', 'Langue / Locale')
            ->setHelp('Code de langue (ex: fr, en).');
            
        yield TextField::new('subject', 'Sujet du mail')
            ->setHelp('Sujet du mail. Supporte la syntaxe Twig.');

        yield ChoiceField::new('layout', 'Gabarit de base (Layout)')
            ->setChoices([
                'Tailwind CSS (Moderne)' => 'tailwind',
                'Bootstrap (Classique)' => 'bootstrap',
            ])
            ->allowMultipleChoices(false)
            ->setHelp('Layout global enveloppant le message.');

        yield ArrayField::new('expectedVariables', 'Variables attendues')
            ->setHelp('Contrat JSON des variables obligatoires dans le contexte Twig (ex: user.firstName, order.ref).');

        yield CodeEditorField::new('content', 'Contenu HTML du message')
            ->setNumOfRows(15)
            ->setLanguage('twig')
            ->setHelp('Corps du mail en HTML. Supporte la syntaxe Twig.');
    }
}

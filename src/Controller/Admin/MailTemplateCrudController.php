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
        
        yield TextField::new('code', 'Reference Code')
            ->setHelp('Unique identifier of the mail template (e.g. user_welcome).');
        
        yield TextField::new('locale', 'Language / Locale')
            ->setHelp('Language locale code (e.g. en, fr).');
            
        yield TextField::new('subject', 'Email Subject')
            ->setHelp('Email subject. Supports Twig syntax.');

        yield ChoiceField::new('layout', 'Base Layout')
            ->setChoices([
                'Tailwind CSS (Modern)' => 'tailwind',
                'Bootstrap (Classic)' => 'bootstrap',
            ])
            ->allowMultipleChoices(false)
            ->setHelp('Global layout wrapping the email message.');

        yield ArrayField::new('expectedVariables', 'Expected Variables')
            ->setHelp('JSON contract of required variables in Twig context (e.g. user.firstName, order.ref).');

        yield CodeEditorField::new('content', 'HTML Content')
            ->setNumOfRows(15)
            ->setLanguage('twig')
            ->setHelp('Email body in HTML. Supports Twig syntax.');
    }
}

<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Entity;

use Callisto\CallistoMailer\Repository\MailTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MailTemplateRepository::class)]
#[ORM\Table(name: 'callisto_mail_template')]
#[ORM\UniqueConstraint(name: 'uniq_code_locale', columns: ['code', 'locale'])]
class MailTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $locale = 'fr';

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $subject;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $layout;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $expectedVariables = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function setLayout(string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getExpectedVariables(): array
    {
        return $this->expectedVariables;
    }

    /**
     * @param string[] $expectedVariables
     */
    public function setExpectedVariables(array $expectedVariables): self
    {
        $this->expectedVariables = $expectedVariables;
        return $this;
    }
}

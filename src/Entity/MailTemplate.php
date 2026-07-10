<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Entity;

use Callisto\CallistoMailer\Repository\MailTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MailTemplateRepository::class)]
#[ORM\Table(name: 'callisto_mail_template')]
class MailTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $subject;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $layout;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

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
}

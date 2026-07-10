<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Event;

use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\Event;

class AfterTemplateMailSendEvent extends Event
{
    public function __construct(
        private readonly Email $email,
        private readonly string $code,
        private readonly string $locale,
        private readonly array $context
    ) {}

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}

<?php

declare(strict_types=1);

namespace Callisto\CallistoMailer\Exception;

class MissingTemplateVariablesException extends \RuntimeException
{
    /**
     * @param string[] $missingVariables
     */
    public function __construct(string $code, string $locale, array $missingVariables)
    {
        parent::__construct(sprintf(
            'Missing expected variables for mail template "%s" (locale: "%s"): %s',
            $code,
            $locale,
            implode(', ', $missingVariables)
        ));
    }
}

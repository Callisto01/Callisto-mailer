#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Callisto\CallistoMailer\Tests\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

// We boot a custom Kernel instance where getProjectDir() is overriden to the root directory
// of the project, allowing CLI operations relative to the project root directory.
$kernel = new class('dev', true) extends Kernel {
    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }
};

$application = new Application($kernel);
$application->run();

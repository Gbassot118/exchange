<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Entity\Session;

interface SlugGeneratorInterface
{
    public function generateForDocument(string $title, Session $session): string;
}

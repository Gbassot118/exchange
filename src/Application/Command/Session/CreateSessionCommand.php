<?php

declare(strict_types=1);

namespace App\Application\Command\Session;

final readonly class CreateSessionCommand
{
    public function __construct(
        public string $title,
        public ?string $description,
        public string $creatorPseudo,
        public bool $isAgent = false,
    ) {}
}

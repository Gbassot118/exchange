<?php

declare(strict_types=1);

namespace App\Application\Command\Session;

final readonly class JoinSessionCommand
{
    public function __construct(
        public string $inviteCode,
        public string $pseudo,
        public ?string $color = null,
        public bool $isAgent = false,
    ) {}
}

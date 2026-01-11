<?php

declare(strict_types=1);

namespace App\Application\Query\Session;

final readonly class GetSessionStatusQuery
{
    public function __construct(
        public string $sessionId,
    ) {}
}

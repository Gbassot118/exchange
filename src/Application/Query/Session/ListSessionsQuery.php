<?php

declare(strict_types=1);

namespace App\Application\Query\Session;

final readonly class ListSessionsQuery
{
    public function __construct(
        public bool $activeOnly = true,
        public int $limit = 50,
    ) {}
}

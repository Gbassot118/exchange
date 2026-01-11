<?php

declare(strict_types=1);

namespace App\Application\Command\Estimation;

final readonly class CreateEstimationCommand
{
    public function __construct(
        public string $sessionId,
        public string $title,
        public ?string $description = null,
        public ?string $linkedDocumentId = null,
    ) {}
}

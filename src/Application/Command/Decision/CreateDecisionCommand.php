<?php

declare(strict_types=1);

namespace App\Application\Command\Decision;

final readonly class CreateDecisionCommand
{
    /**
     * @param array<array{label: string, description?: string|null}> $options
     */
    public function __construct(
        public string $sessionId,
        public string $title,
        public array $options,
        public ?string $description = null,
        public ?string $linkedDocumentId = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

final readonly class AcknowledgeAnnotationRequest
{
    public function __construct(
        public bool $acknowledged = true,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            acknowledged: $data['acknowledged'] ?? $data['taken_into_account'] ?? true,
        );
    }
}

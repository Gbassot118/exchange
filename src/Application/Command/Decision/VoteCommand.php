<?php

declare(strict_types=1);

namespace App\Application\Command\Decision;

final readonly class VoteCommand
{
    public function __construct(
        public string $decisionId,
        public string $participantId,
        public string $optionId,
        public ?string $comment = null,
    ) {}
}

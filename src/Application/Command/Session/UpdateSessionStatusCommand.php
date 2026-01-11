<?php

declare(strict_types=1);

namespace App\Application\Command\Session;

use App\Domain\Session\ValueObject\SessionStatus;

final readonly class UpdateSessionStatusCommand
{
    public function __construct(
        public string $sessionId,
        public SessionStatus $newStatus,
    ) {}
}

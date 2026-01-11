<?php

declare(strict_types=1);

namespace App\Application\Query\Session;

use App\Application\DTO\Response\SessionResponse;
use App\Application\DTO\Transformer\SessionTransformer;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\SessionId;

final readonly class GetSessionHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private SessionTransformer $sessionTransformer,
    ) {}

    public function __invoke(GetSessionQuery $query): SessionResponse
    {
        $sessionId = SessionId::fromString($query->sessionId);
        $session = $this->sessionRepository->findById($sessionId);

        if ($session === null) {
            throw SessionNotFoundException::withId($query->sessionId);
        }

        return $this->sessionTransformer->toResponse($session, $query->includeParticipants);
    }
}

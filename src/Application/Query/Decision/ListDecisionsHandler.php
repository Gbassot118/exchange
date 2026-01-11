<?php

declare(strict_types=1);

namespace App\Application\Query\Decision;

use App\Application\DTO\Response\DecisionResponse;
use App\Application\DTO\Transformer\DecisionTransformer;
use App\Domain\Decision\Repository\DecisionRepositoryInterface;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\SessionId;

final readonly class ListDecisionsHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private DecisionRepositoryInterface $decisionRepository,
        private DecisionTransformer $decisionTransformer,
    ) {}

    /**
     * @return array<DecisionResponse>
     */
    public function __invoke(ListDecisionsQuery $query): array
    {
        $sessionId = SessionId::fromString($query->sessionId);
        $session = $this->sessionRepository->findById($sessionId);

        if ($session === null) {
            throw SessionNotFoundException::withId($query->sessionId);
        }

        if ($query->pendingOnly) {
            $decisions = $this->decisionRepository->findPendingBySession($session);
        } elseif ($query->status !== null) {
            $decisions = $this->decisionRepository->findByStatus($session, $query->status);
        } else {
            $decisions = $this->decisionRepository->findBySession($session);
        }

        return $this->decisionTransformer->toResponseList($decisions);
    }
}

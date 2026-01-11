<?php

declare(strict_types=1);

namespace App\Application\Command\Decision;

use App\Application\DTO\Response\DecisionResponse;
use App\Application\DTO\Transformer\DecisionTransformer;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Decision\Exception\DecisionLockedException;
use App\Domain\Decision\Exception\DecisionNotFoundException;
use App\Domain\Decision\Repository\DecisionRepositoryInterface;
use App\Domain\Decision\ValueObject\DecisionId;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PostponeDecisionHandler
{
    public function __construct(
        private DecisionRepositoryInterface $decisionRepository,
        private DecisionTransformer $decisionTransformer,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(PostponeDecisionCommand $command): DecisionResponse
    {
        $decisionId = DecisionId::fromString($command->decisionId);
        $decision = $this->decisionRepository->findById($decisionId);

        if ($decision === null) {
            throw DecisionNotFoundException::withId($command->decisionId);
        }

        if ($decision->isLocked()) {
            throw DecisionLockedException::create($command->decisionId);
        }

        $decision->postpone();
        $this->decisionRepository->save($decision);

        $sessionId = $decision->getSession()->getId()->toString();

        $this->realtimeNotifier->notifyDecisionUpdated(
            sessionId: $sessionId,
            decision: $this->decisionTransformer->toResponse($decision),
        );

        return $this->decisionTransformer->toResponse($decision);
    }
}

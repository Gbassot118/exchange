<?php

declare(strict_types=1);

namespace App\Application\Command\Decision;

use App\Application\DTO\Response\DecisionResponse;
use App\Application\DTO\Transformer\DecisionTransformer;
use App\Application\Port\EventPublisherInterface;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Decision\Event\DecisionValidated;
use App\Domain\Decision\Exception\DecisionLockedException;
use App\Domain\Decision\Exception\DecisionNotFoundException;
use App\Domain\Decision\Exception\InvalidOptionException;
use App\Domain\Decision\Repository\DecisionRepositoryInterface;
use App\Domain\Decision\ValueObject\DecisionId;
use App\Domain\Decision\ValueObject\OptionId;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ValidateDecisionHandler
{
    public function __construct(
        private DecisionRepositoryInterface $decisionRepository,
        private DecisionTransformer $decisionTransformer,
        private EventPublisherInterface $eventPublisher,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(ValidateDecisionCommand $command): DecisionResponse
    {
        $decisionId = DecisionId::fromString($command->decisionId);
        $decision = $this->decisionRepository->findById($decisionId);

        if ($decision === null) {
            throw DecisionNotFoundException::withId($command->decisionId);
        }

        if ($decision->isLocked()) {
            throw DecisionLockedException::create($command->decisionId);
        }

        $optionId = OptionId::fromString($command->selectedOptionId);
        if (!$decision->hasOption($optionId->value())) {
            throw InvalidOptionException::withId($command->selectedOptionId, $command->decisionId);
        }

        $decision->validate($optionId->value());
        $this->decisionRepository->save($decision);

        $sessionId = $decision->getSession()->getId()->toString();

        $event = new DecisionValidated(
            decisionId: $command->decisionId,
            sessionId: $sessionId,
            selectedOptionId: $command->selectedOptionId,
        );

        $this->eventPublisher->publish($event);

        $this->realtimeNotifier->notifyDecisionValidated(
            sessionId: $sessionId,
            decision: $this->decisionTransformer->toResponse($decision),
        );

        return $this->decisionTransformer->toResponse($decision);
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Command\Decision;

use App\Domain\Decision\Exception\DecisionNotFoundException;
use App\Domain\Decision\Repository\DecisionRepositoryInterface;
use App\Domain\Decision\ValueObject\DecisionId;

final readonly class DeleteDecisionHandler
{
    public function __construct(
        private DecisionRepositoryInterface $decisionRepository,
    ) {}

    public function __invoke(DeleteDecisionCommand $command): void
    {
        $decisionId = DecisionId::fromString($command->decisionId);
        $decision = $this->decisionRepository->findById($decisionId);

        if ($decision === null) {
            throw DecisionNotFoundException::withId($command->decisionId);
        }

        $this->decisionRepository->remove($decision);
    }
}

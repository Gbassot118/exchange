<?php

declare(strict_types=1);

namespace App\Application\Query\Decision;

use App\Application\DTO\Response\DecisionResponse;
use App\Application\DTO\Transformer\DecisionTransformer;
use App\Domain\Decision\Exception\DecisionNotFoundException;
use App\Domain\Decision\Repository\DecisionRepositoryInterface;
use App\Domain\Decision\ValueObject\DecisionId;

final readonly class GetDecisionHandler
{
    public function __construct(
        private DecisionRepositoryInterface $decisionRepository,
        private DecisionTransformer $decisionTransformer,
    ) {}

    public function __invoke(GetDecisionQuery $query): DecisionResponse
    {
        $decisionId = DecisionId::fromString($query->decisionId);

        if ($query->includeVotes) {
            $decision = $this->decisionRepository->findByIdWithVotes($decisionId);
        } else {
            $decision = $this->decisionRepository->findById($decisionId);
        }

        if ($decision === null) {
            throw DecisionNotFoundException::withId($query->decisionId);
        }

        return $this->decisionTransformer->toResponse($decision);
    }
}

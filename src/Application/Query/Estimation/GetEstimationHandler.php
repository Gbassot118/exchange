<?php

declare(strict_types=1);

namespace App\Application\Query\Estimation;

use App\Application\DTO\Response\EstimationResponse;
use App\Application\DTO\Transformer\EstimationTransformer;
use App\Domain\Estimation\Exception\EstimationNotFoundException;
use App\Domain\Estimation\Repository\EstimationRepositoryInterface;
use App\Domain\Estimation\ValueObject\EstimationId;

final readonly class GetEstimationHandler
{
    public function __construct(
        private EstimationRepositoryInterface $estimationRepository,
        private EstimationTransformer $estimationTransformer,
    ) {}

    public function __invoke(GetEstimationQuery $query): EstimationResponse
    {
        $estimationId = EstimationId::fromString($query->estimationId);

        if ($query->includeVotes) {
            $estimation = $this->estimationRepository->findByIdWithVotes($estimationId);
        } else {
            $estimation = $this->estimationRepository->findById($estimationId);
        }

        if ($estimation === null) {
            throw EstimationNotFoundException::withId($query->estimationId);
        }

        return $this->estimationTransformer->toResponse($estimation, $query->includeVotes);
    }
}

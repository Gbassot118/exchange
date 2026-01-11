<?php

declare(strict_types=1);

namespace App\Application\DTO\Transformer;

use App\Application\DTO\Response\EstimationResponse;
use App\Entity\Estimation;

final class EstimationTransformer
{
    public function toResponse(Estimation $estimation, bool $includeVotes = true): EstimationResponse
    {
        return EstimationResponse::fromEntity($estimation, $includeVotes);
    }

    /**
     * @param iterable<Estimation> $estimations
     * @return array<EstimationResponse>
     */
    public function toResponseList(iterable $estimations, bool $includeVotes = true): array
    {
        $responses = [];
        foreach ($estimations as $estimation) {
            $responses[] = $this->toResponse($estimation, $includeVotes);
        }
        return $responses;
    }

    /**
     * @param iterable<Estimation> $estimations
     * @return array<EstimationResponse>
     */
    public function toOpenList(iterable $estimations): array
    {
        $responses = [];
        foreach ($estimations as $estimation) {
            if ($estimation->isOpen()) {
                $responses[] = $this->toResponse($estimation, false);
            }
        }
        return $responses;
    }

    /**
     * @param iterable<Estimation> $estimations
     * @return array<EstimationResponse>
     */
    public function toRevealedList(iterable $estimations): array
    {
        $responses = [];
        foreach ($estimations as $estimation) {
            if ($estimation->isRevealed()) {
                $responses[] = $this->toResponse($estimation, true);
            }
        }
        return $responses;
    }
}

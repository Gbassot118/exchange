<?php

declare(strict_types=1);

namespace App\Application\DTO\Transformer;

use App\Application\DTO\Response\DecisionResponse;
use App\Entity\Decision;

final class DecisionTransformer
{
    public function toResponse(Decision $decision): DecisionResponse
    {
        return DecisionResponse::fromEntity($decision);
    }

    /**
     * @param iterable<Decision> $decisions
     * @return array<DecisionResponse>
     */
    public function toResponseList(iterable $decisions): array
    {
        $responses = [];
        foreach ($decisions as $decision) {
            $responses[] = $this->toResponse($decision);
        }
        return $responses;
    }

    /**
     * Filter and transform only pending (open or in discussion) decisions.
     *
     * @param iterable<Decision> $decisions
     * @return array<DecisionResponse>
     */
    public function toPendingList(iterable $decisions): array
    {
        $pending = [];
        foreach ($decisions as $decision) {
            $status = $decision->getStatus();
            if ($status === 'ouvert' || $status === 'en_discussion') {
                $pending[] = $this->toResponse($decision);
            }
        }
        return $pending;
    }

    /**
     * Filter and transform only validated decisions.
     *
     * @param iterable<Decision> $decisions
     * @return array<DecisionResponse>
     */
    public function toValidatedList(iterable $decisions): array
    {
        $validated = [];
        foreach ($decisions as $decision) {
            if ($decision->getStatus() === 'valide') {
                $validated[] = $this->toResponse($decision);
            }
        }
        return $validated;
    }

    /**
     * Get vote statistics for a decision.
     *
     * @return array<string, mixed>
     */
    public function toVoteStatsResponse(Decision $decision): array
    {
        $response = $this->toResponse($decision);

        return [
            'decision_id' => $response->id,
            'title' => $response->title,
            'status' => $response->status,
            'vote_count' => $response->voteCount,
            'vote_stats' => $response->voteStats,
            'options' => $response->options,
            'selected_option_id' => $response->selectedOptionId,
            'is_locked' => $response->isLocked,
        ];
    }
}

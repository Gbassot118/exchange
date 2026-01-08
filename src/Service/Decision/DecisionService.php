<?php

namespace App\Service\Decision;

use App\Entity\Decision;
use App\Entity\Document;
use App\Entity\Participant;
use App\Entity\Session;
use App\Entity\Vote;
use App\Repository\DecisionRepository;
use App\Repository\VoteRepository;
use App\Service\Mercure\MercurePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DecisionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DecisionRepository $decisionRepository,
        private readonly VoteRepository $voteRepository,
        private readonly MercurePublisher $mercurePublisher,
    ) {}

    /**
     * @param array<array{label: string, description?: string}> $options
     */
    public function create(
        Session $session,
        string $title,
        array $options,
        ?string $description = null,
        ?Document $linkedDocument = null
    ): Decision {
        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle($title);
        $decision->setDescription($description);
        $decision->setLinkedDocument($linkedDocument);

        $formattedOptions = [];
        foreach ($options as $option) {
            $formattedOptions[] = [
                'id' => Uuid::v7()->toString(),
                'label' => $option['label'],
                'description' => $option['description'] ?? null,
            ];
        }
        $decision->setOptions($formattedOptions);

        $this->decisionRepository->save($decision, true);

        $this->mercurePublisher->publishDecisionCreated($decision);

        return $decision;
    }

    public function vote(Decision $decision, Participant $participant, string $optionId, ?string $comment = null): Vote
    {
        if ($decision->isLocked()) {
            throw new \LogicException('Cette décision est verrouillée et ne peut plus recevoir de votes.');
        }

        $existingVote = $this->voteRepository->findByDecisionAndParticipant($decision, $participant);

        if ($existingVote !== null) {
            $existingVote->setOptionId(Uuid::fromString($optionId));
            $existingVote->setComment($comment);
            $this->entityManager->flush();
            $vote = $existingVote;
        } else {
            $vote = new Vote();
            $vote->setDecision($decision);
            $vote->setParticipant($participant);
            $vote->setOptionId(Uuid::fromString($optionId));
            $vote->setComment($comment);

            $this->voteRepository->save($vote, true);
        }

        $this->mercurePublisher->publishVoteReceived(
            $decision->getSession()->getId()->toString(),
            $decision->getId()->toString(),
            $decision->getVoteStats()
        );

        return $vote;
    }

    public function removeVote(Decision $decision, Participant $participant): void
    {
        if ($decision->isLocked()) {
            throw new \LogicException('Cette décision est verrouillée.');
        }

        $this->voteRepository->removeByDecisionAndParticipant($decision, $participant);

        $this->mercurePublisher->publishVoteReceived(
            $decision->getSession()->getId()->toString(),
            $decision->getId()->toString(),
            $decision->getVoteStats()
        );
    }

    public function updateStatus(Decision $decision, string $status): Decision
    {
        if ($decision->isLocked() && $status !== Decision::STATUS_REPORTE) {
            throw new \LogicException('Cette décision est verrouillée.');
        }

        $decision->setStatus($status);
        $this->entityManager->flush();

        $this->mercurePublisher->publishDecisionStatusChanged(
            $decision->getSession()->getId()->toString(),
            $this->serialize($decision)
        );

        return $decision;
    }

    public function validate(Decision $decision, string $selectedOptionId): Decision
    {
        $decision->validate(Uuid::fromString($selectedOptionId));
        $this->entityManager->flush();

        $this->mercurePublisher->publishDecisionStatusChanged(
            $decision->getSession()->getId()->toString(),
            $this->serialize($decision)
        );

        return $decision;
    }

    public function postpone(Decision $decision): Decision
    {
        $decision->setStatus(Decision::STATUS_REPORTE);
        $decision->setIsLocked(false);
        $this->entityManager->flush();

        $this->mercurePublisher->publishDecisionStatusChanged(
            $decision->getSession()->getId()->toString(),
            $this->serialize($decision)
        );

        return $decision;
    }

    public function delete(Decision $decision): void
    {
        $sessionId = $decision->getSession()->getId()->toString();
        $decisionId = $decision->getId()->toString();
        $documentId = $decision->getLinkedDocument()?->getId()->toString();

        // Remove all votes first
        foreach ($decision->getVotes() as $vote) {
            $this->entityManager->remove($vote);
        }

        $this->entityManager->remove($decision);
        $this->entityManager->flush();

        $this->mercurePublisher->publishDecisionDeleted($sessionId, $decisionId, $documentId);
    }

    public function serialize(Decision $decision): array
    {
        return [
            'id' => $decision->getId()->toString(),
            'title' => $decision->getTitle(),
            'description' => $decision->getDescription(),
            'status' => $decision->getStatus(),
            'options' => $decision->getOptions(),
            'selected_option_id' => $decision->getSelectedOptionId()?->toString(),
            'is_locked' => $decision->isLocked(),
            'vote_stats' => $decision->getVoteStats(),
            'vote_count' => $decision->getVotes()->count(),
            'linked_document_id' => $decision->getLinkedDocument()?->getId()->toString(),
            'created_at' => $decision->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $decision->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}

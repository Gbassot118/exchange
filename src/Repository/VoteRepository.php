<?php

namespace App\Repository;

use App\Entity\Decision;
use App\Entity\Participant;
use App\Entity\Vote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Vote>
 */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    public function save(Vote $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Vote $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByDecisionAndParticipant(Decision $decision, Participant $participant): ?Vote
    {
        return $this->findOneBy([
            'decision' => $decision,
            'participant' => $participant,
        ]);
    }

    /**
     * @return array<string, int>
     */
    public function getVoteCountsByDecision(Decision $decision): array
    {
        $results = $this->createQueryBuilder('v')
            ->select('v.optionId as optionId, COUNT(v.id) as voteCount')
            ->where('v.decision = :decision')
            ->setParameter('decision', $decision)
            ->groupBy('v.optionId')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['optionId']->toString()] = (int) $result['voteCount'];
        }

        return $counts;
    }

    /**
     * @return Vote[]
     */
    public function findByDecision(Decision $decision): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.participant', 'p')
            ->addSelect('p')
            ->where('v.decision = :decision')
            ->setParameter('decision', $decision)
            ->orderBy('v.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function removeByDecisionAndParticipant(Decision $decision, Participant $participant): void
    {
        $this->createQueryBuilder('v')
            ->delete()
            ->where('v.decision = :decision')
            ->andWhere('v.participant = :participant')
            ->setParameter('decision', $decision)
            ->setParameter('participant', $participant)
            ->getQuery()
            ->execute();
    }
}

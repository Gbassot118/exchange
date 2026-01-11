<?php

namespace App\Repository;

use App\Domain\Estimation\Repository\EstimationVoteRepositoryInterface;
use App\Domain\Estimation\ValueObject\EstimationId;
use App\Entity\Estimation;
use App\Entity\EstimationVote;
use App\Entity\Participant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EstimationVote>
 */
class EstimationVoteRepository extends ServiceEntityRepository implements EstimationVoteRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EstimationVote::class);
    }

    public function save(EstimationVote $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(EstimationVote $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByEstimationAndParticipant(Estimation $estimation, Participant $participant): ?EstimationVote
    {
        return $this->findOneBy([
            'estimation' => $estimation,
            'participant' => $participant,
        ]);
    }

    /**
     * @return EstimationVote[]
     */
    public function findByEstimation(EstimationId $estimationId): array
    {
        return $this->createQueryBuilder('v')
            ->join('v.estimation', 'e')
            ->where('e.id = :estimationId')
            ->setParameter('estimationId', $estimationId->value(), 'uuid')
            ->getQuery()
            ->getResult();
    }

    public function removeByEstimationAndParticipant(Estimation $estimation, Participant $participant): void
    {
        $vote = $this->findByEstimationAndParticipant($estimation, $participant);
        if ($vote !== null) {
            $this->remove($vote);
        }
    }
}

<?php

namespace App\Repository;

use App\Domain\Decision\Repository\DecisionRepositoryInterface;
use App\Domain\Decision\ValueObject\DecisionId;
use App\Entity\Decision;
use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Decision>
 */
class DecisionRepository extends ServiceEntityRepository implements DecisionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Decision::class);
    }

    public function save(Decision $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Decision $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findById(DecisionId $id): ?Decision
    {
        return $this->find($id->value());
    }

    /**
     * @return Decision[]
     */
    public function findBySession(Session $session): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.votes', 'v')
            ->addSelect('v')
            ->where('d.session = :session')
            ->setParameter('session', $session)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Decision[]
     */
    public function findPendingBySession(Session $session): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.votes', 'v')
            ->addSelect('v')
            ->where('d.session = :session')
            ->andWhere('d.status NOT IN (:finalStatuses)')
            ->setParameter('session', $session)
            ->setParameter('finalStatuses', [Decision::STATUS_VALIDE, Decision::STATUS_REPORTE])
            ->orderBy('d.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countPendingBySession(Session $session): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.session = :session')
            ->andWhere('d.status NOT IN (:finalStatuses)')
            ->setParameter('session', $session)
            ->setParameter('finalStatuses', [Decision::STATUS_VALIDE, Decision::STATUS_REPORTE])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Decision[]
     */
    public function findByStatus(Session $session, string $status): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.votes', 'v')
            ->addSelect('v')
            ->where('d.session = :session')
            ->andWhere('d.status = :status')
            ->setParameter('session', $session)
            ->setParameter('status', $status)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByIdWithVotes(DecisionId|string $id): ?Decision
    {
        $idValue = $id instanceof DecisionId ? $id->value() : $id;

        return $this->createQueryBuilder('d')
            ->leftJoin('d.votes', 'v')
            ->addSelect('v')
            ->leftJoin('v.participant', 'p')
            ->addSelect('p')
            ->where('d.id = :id')
            ->setParameter('id', $idValue, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}

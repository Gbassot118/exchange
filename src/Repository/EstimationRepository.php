<?php

namespace App\Repository;

use App\Domain\Estimation\Repository\EstimationRepositoryInterface;
use App\Domain\Estimation\ValueObject\EstimationId;
use App\Entity\Document;
use App\Entity\Estimation;
use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Estimation>
 */
class EstimationRepository extends ServiceEntityRepository implements EstimationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Estimation::class);
    }

    public function save(Estimation $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Estimation $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findById(EstimationId $id): ?Estimation
    {
        return $this->find($id->value());
    }

    /**
     * @return Estimation[]
     */
    public function findBySession(Session $session): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.votes', 'v')
            ->addSelect('v')
            ->where('e.session = :session')
            ->setParameter('session', $session)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Estimation[]
     */
    public function findByDocument(Document $document): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.votes', 'v')
            ->addSelect('v')
            ->where('e.linkedDocument = :document')
            ->setParameter('document', $document)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Estimation[]
     */
    public function findOpenBySession(Session $session): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.votes', 'v')
            ->addSelect('v')
            ->where('e.session = :session')
            ->andWhere('e.status = :status')
            ->setParameter('session', $session)
            ->setParameter('status', Estimation::STATUS_OPEN)
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countOpenBySession(Session $session): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.session = :session')
            ->andWhere('e.status = :status')
            ->setParameter('session', $session)
            ->setParameter('status', Estimation::STATUS_OPEN)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Estimation[]
     */
    public function findByStatus(Session $session, string $status): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.votes', 'v')
            ->addSelect('v')
            ->where('e.session = :session')
            ->andWhere('e.status = :status')
            ->setParameter('session', $session)
            ->setParameter('status', $status)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByIdWithVotes(EstimationId|string $id): ?Estimation
    {
        $idValue = $id instanceof EstimationId ? $id->value() : $id;

        return $this->createQueryBuilder('e')
            ->leftJoin('e.votes', 'v')
            ->addSelect('v')
            ->leftJoin('v.participant', 'p')
            ->addSelect('p')
            ->where('e.id = :id')
            ->setParameter('id', $idValue, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}

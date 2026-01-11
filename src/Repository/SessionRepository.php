<?php

namespace App\Repository;

use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\SessionId;
use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository implements SessionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    public function save(Session $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Session $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findById(SessionId $id): ?Session
    {
        return $this->find($id->value());
    }

    public function findByInviteCode(string $inviteCode): ?Session
    {
        return $this->findOneBy(['inviteCode' => $inviteCode]);
    }

    public function findActive(): array
    {
        return $this->findActiveWithParticipants();
    }

    public function findAllSessions(int $limit = 50): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Session[]
     */
    public function findActiveWithParticipants(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.participants', 'p')
            ->addSelect('p')
            ->where('s.status != :archived')
            ->setParameter('archived', Session::STATUS_ARCHIVE)
            ->orderBy('s.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Session[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->setParameter('status', $status)
            ->orderBy('s.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

}

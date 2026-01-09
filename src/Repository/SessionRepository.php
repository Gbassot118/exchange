<?php

namespace App\Repository;

use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    public function save(Session $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Session $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByInviteCode(string $inviteCode): ?Session
    {
        return $this->findOneBy(['inviteCode' => $inviteCode]);
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

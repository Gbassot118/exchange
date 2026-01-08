<?php

namespace App\Repository;

use App\Entity\Participant;
use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Participant>
 */
class ParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participant::class);
    }

    public function save(Participant $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Participant $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySessionAndPseudo(Session $session, string $pseudo): ?Participant
    {
        return $this->findOneBy([
            'session' => $session,
            'pseudo' => $pseudo,
        ]);
    }

    /**
     * @return Participant[]
     */
    public function findOnlineInSession(string $sessionId, \DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.session', 's')
            ->where('s.id = :sessionId')
            ->andWhere('p.lastSeenAt >= :threshold')
            ->setParameter('sessionId', Uuid::fromString($sessionId), 'uuid')
            ->setParameter('threshold', $threshold)
            ->orderBy('p.pseudo', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Participant[]
     */
    public function findBySession(Session $session): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.session = :session')
            ->setParameter('session', $session)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Participant[]
     */
    public function findAgentsBySession(Session $session): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.session = :session')
            ->andWhere('p.isAgent = true')
            ->setParameter('session', $session)
            ->getQuery()
            ->getResult();
    }

    public function updateLastSeen(Participant $participant, ?Uuid $currentDocumentId = null): void
    {
        $participant->setLastSeenAt(new \DateTimeImmutable());
        if ($currentDocumentId !== null) {
            $participant->setCurrentDocumentId($currentDocumentId);
        }
        $this->save($participant, true);
    }
}

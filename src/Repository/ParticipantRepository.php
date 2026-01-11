<?php

namespace App\Repository;

use App\Domain\Session\Repository\ParticipantRepositoryInterface;
use App\Domain\Session\ValueObject\ParticipantId;
use App\Entity\Participant;
use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Participant>
 */
class ParticipantRepository extends ServiceEntityRepository implements ParticipantRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participant::class);
    }

    public function save(Participant $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Participant $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findById(ParticipantId $id): ?Participant
    {
        return $this->find($id->value());
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
    public function findOnlineInSession(Session $session, \DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.session = :session')
            ->andWhere('p.lastSeenAt >= :threshold')
            ->setParameter('session', $session)
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

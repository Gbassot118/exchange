<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function save(Document $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Document $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Document[]
     */
    public function findBySession(Session $session, ?Uuid $parentId = null, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.session = :session')
            ->setParameter('session', $session)
            ->orderBy('d.sortOrder', 'ASC');

        if ($parentId !== null) {
            $qb->andWhere('d.parent = :parentId')
               ->setParameter('parentId', $parentId, 'uuid');
        } else {
            $qb->andWhere('d.parent IS NULL');
        }

        if ($type !== null) {
            $qb->andWhere('d.type = :type')
               ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Document[]
     */
    public function findRootDocuments(Session $session): array
    {
        return $this->findBySession($session, null, null);
    }

    public function findOneBySessionAndSlug(Session $session, string $slug): ?Document
    {
        return $this->findOneBy([
            'session' => $session,
            'slug' => $slug,
        ]);
    }

    public function getMaxSortOrder(Session $session, ?Document $parent): int
    {
        $qb = $this->createQueryBuilder('d')
            ->select('MAX(d.sortOrder)')
            ->where('d.session = :session')
            ->setParameter('session', $session);

        if ($parent !== null) {
            $qb->andWhere('d.parent = :parent')
               ->setParameter('parent', $parent);
        } else {
            $qb->andWhere('d.parent IS NULL');
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result !== null ? (int) $result : -1;
    }

    /**
     * @return Document[]
     */
    public function findWithAnnotations(Session $session): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.annotations', 'a')
            ->addSelect('a')
            ->where('d.session = :session')
            ->setParameter('session', $session)
            ->orderBy('d.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Document[]
     */
    public function findByType(Session $session, string $type): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.session = :session')
            ->andWhere('d.type = :type')
            ->setParameter('session', $session)
            ->setParameter('type', $type)
            ->orderBy('d.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByIdWithVersions(Uuid $id): ?Document
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.versions', 'v')
            ->addSelect('v')
            ->where('d.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}

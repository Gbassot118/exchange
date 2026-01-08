<?php

namespace App\Repository;

use App\Entity\Annotation;
use App\Entity\Document;
use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Annotation>
 */
class AnnotationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Annotation::class);
    }

    public function save(Annotation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Annotation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param array{type?: string, status?: string, untreated_only?: bool, author_id?: string} $filters
     * @return Annotation[]
     */
    public function findByDocumentWithFilters(Document $document, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.author', 'author')
            ->addSelect('author')
            ->leftJoin('a.replies', 'r')
            ->addSelect('r')
            ->where('a.document = :document')
            ->andWhere('a.parentAnnotation IS NULL')
            ->setParameter('document', $document)
            ->orderBy('a.createdAt', 'DESC');

        $this->applyFilters($qb, $filters);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array{type?: string, status?: string, untreated_only?: bool} $filters
     * @return Annotation[]
     */
    public function findBySessionWithFilters(Session $session, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->join('a.document', 'd')
            ->leftJoin('a.author', 'author')
            ->addSelect('author')
            ->where('d.session = :session')
            ->andWhere('a.parentAnnotation IS NULL')
            ->setParameter('session', $session)
            ->orderBy('a.createdAt', 'DESC');

        $this->applyFilters($qb, $filters);

        return $qb->getQuery()->getResult();
    }

    private function applyFilters(\Doctrine\ORM\QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['type'])) {
            $qb->andWhere('a.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['untreated_only'])) {
            $qb->andWhere('a.takenIntoAccount = false')
               ->andWhere('a.status != :resolved')
               ->setParameter('resolved', Annotation::STATUS_RESOLVED);
        }

        if (!empty($filters['author_id'])) {
            $qb->andWhere('author.id = :authorId')
               ->setParameter('authorId', $filters['author_id'], 'uuid');
        }
    }

    public function countBySessionAndStatus(Session $session, string $status): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->join('a.document', 'd')
            ->where('d.session = :session')
            ->andWhere('a.status = :status')
            ->setParameter('session', $session)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUntreatedBySession(Session $session): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->join('a.document', 'd')
            ->where('d.session = :session')
            ->andWhere('a.takenIntoAccount = false')
            ->andWhere('a.status != :resolved')
            ->andWhere('a.parentAnnotation IS NULL')
            ->setParameter('session', $session)
            ->setParameter('resolved', Annotation::STATUS_RESOLVED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Annotation[]
     */
    public function findPriorityAnnotations(Session $session, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.document', 'd')
            ->leftJoin('a.author', 'author')
            ->addSelect('author')
            ->where('d.session = :session')
            ->andWhere('a.takenIntoAccount = false')
            ->andWhere('a.status != :resolved')
            ->andWhere('a.parentAnnotation IS NULL')
            ->andWhere('a.type IN (:priorityTypes)')
            ->setParameter('session', $session)
            ->setParameter('resolved', Annotation::STATUS_RESOLVED)
            ->setParameter('priorityTypes', [Annotation::TYPE_QUESTION, Annotation::TYPE_OBJECTION])
            ->orderBy('a.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

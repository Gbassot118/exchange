<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentVersion>
 */
class DocumentVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentVersion::class);
    }

    public function save(DocumentVersion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DocumentVersion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return DocumentVersion[]
     */
    public function findByDocument(Document $document): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.document = :document')
            ->setParameter('document', $document)
            ->orderBy('v.version', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findSpecificVersion(Document $document, int $version): ?DocumentVersion
    {
        return $this->findOneBy([
            'document' => $document,
            'version' => $version,
        ]);
    }

    public function findLatestVersion(Document $document): ?DocumentVersion
    {
        return $this->createQueryBuilder('v')
            ->where('v.document = :document')
            ->setParameter('document', $document)
            ->orderBy('v.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

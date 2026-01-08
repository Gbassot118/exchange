<?php

namespace App\Service\Document;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Entity\Participant;
use App\Entity\Session;
use App\Repository\DocumentRepository;
use App\Service\Mercure\MercurePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;

final class DocumentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentRepository $documentRepository,
        private readonly SluggerInterface $slugger,
        private readonly MercurePublisher $mercurePublisher,
    ) {}

    public function create(Session $session, array $data, ?Participant $author = null): Document
    {
        $document = new Document();
        $document->setSession($session);
        $document->setTitle($data['title']);
        $document->setSlug($this->generateSlug($data['title'], $session));
        $document->setContent($data['content'] ?? '');
        $document->setType($data['type'] ?? Document::TYPE_GENERAL);
        $document->setMetadata($data['metadata'] ?? null);

        if (!empty($data['parent_id'])) {
            $parent = $this->documentRepository->find(Uuid::fromString($data['parent_id']));
            if ($parent !== null) {
                $document->setParent($parent);
            }
        }

        $document->setSortOrder($data['sort_order'] ?? $this->getNextSortOrder($session, $document->getParent()));

        $version = $this->createVersion($document, $author, 'Version initiale');
        $document->addVersion($version);

        $this->entityManager->persist($document);
        $this->entityManager->persist($version);
        $this->entityManager->flush();

        $this->mercurePublisher->publishDocumentCreated(
            $session->getId()->toString(),
            $this->serialize($document)
        );

        return $document;
    }

    public function update(Document $document, array $data, ?Participant $author = null, ?string $changeDescription = null): Document
    {
        $hasContentChange = isset($data['content']) && $data['content'] !== $document->getContent();
        $hasMetadataChange = isset($data['metadata']) && $data['metadata'] !== $document->getMetadata();

        if (isset($data['title'])) {
            $document->setTitle($data['title']);
        }
        if (isset($data['content'])) {
            $document->setContent($data['content']);
        }
        if (isset($data['type'])) {
            $document->setType($data['type']);
        }
        if (isset($data['metadata'])) {
            $document->setMetadata($data['metadata']);
        }
        if (isset($data['sort_order'])) {
            $document->setSortOrder($data['sort_order']);
        }

        if ($hasContentChange || $hasMetadataChange) {
            $document->incrementVersion();

            $version = $this->createVersion(
                $document,
                $author,
                $changeDescription ?? ($author?->isAgent() ? 'Mise à jour par l\'agent IA' : null)
            );
            $document->addVersion($version);
            $this->entityManager->persist($version);
        }

        $this->entityManager->flush();

        $this->mercurePublisher->publishDocumentUpdated(
            $document->getSession()->getId()->toString(),
            $document->getId()->toString(),
            $this->serialize($document)
        );

        return $document;
    }

    public function delete(Document $document): void
    {
        $sessionId = $document->getSession()->getId()->toString();
        $documentId = $document->getId()->toString();

        foreach ($document->getChildren() as $child) {
            $this->delete($child);
        }

        $this->entityManager->remove($document);
        $this->entityManager->flush();

        $this->mercurePublisher->publishDocumentDeleted($sessionId, $documentId);
    }

    public function reorder(Document $document, int $newPosition): Document
    {
        $session = $document->getSession();
        $parent = $document->getParent();
        $siblings = $this->documentRepository->findBySession($session, $parent?->getId(), null);

        $currentPosition = $document->getSortOrder();

        if ($newPosition === $currentPosition) {
            return $document;
        }

        foreach ($siblings as $sibling) {
            $siblingPosition = $sibling->getSortOrder();

            if ($newPosition > $currentPosition) {
                if ($siblingPosition > $currentPosition && $siblingPosition <= $newPosition) {
                    $sibling->setSortOrder($siblingPosition - 1);
                }
            } else {
                if ($siblingPosition >= $newPosition && $siblingPosition < $currentPosition) {
                    $sibling->setSortOrder($siblingPosition + 1);
                }
            }
        }

        $document->setSortOrder($newPosition);
        $this->entityManager->flush();

        return $document;
    }

    public function serialize(Document $document, bool $includeContent = true): array
    {
        $data = [
            'id' => $document->getId()->toString(),
            'title' => $document->getTitle(),
            'slug' => $document->getSlug(),
            'type' => $document->getType(),
            'parent_id' => $document->getParent()?->getId()->toString(),
            'sort_order' => $document->getSortOrder(),
            'current_version' => $document->getCurrentVersion(),
            'created_at' => $document->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $document->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($includeContent) {
            $data['content'] = $document->getContent();
            $data['metadata'] = $document->getMetadata();
        }

        return $data;
    }

    private function createVersion(Document $document, ?Participant $author, ?string $changeDescription): DocumentVersion
    {
        $version = new DocumentVersion();
        $version->setDocument($document);
        $version->setVersion($document->getCurrentVersion());
        $version->setContent($document->getContent());
        $version->setMetadata($document->getMetadata());
        $version->setAuthor($author);
        $version->setChangeDescription($changeDescription);

        return $version;
    }

    private function generateSlug(string $title, Session $session): string
    {
        $baseSlug = $this->slugger->slug($title)->lower()->toString();
        $slug = $baseSlug;
        $counter = 1;

        while ($this->documentRepository->findOneBySessionAndSlug($session, $slug) !== null) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function getNextSortOrder(Session $session, ?Document $parent): int
    {
        return $this->documentRepository->getMaxSortOrder($session, $parent) + 1;
    }
}

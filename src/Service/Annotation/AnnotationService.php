<?php

namespace App\Service\Annotation;

use App\Entity\Annotation;
use App\Entity\Document;
use App\Entity\Participant;
use App\Repository\AnnotationRepository;
use App\Repository\ParticipantRepository;
use App\Service\Mercure\MercurePublisher;
use Doctrine\ORM\EntityManagerInterface;

final class AnnotationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AnnotationRepository $annotationRepository,
        private readonly ParticipantRepository $participantRepository,
        private readonly MercurePublisher $mercurePublisher,
    ) {}

    public function create(
        Document $document,
        Participant $author,
        string $content,
        string $type = Annotation::TYPE_COMMENT,
        ?array $anchor = null
    ): Annotation {
        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent($content);
        $annotation->setType($type);
        $annotation->setAnchor($anchor);

        $mentions = $this->extractMentions($content, $document->getSession());
        $annotation->setMentions($mentions);

        $this->annotationRepository->save($annotation, true);

        $this->mercurePublisher->publishAnnotationCreated(
            $document->getSession()->getId()->toString(),
            $document->getId()->toString(),
            $this->serialize($annotation)
        );

        return $annotation;
    }

    public function createReply(
        Annotation $parentAnnotation,
        string $content,
        Participant $author
    ): Annotation {
        $reply = new Annotation();
        $reply->setDocument($parentAnnotation->getDocument());
        $reply->setAuthor($author);
        $reply->setContent($content);
        $reply->setType(Annotation::TYPE_COMMENT);
        $reply->setParentAnnotation($parentAnnotation);

        $mentions = $this->extractMentions($content, $parentAnnotation->getDocument()->getSession());
        $reply->setMentions($mentions);

        $this->annotationRepository->save($reply, true);

        $document = $parentAnnotation->getDocument();
        $this->mercurePublisher->publishAnnotationCreated(
            $document->getSession()->getId()->toString(),
            $document->getId()->toString(),
            $this->serialize($reply)
        );

        return $reply;
    }

    public function update(Annotation $annotation, string $content): Annotation
    {
        $annotation->setContent($content);

        $mentions = $this->extractMentions($content, $annotation->getDocument()->getSession());
        $annotation->setMentions($mentions);

        $this->entityManager->flush();

        $document = $annotation->getDocument();
        $this->mercurePublisher->publishAnnotationUpdated(
            $document->getSession()->getId()->toString(),
            $document->getId()->toString(),
            $this->serialize($annotation)
        );

        return $annotation;
    }

    public function resolve(Annotation $annotation, Participant $resolvedBy): Annotation
    {
        $annotation->resolve($resolvedBy);
        $this->entityManager->flush();

        $document = $annotation->getDocument();
        $this->mercurePublisher->publishAnnotationResolved(
            $document->getSession()->getId()->toString(),
            $document->getId()->toString(),
            $this->serialize($annotation)
        );

        return $annotation;
    }

    public function markAsTakenIntoAccount(Annotation $annotation): Annotation
    {
        $annotation->setTakenIntoAccount(true);
        $this->entityManager->flush();

        $document = $annotation->getDocument();
        $this->mercurePublisher->publishAnnotationUpdated(
            $document->getSession()->getId()->toString(),
            $document->getId()->toString(),
            $this->serialize($annotation)
        );

        return $annotation;
    }

    public function setStatus(Annotation $annotation, string $status): Annotation
    {
        $annotation->setStatus($status);
        $this->entityManager->flush();

        $document = $annotation->getDocument();
        $this->mercurePublisher->publishAnnotationUpdated(
            $document->getSession()->getId()->toString(),
            $document->getId()->toString(),
            $this->serialize($annotation)
        );

        return $annotation;
    }

    public function serialize(Annotation $annotation): array
    {
        return [
            'id' => $annotation->getId()->toString(),
            'content' => $annotation->getContent(),
            'type' => $annotation->getType(),
            'status' => $annotation->getStatus(),
            'anchor' => $annotation->getAnchor(),
            'author' => [
                'id' => $annotation->getAuthor()->getId()->toString(),
                'pseudo' => $annotation->getAuthor()->getPseudo(),
                'color' => $annotation->getAuthor()->getColor(),
                'is_agent' => $annotation->getAuthor()->isAgent(),
            ],
            'document_id' => $annotation->getDocument()->getId()->toString(),
            'parent_id' => $annotation->getParentAnnotation()?->getId()->toString(),
            'mentions' => $annotation->getMentions(),
            'taken_into_account' => $annotation->isTakenIntoAccount(),
            'resolved_by' => $annotation->getResolvedBy()?->getPseudo(),
            'created_at' => $annotation->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $annotation->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'reply_count' => $annotation->getReplies()->count(),
        ];
    }

    /**
     * @return array<string>
     */
    private function extractMentions(string $content, \App\Entity\Session $session): array
    {
        preg_match_all('/@(\w+)/', $content, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $mentions = [];
        foreach ($matches[1] as $pseudo) {
            $participant = $this->participantRepository->findBySessionAndPseudo($session, $pseudo);
            if ($participant !== null) {
                $mentions[] = $participant->getId()->toString();
            }
        }

        return array_unique($mentions);
    }
}

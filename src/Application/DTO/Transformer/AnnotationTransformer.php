<?php

declare(strict_types=1);

namespace App\Application\DTO\Transformer;

use App\Application\DTO\Response\AnnotationResponse;
use App\Entity\Annotation;

final class AnnotationTransformer
{
    public function toResponse(Annotation $annotation, bool $includeReplies = false): AnnotationResponse
    {
        return AnnotationResponse::fromEntity($annotation, $includeReplies);
    }

    /**
     * @param iterable<Annotation> $annotations
     * @return array<AnnotationResponse>
     */
    public function toResponseList(iterable $annotations, bool $includeReplies = false): array
    {
        $responses = [];
        foreach ($annotations as $annotation) {
            $responses[] = $this->toResponse($annotation, $includeReplies);
        }
        return $responses;
    }

    /**
     * Transform annotations into a threaded structure.
     * Returns only top-level annotations with their replies included.
     *
     * @param iterable<Annotation> $annotations
     * @return array<AnnotationResponse>
     */
    public function toThreadedList(iterable $annotations): array
    {
        $topLevel = [];
        foreach ($annotations as $annotation) {
            if (!$annotation->isReply()) {
                $topLevel[] = $this->toResponse($annotation, includeReplies: true);
            }
        }
        return $topLevel;
    }

    /**
     * Filter and transform only open (unresolved) annotations.
     *
     * @param iterable<Annotation> $annotations
     * @return array<AnnotationResponse>
     */
    public function toOpenAnnotationsList(iterable $annotations): array
    {
        $open = [];
        foreach ($annotations as $annotation) {
            if ($annotation->getStatus() === 'open' && !$annotation->isReply()) {
                $open[] = $this->toResponse($annotation, includeReplies: true);
            }
        }
        return $open;
    }

    /**
     * Filter and transform priority annotations (open and not taken into account).
     *
     * @param iterable<Annotation> $annotations
     * @return array<AnnotationResponse>
     */
    public function toPriorityList(iterable $annotations): array
    {
        $priority = [];
        foreach ($annotations as $annotation) {
            if (
                $annotation->getStatus() === 'open'
                && !$annotation->isTakenIntoAccount()
                && !$annotation->isReply()
            ) {
                $priority[] = $this->toResponse($annotation, includeReplies: true);
            }
        }
        return $priority;
    }
}

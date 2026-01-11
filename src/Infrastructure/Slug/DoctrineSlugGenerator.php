<?php

declare(strict_types=1);

namespace App\Infrastructure\Slug;

use App\Application\Port\SlugGeneratorInterface;
use App\Domain\Document\ValueObject\DocumentSlug;
use App\Entity\Session;
use App\Repository\DocumentRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class DoctrineSlugGenerator implements SlugGeneratorInterface
{
    public function __construct(
        private SluggerInterface $slugger,
        private DocumentRepository $documentRepository,
    ) {}

    public function generateForDocument(string $title, Session $session): string
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
}

<?php

namespace App\Entity;

use App\Repository\AnnotationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AnnotationRepository::class)]
#[ORM\Table(name: 'annotations')]
#[ORM\Index(columns: ['status', 'type'])]
#[ORM\Index(columns: ['document_id', 'status'])]
#[ORM\HasLifecycleCallbacks]
class Annotation
{
    public const TYPE_COMMENT = 'comment';
    public const TYPE_QUESTION = 'question';
    public const TYPE_SUGGESTION = 'suggestion';
    public const TYPE_OBJECTION = 'objection';
    public const TYPE_VALIDATION = 'validation';

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';

    public const TYPES = [
        self::TYPE_COMMENT,
        self::TYPE_QUESTION,
        self::TYPE_SUGGESTION,
        self::TYPE_OBJECTION,
        self::TYPE_VALIDATION,
    ];

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(length: 50)]
    private string $type = self::TYPE_COMMENT;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_OPEN;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'annotations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $anchor = null;

    #[ORM\ManyToOne(targetEntity: Participant::class, inversedBy: 'annotations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Participant $author;

    #[ORM\ManyToOne(targetEntity: Annotation::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Annotation $parentAnnotation = null;

    /** @var Collection<int, Annotation> */
    #[ORM\OneToMany(targetEntity: Annotation::class, mappedBy: 'parentAnnotation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $replies;

    /** @var array<string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $mentions = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $takenIntoAccount = false;

    #[ORM\ManyToOne(targetEntity: Participant::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Participant $resolvedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->replies = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function setDocument(Document $document): static
    {
        $this->document = $document;
        return $this;
    }

    public function getAnchor(): ?array
    {
        return $this->anchor;
    }

    public function setAnchor(?array $anchor): static
    {
        $this->anchor = $anchor;
        return $this;
    }

    public function getAuthor(): Participant
    {
        return $this->author;
    }

    public function setAuthor(Participant $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function getParentAnnotation(): ?Annotation
    {
        return $this->parentAnnotation;
    }

    public function setParentAnnotation(?Annotation $parentAnnotation): static
    {
        $this->parentAnnotation = $parentAnnotation;
        return $this;
    }

    /** @return Collection<int, Annotation> */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function addReply(Annotation $reply): static
    {
        if (!$this->replies->contains($reply)) {
            $this->replies->add($reply);
            $reply->setParentAnnotation($this);
        }
        return $this;
    }

    public function removeReply(Annotation $reply): static
    {
        if ($this->replies->removeElement($reply)) {
            if ($reply->getParentAnnotation() === $this) {
                $reply->setParentAnnotation(null);
            }
        }
        return $this;
    }

    /** @return array<string>|null */
    public function getMentions(): ?array
    {
        return $this->mentions;
    }

    /** @param array<string>|null $mentions */
    public function setMentions(?array $mentions): static
    {
        $this->mentions = $mentions;
        return $this;
    }

    public function isTakenIntoAccount(): bool
    {
        return $this->takenIntoAccount;
    }

    public function setTakenIntoAccount(bool $takenIntoAccount): static
    {
        $this->takenIntoAccount = $takenIntoAccount;
        return $this;
    }

    public function getResolvedBy(): ?Participant
    {
        return $this->resolvedBy;
    }

    public function setResolvedBy(?Participant $resolvedBy): static
    {
        $this->resolvedBy = $resolvedBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isReply(): bool
    {
        return $this->parentAnnotation !== null;
    }

    public function resolve(Participant $resolvedBy): static
    {
        $this->status = self::STATUS_RESOLVED;
        $this->resolvedBy = $resolvedBy;
        return $this;
    }
}

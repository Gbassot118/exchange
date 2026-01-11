<?php

namespace App\Entity;

use App\Repository\EstimationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EstimationRepository::class)]
#[ORM\Table(name: 'estimations')]
#[ORM\HasLifecycleCallbacks]
class Estimation
{
    public const STATUS_OPEN = 'open';
    public const STATUS_REVEALED = 'revealed';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_REVEALED,
        self::STATUS_CLOSED,
    ];

    public const FIBONACCI_VALUES = ['0', '1', '2', '3', '5', '8', '13', '21', '?'];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_OPEN;

    #[ORM\ManyToOne(targetEntity: Session::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Session $session;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Document $linkedDocument = null;

    /** @var Collection<int, EstimationVote> */
    #[ORM\OneToMany(targetEntity: EstimationVote::class, mappedBy: 'estimation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $votes;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revealedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->votes = new ArrayCollection();
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    public function getSession(): Session
    {
        return $this->session;
    }

    public function setSession(Session $session): static
    {
        $this->session = $session;
        return $this;
    }

    public function getLinkedDocument(): ?Document
    {
        return $this->linkedDocument;
    }

    public function setLinkedDocument(?Document $linkedDocument): static
    {
        $this->linkedDocument = $linkedDocument;
        return $this;
    }

    /** @return Collection<int, EstimationVote> */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(EstimationVote $vote): static
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setEstimation($this);
        }
        return $this;
    }

    public function removeVote(EstimationVote $vote): static
    {
        $this->votes->removeElement($vote);
        return $this;
    }

    public function getRevealedAt(): ?\DateTimeImmutable
    {
        return $this->revealedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isRevealed(): bool
    {
        return $this->status === self::STATUS_REVEALED || $this->status === self::STATUS_CLOSED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function reveal(): static
    {
        $this->status = self::STATUS_REVEALED;
        $this->revealedAt = new \DateTimeImmutable();
        return $this;
    }

    public function close(): static
    {
        $this->status = self::STATUS_CLOSED;
        return $this;
    }

    public function calculateAverage(): ?float
    {
        if (!$this->isRevealed()) {
            return null;
        }

        $numericValues = [];
        foreach ($this->votes as $vote) {
            $value = $vote->getValue();
            if ($value !== '?' && is_numeric($value)) {
                $numericValues[] = (int) $value;
            }
        }

        if (count($numericValues) === 0) {
            return null;
        }

        return round(array_sum($numericValues) / count($numericValues), 1);
    }

    /** @return array<string> */
    public function getVoterParticipantIds(): array
    {
        return $this->votes->map(
            fn(EstimationVote $v) => $v->getParticipant()->getId()->toString()
        )->toArray();
    }

    public function getVoteForParticipant(Participant $participant): ?EstimationVote
    {
        foreach ($this->votes as $vote) {
            if ($vote->getParticipant()->getId()->equals($participant->getId())) {
                return $vote;
            }
        }
        return null;
    }

    public static function isValidFibonacciValue(string $value): bool
    {
        return in_array($value, self::FIBONACCI_VALUES, true);
    }
}

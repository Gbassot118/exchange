<?php

namespace App\Entity;

use App\Repository\DecisionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DecisionRepository::class)]
#[ORM\Table(name: 'decisions')]
#[ORM\HasLifecycleCallbacks]
class Decision
{
    public const STATUS_OUVERT = 'ouvert';
    public const STATUS_EN_DISCUSSION = 'en_discussion';
    public const STATUS_CONSENSUS = 'consensus';
    public const STATUS_VALIDE = 'valide';
    public const STATUS_REPORTE = 'reporte';

    public const STATUSES = [
        self::STATUS_OUVERT,
        self::STATUS_EN_DISCUSSION,
        self::STATUS_CONSENSUS,
        self::STATUS_VALIDE,
        self::STATUS_REPORTE,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_OUVERT;

    /** @var array<array{id: string, label: string, description?: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $options = [];

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'decisions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Session $session;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Document $linkedDocument = null;

    /** @var Collection<int, Vote> */
    #[ORM\OneToMany(targetEntity: Vote::class, mappedBy: 'decision', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $votes;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $selectedOptionId = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isLocked = false;

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

    /** @return array<array{id: string, label: string, description?: string}> */
    public function getOptions(): array
    {
        return $this->options;
    }

    /** @param array<array{id: string, label: string, description?: string}> $options */
    public function setOptions(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function addOption(string $label, ?string $description = null): static
    {
        $this->options[] = [
            'id' => Uuid::v7()->toString(),
            'label' => $label,
            'description' => $description,
        ];
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

    /** @return Collection<int, Vote> */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(Vote $vote): static
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setDecision($this);
        }
        return $this;
    }

    public function removeVote(Vote $vote): static
    {
        $this->votes->removeElement($vote);
        return $this;
    }

    public function getSelectedOptionId(): ?Uuid
    {
        return $this->selectedOptionId;
    }

    public function setSelectedOptionId(?Uuid $selectedOptionId): static
    {
        $this->selectedOptionId = $selectedOptionId;
        return $this;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function setIsLocked(bool $isLocked): static
    {
        $this->isLocked = $isLocked;
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

    public function lock(): static
    {
        $this->isLocked = true;
        return $this;
    }

    public function validate(Uuid $selectedOptionId): static
    {
        $this->selectedOptionId = $selectedOptionId;
        $this->status = self::STATUS_VALIDE;
        $this->isLocked = true;
        return $this;
    }

    /** @return array<string, int> */
    public function getVoteStats(): array
    {
        $stats = [];
        foreach ($this->options as $option) {
            $stats[$option['id']] = 0;
        }
        foreach ($this->votes as $vote) {
            $optionId = $vote->getOptionId()->toString();
            if (isset($stats[$optionId])) {
                $stats[$optionId]++;
            }
        }
        return $stats;
    }

    public function getVoteForParticipant(Participant $participant): ?Vote
    {
        foreach ($this->votes as $vote) {
            if ($vote->getParticipant()->getId()->equals($participant->getId())) {
                return $vote;
            }
        }
        return null;
    }
}

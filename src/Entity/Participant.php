<?php

namespace App\Entity;

use App\Repository\ParticipantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ParticipantRepository::class)]
#[ORM\Table(name: 'participants')]
#[ORM\UniqueConstraint(columns: ['session_id', 'pseudo'])]
class Participant
{
    private const COLORS = [
        '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6',
        '#EC4899', '#06B6D4', '#84CC16', '#F97316', '#6366F1',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $pseudo;

    #[ORM\Column(length: 7)]
    private string $color;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Session $session;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $currentDocumentId = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isAgent = false;

    /** @var Collection<int, Annotation> */
    #[ORM\OneToMany(targetEntity: Annotation::class, mappedBy: 'author')]
    private Collection $annotations;

    /** @var Collection<int, Vote> */
    #[ORM\OneToMany(targetEntity: Vote::class, mappedBy: 'participant')]
    private Collection $votes;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->color = self::COLORS[array_rand(self::COLORS)];
        $this->annotations = new ArrayCollection();
        $this->votes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPseudo(): string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;
        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;
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

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;
        return $this;
    }

    public function getCurrentDocumentId(): ?Uuid
    {
        return $this->currentDocumentId;
    }

    public function setCurrentDocumentId(?Uuid $currentDocumentId): static
    {
        $this->currentDocumentId = $currentDocumentId;
        return $this;
    }

    public function isAgent(): bool
    {
        return $this->isAgent;
    }

    public function setIsAgent(bool $isAgent): static
    {
        $this->isAgent = $isAgent;
        return $this;
    }

    /** @return Collection<int, Annotation> */
    public function getAnnotations(): Collection
    {
        return $this->annotations;
    }

    /** @return Collection<int, Vote> */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isOnline(): bool
    {
        if ($this->lastSeenAt === null) {
            return false;
        }
        return $this->lastSeenAt > new \DateTimeImmutable('-30 seconds');
    }
}

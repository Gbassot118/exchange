<?php

declare(strict_types=1);

namespace App\Domain\Decision\ValueObject;

final readonly class DecisionOption
{
    private function __construct(
        private OptionId $id,
        private string $label,
        private ?string $description,
    ) {}

    public static function create(string $label, ?string $description = null): self
    {
        if (empty(trim($label))) {
            throw new \InvalidArgumentException('Option label cannot be empty');
        }

        return new self(
            OptionId::generate(),
            trim($label),
            $description !== null ? trim($description) : null
        );
    }

    public static function fromArray(array $data): self
    {
        $id = isset($data['id'])
            ? OptionId::fromString($data['id'])
            : OptionId::generate();

        $label = $data['label'] ?? throw new \InvalidArgumentException('Option must have a label');
        $description = $data['description'] ?? $data['text'] ?? null;

        return new self($id, trim($label), $description !== null ? trim($description) : null);
    }

    public function getId(): OptionId
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array{id: string, label: string, description: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'label' => $this->label,
            'description' => $this->description,
        ];
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}

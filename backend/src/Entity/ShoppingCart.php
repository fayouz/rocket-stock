<?php

namespace App\Entity;

use App\Repository\ShoppingCartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A shopping trip built from the levels below their threshold (App\Stock\Replenishment): draft → in_progress (lines
 * ticked while shopping) → done (an "in" movement per ticked line into its location, externalRef
 * "cart:<cart id>:<line id>", so finishing twice never counts twice). placeId null = every place.
 */
#[ORM\Entity(repositoryClass: ShoppingCartRepository::class)]
class ShoppingCart
{
    public const DRAFT = 'draft';
    public const IN_PROGRESS = 'in_progress';
    public const DONE = 'done';
    public const STATUSES = [self::DRAFT, self::IN_PROGRESS, self::DONE];

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'place_id', length: 36, nullable: true)]
    private ?string $placeId;

    #[ORM\Column(length: 12)]
    private string $status = self::DRAFT;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /** @var Collection<int, CartLine> */
    #[ORM\OneToMany(targetEntity: CartLine::class, mappedBy: 'cart', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    use TrackedTrait;

    public function __construct(?string $placeId)
    {
        $this->id = Uuid::v7();
        $this->placeId = $placeId;
        $this->lines = new ArrayCollection();
    }

    public function getId(): Uuid { return $this->id; }
    public function getPlaceId(): ?string { return $this->placeId; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): static { $this->status = $s; return $this; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeImmutable $d): static { $this->completedAt = $d; return $this; }

    /** @return Collection<int, CartLine> */
    public function getLines(): Collection { return $this->lines; }

    public function addLine(CartLine $line): static
    {
        $this->lines->add($line);

        return $this;
    }

    /** @return array<string, mixed> lines grouped by store (lines without offer under store null), totals */
    public function toArray(): array
    {
        $groups = [];
        $total = 0.0;
        foreach ($this->lines as $line) {
            $store = $line->getStore();
            $key = $store?->getId()->toRfc4122() ?? '';
            $groups[$key] ??= ['store' => null === $store ? null : ['id' => $key, 'name' => $store->getName(), 'kind' => $store->getKind(), 'address' => $store->toArray()['address'], 'openingHours' => $store->toArray()['openingHours']], 'lines' => [], 'estimatedTotal' => 0.0];
            $groups[$key]['lines'][] = $line->toArray();
            $groups[$key]['estimatedTotal'] = round($groups[$key]['estimatedTotal'] + ($line->getEstimatedCost() ?? 0.0), 2);
            $total += $line->getEstimatedCost() ?? 0.0;
        }
        uasort($groups, static fn (array $a, array $b) => (null === $a['store']) <=> (null === $b['store']) ?: strcmp($a['store']['name'] ?? '', $b['store']['name'] ?? ''));

        return [
            'id' => $this->id->toRfc4122(), 'placeId' => $this->placeId, 'status' => $this->status,
            'createdAt' => $this->getCreatedAt()?->format(\DATE_ATOM), 'completedAt' => $this->completedAt?->format(\DATE_ATOM),
            'createdBy' => $this->getCreatedBy(),
            'lineCount' => \count($this->lines), 'checkedCount' => \count(array_filter($this->lines->toArray(), static fn (CartLine $l) => $l->isChecked())),
            'stores' => array_values($groups), 'estimatedTotal' => round($total, 2),
        ];
    }
}

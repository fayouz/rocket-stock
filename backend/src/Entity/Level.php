<?php

namespace App\Entity;

use App\Repository\LevelRepository;
use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * An article tracked at a location: a quantity (null when only checked by eye) and a simple state ok/low/empty for
 * quick checks (as Rocket Place's StockLevel). Setting the quantity recomputes the state against the threshold (the
 * level's override, else the article's); setting the state keeps the quantity, except "empty" which sets it to 0.
 * Serialized like Place's StockLevel: "place" and "item" are IRIs, "level" the state.
 */
#[ORM\Entity(repositoryClass: LevelRepository::class)]
#[ORM\Table(name: 'stock_level')]
#[ORM\UniqueConstraint(name: 'uniq_stock_level_location_item', columns: ['location_id', 'item_id'])]
class Level
{
    public const OK = 'ok';
    public const LOW = 'low';
    public const EMPTY = 'empty';
    public const LEVELS = [self::OK, self::LOW, self::EMPTY];

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Location $location;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Item $item;

    #[ORM\Column(nullable: true)]
    private ?float $quantity = null;

    #[ORM\Column(length: 8)]
    private string $level = self::OK;

    #[ORM\Column(nullable: true)]
    private ?float $thresholdOverride = null;

    /** Quantity to go back to when restocking (shopping cart); null = twice the threshold. */
    #[ORM\Column(nullable: true)]
    private ?float $targetQuantity = null;

    use TrackedTrait;

    public function __construct(Location $location, Item $item, string $level = self::OK)
    {
        $this->id = Uuid::v7();
        $this->location = $location;
        $this->item = $item;
        $this->level = $level;
    }

    public function getId(): Uuid { return $this->id; }
    public function getLocation(): Location { return $this->location; }
    public function getItem(): Item { return $this->item; }
    public function getQuantity(): ?float { return $this->quantity; }
    public function getLevel(): string { return $this->level; }
    public function getThresholdOverride(): ?float { return $this->thresholdOverride; }
    public function threshold(): float { return $this->thresholdOverride ?? $this->item->getReorderThreshold(); }

    public function getTargetQuantity(): ?float { return $this->targetQuantity; }
    public function setTargetQuantity(?float $t): static { $this->targetQuantity = null === $t ? null : max(0.0, $t); return $this; }
    public function target(): float { return $this->targetQuantity ?? $this->threshold() * 2; }

    /** Below its threshold: low/empty, or a known quantity under the threshold. */
    public function needsRestock(): bool
    {
        return self::OK !== $this->level || (null !== $this->quantity && $this->quantity < $this->threshold());
    }

    /** Current quantity for restocking: the known one, else 0 when empty, else the threshold (checked by eye). */
    public function estimatedQuantity(): float
    {
        return $this->quantity ?? (self::EMPTY === $this->level ? 0.0 : (self::LOW === $this->level ? $this->threshold() : $this->target()));
    }

    public function setQuantity(?float $quantity): static
    {
        $this->quantity = null === $quantity ? null : max(0.0, round($quantity, 3));
        if (null !== $this->quantity) {
            $this->level = $this->quantity <= 0 ? self::EMPTY : ($this->quantity <= $this->threshold() ? self::LOW : self::OK);
        }

        return $this;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;
        if (self::EMPTY === $level) {
            $this->quantity = 0.0;
        }

        return $this;
    }

    public function setThresholdOverride(?float $t): static
    {
        $this->thresholdOverride = null === $t ? null : max(0.0, $t);

        return $this->setQuantity($this->quantity);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'place' => '/api/places/'.$this->location->getPlaceId(),
            'item' => '/api/stock-items/'.$this->item->getId()->toRfc4122(),
            'level' => $this->level,
            'placeId' => $this->location->getPlaceId(),
            'location' => $this->location->toArray(),
            'name' => $this->item->getName(),
            'itemName' => $this->item->getName(),
            'unit' => $this->item->getUnit(),
            'category' => $this->item->getCategory(),
            'quantity' => $this->quantity,
            'threshold' => $this->threshold(),
            'thresholdOverride' => $this->thresholdOverride,
            'targetQuantity' => $this->targetQuantity,
            'target' => $this->target(),
            'createdAt' => $this->getCreatedAt()?->format(\DATE_ATOM),
            'updatedAt' => $this->getUpdatedAt()?->format(\DATE_ATOM),
        ];
    }
}

<?php

namespace App\Entity;

use App\Repository\MovementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A change of stock, applied to the levels when recorded (App\Stock\StockBook):
 * - in: bought / delivered (+quantity at location); out: removed (lost, given, thrown away); consume: used up during
 *   a stay or a cleaning (both −quantity); transfer: from location to toLocation; adjust: an inventory count
 *   (quantity = the counted quantity, the level is set to it).
 * "usage" splits rental consumption (fed to the host's bilan: export filtered by usage, valued at the unit cost) from
 * personal use. "origin" tells which brick recorded it (host, pms, place, clean, stock) and "originApp" the name of
 * the calling application. "externalRef" (e.g. "cleaning:<id>") makes the recording idempotent: unique.
 */
#[ORM\Entity(repositoryClass: MovementRepository::class)]
#[ORM\Table(name: 'stock_movement')]
#[ORM\Index(name: 'idx_stock_movement_place_at', columns: ['place_id', 'occurred_at'])]
class Movement
{
    public const TYPES = ['in', 'out', 'consume', 'transfer', 'adjust'];
    public const ORIGINS = ['host', 'pms', 'place', 'clean', 'stock'];
    public const USAGES = ['rental', 'personal'];

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Item $item;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Location $location;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Location $toLocation = null;

    /** Copy of the location's place id, for the export by place. */
    #[ORM\Column(name: 'place_id', length: 36)]
    private string $placeId;

    #[ORM\Column(length: 12)]
    private string $type;

    #[ORM\Column]
    private float $quantity;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(length: 120, unique: true, nullable: true)]
    private ?string $externalRef = null;

    #[ORM\Column(length: 8)]
    private string $origin = 'stock';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $originApp = null;

    #[ORM\Column(name: 'usage_kind', length: 8)]
    private string $usage = 'rental';

    /** Unit cost at the time of the movement (the article's, unless given). */
    #[ORM\Column(nullable: true)]
    private ?float $unitCost = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    use TrackedTrait;

    public function __construct(Item $item, Location $location, string $type, float $quantity, \DateTimeImmutable $occurredAt = new \DateTimeImmutable())
    {
        $this->id = Uuid::v7();
        $this->item = $item;
        $this->location = $location;
        $this->placeId = $location->getPlaceId();
        $this->type = $type;
        $this->quantity = $quantity;
        $this->occurredAt = $occurredAt;
        $this->unitCost = $item->getUnitCost();
    }

    public function getId(): Uuid { return $this->id; }
    public function getItem(): Item { return $this->item; }
    public function getLocation(): Location { return $this->location; }
    public function getToLocation(): ?Location { return $this->toLocation; }
    public function setToLocation(?Location $l): static { $this->toLocation = $l; return $this; }
    public function getPlaceId(): string { return $this->placeId; }
    public function getType(): string { return $this->type; }
    public function getQuantity(): float { return $this->quantity; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $r): static { $this->reason = $r; return $this; }
    public function getExternalRef(): ?string { return $this->externalRef; }
    public function setExternalRef(?string $r): static { $this->externalRef = $r; return $this; }
    public function getOrigin(): string { return $this->origin; }
    public function setOrigin(string $o): static { $this->origin = $o; return $this; }
    public function getOriginApp(): ?string { return $this->originApp; }
    public function setOriginApp(?string $a): static { $this->originApp = $a; return $this; }
    public function getUsage(): string { return $this->usage; }
    public function setUsage(string $u): static { $this->usage = $u; return $this; }
    public function getUnitCost(): ?float { return $this->unitCost; }
    public function setUnitCost(?float $c): static { $this->unitCost = $c; return $this; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }

    /** Whether the movement uses stock up (valued in the bilan): consume and out. */
    public function isConsumption(): bool
    {
        return 'consume' === $this->type || 'out' === $this->type;
    }

    public function cost(): ?float
    {
        return null === $this->unitCost ? null : round($this->unitCost * $this->quantity, 2);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(), 'type' => $this->type, 'quantity' => $this->quantity,
            'item' => '/api/stock-items/'.$this->item->getId()->toRfc4122(), 'itemId' => $this->item->getId()->toRfc4122(), 'itemName' => $this->item->getName(), 'unit' => $this->item->getUnit(),
            'placeId' => $this->placeId, 'location' => $this->location->toArray(), 'toLocation' => $this->toLocation?->toArray(),
            'reason' => $this->reason, 'externalRef' => $this->externalRef, 'origin' => $this->origin, 'originApp' => $this->originApp,
            'usage' => $this->usage, 'unitCost' => $this->unitCost, 'cost' => $this->cost(),
            'occurredAt' => $this->occurredAt->format(\DATE_ATOM), 'createdBy' => $this->getCreatedBy(),
        ];
    }
}

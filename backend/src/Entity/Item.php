<?php

namespace App\Entity;

use App\Repository\ItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A catalogue article, shared by every place: consumable (coffee, toilet paper…), linen (sheets, towels) or equipment
 * (a kettle, a vacuum cleaner). Serialized with the fields of Rocket Place's StockItem (name, asin, reorderQty,
 * subscription) so a client of /api/stock-items keeps working, plus Stock's own (unit, category, sku, threshold,
 * supplier, unit cost). Table "stock_item" ("item" alone is too generic).
 */
#[ORM\Entity(repositoryClass: ItemRepository::class)]
#[ORM\Table(name: 'stock_item')]
class Item
{
    public const CATEGORIES = ['consumable', 'linen', 'equipment'];

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 24)]
    private string $unit = 'unité';

    #[ORM\Column(length: 16)]
    private string $category = 'consumable';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sku = null;

    /** At or below this quantity, a level is "low" (a place may override it on its level). */
    #[ORM\Column]
    private float $reorderThreshold = 1.0;

    /** Quantity usually bought at once (shopping list). */
    #[ORM\Column]
    private int $reorderQty = 1;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $asin = null;

    /** Barcode EAN-13 or EAN-8 (digits only, check digit validated by App\Ordering\Ean). */
    #[ORM\Column(length: 13, nullable: true)]
    private ?string $ean = null;

    /** Delivered on subscription: listed apart on the shopping list. */
    #[ORM\Column]
    private bool $subscription = false;

    /** Cost of one unit (euros), used by the export to value consumption. */
    #[ORM\Column(nullable: true)]
    private ?float $unitCost = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Supplier $supplier = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    use TrackedTrait;

    public function __construct(string $name)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
    }

    public function getId(): Uuid { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getUnit(): string { return $this->unit; }
    public function setUnit(string $unit): static { $this->unit = $unit; return $this; }
    public function getCategory(): string { return $this->category; }
    public function setCategory(string $category): static { $this->category = $category; return $this; }
    public function getSku(): ?string { return $this->sku; }
    public function setSku(?string $sku): static { $this->sku = $sku; return $this; }
    public function getReorderThreshold(): float { return $this->reorderThreshold; }
    public function setReorderThreshold(float $t): static { $this->reorderThreshold = max(0.0, $t); return $this; }
    public function getReorderQty(): int { return $this->reorderQty; }
    public function setReorderQty(int $qty): static { $this->reorderQty = max(0, $qty); return $this; }
    public function getAsin(): ?string { return $this->asin; }
    public function setAsin(?string $asin): static { $this->asin = $asin; return $this; }
    public function getEan(): ?string { return $this->ean; }
    public function setEan(?string $ean): static { $this->ean = $ean; return $this; }
    public function isSubscription(): bool { return $this->subscription; }
    public function setSubscription(bool $s): static { $this->subscription = $s; return $this; }
    public function getUnitCost(): ?float { return $this->unitCost; }
    public function setUnitCost(?float $c): static { $this->unitCost = null === $c ? null : max(0.0, $c); return $this; }
    public function getSupplier(): ?Supplier { return $this->supplier; }
    public function setSupplier(?Supplier $s): static { $this->supplier = $s; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): static { $this->notes = $n; return $this; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(), 'name' => $this->name, 'asin' => $this->asin, 'reorderQty' => $this->reorderQty,
            'subscription' => $this->subscription, 'unit' => $this->unit, 'category' => $this->category, 'sku' => $this->sku, 'ean' => $this->ean,
            'reorderThreshold' => $this->reorderThreshold, 'unitCost' => $this->unitCost,
            'supplier' => null === $this->supplier ? null : ['id' => $this->supplier->getId()->toRfc4122(), 'name' => $this->supplier->getName()],
            'notes' => $this->notes, 'createdAt' => $this->getCreatedAt()?->format(\DATE_ATOM), 'updatedAt' => $this->getUpdatedAt()?->format(\DATE_ATOM),
        ];
    }
}

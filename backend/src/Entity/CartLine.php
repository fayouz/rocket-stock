<?php

namespace App\Entity;

use App\Repository\CartLineRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** One line of a shopping cart: an article to buy for a location, at a store, rounded up to its pack size. */
#[ORM\Entity(repositoryClass: CartLineRepository::class)]
class CartLine
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ShoppingCart $cart;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Item $item;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Location $location;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Supplier $store = null;

    #[ORM\Column]
    private int $position;

    /** Quantity to buy, in the article's unit (a multiple of packSize). */
    #[ORM\Column]
    private float $quantity;

    #[ORM\Column]
    private float $packSize = 1.0;

    /** Price of one pack when known. */
    #[ORM\Column(nullable: true)]
    private ?float $packPrice = null;

    #[ORM\Column]
    private bool $checked = false;

    public function __construct(ShoppingCart $cart, Item $item, Location $location, float $quantity, int $position)
    {
        $this->id = Uuid::v7();
        $this->cart = $cart;
        $this->item = $item;
        $this->location = $location;
        $this->quantity = $quantity;
        $this->position = $position;
    }

    public function getId(): Uuid { return $this->id; }
    public function getCart(): ShoppingCart { return $this->cart; }
    public function getItem(): Item { return $this->item; }
    public function getLocation(): Location { return $this->location; }
    public function getStore(): ?Supplier { return $this->store; }
    public function setStore(?Supplier $s): static { $this->store = $s; return $this; }
    public function getQuantity(): float { return $this->quantity; }
    public function setQuantity(float $q): static { $this->quantity = max(0.0, $q); return $this; }
    public function getPackSize(): float { return $this->packSize; }
    public function setPackSize(float $s): static { $this->packSize = $s > 0 ? $s : 1.0; return $this; }
    public function setPackPrice(?float $p): static { $this->packPrice = $p; return $this; }
    public function isChecked(): bool { return $this->checked; }
    public function setChecked(bool $c): static { $this->checked = $c; return $this; }

    public function packs(): float
    {
        return ceil(round($this->quantity / $this->packSize, 6));
    }

    public function getEstimatedCost(): ?float
    {
        if (null !== $this->packPrice) {
            return round($this->packs() * $this->packPrice, 2);
        }

        return null === $this->item->getUnitCost() ? null : round($this->quantity * $this->item->getUnitCost(), 2);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'item' => ['id' => $this->item->getId()->toRfc4122(), 'name' => $this->item->getName(), 'unit' => $this->item->getUnit(), 'category' => $this->item->getCategory()],
            'placeId' => $this->location->getPlaceId(), 'location' => $this->location->getName(),
            'quantity' => $this->quantity, 'packSize' => $this->packSize, 'packs' => $this->packs(), 'packPrice' => $this->packPrice,
            'estimatedCost' => $this->getEstimatedCost(), 'checked' => $this->checked,
        ];
    }
}

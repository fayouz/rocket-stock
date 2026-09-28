<?php

namespace App\Entity;

use App\Repository\ItemOfferRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Where an article is bought: a store (or supplier), whether it is a preferred one, the price of one pack and the
 * pack size (quantity bought at once, in the article's unit). The shopping cart rounds up to the pack size of the
 * preferred offer and groups lines by its store.
 */
#[ORM\Entity(repositoryClass: ItemOfferRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_item_offer_item_supplier', columns: ['item_id', 'supplier_id'])]
class ItemOffer
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Item $item;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Supplier $supplier;

    #[ORM\Column]
    private bool $preferred = false;

    /** Price of one pack (euros). */
    #[ORM\Column(nullable: true)]
    private ?float $price = null;

    #[ORM\Column]
    private float $packSize = 1.0;

    /** Amazon product id at this store (Amazon stores; falls back to the article's asin). */
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $asin = null;

    /** Direct link to the product on the store's website (wins over the store's search template). */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $productUrl = null;

    public function __construct(Item $item, Supplier $supplier)
    {
        $this->id = Uuid::v7();
        $this->item = $item;
        $this->supplier = $supplier;
    }

    public function getId(): Uuid { return $this->id; }
    public function getItem(): Item { return $this->item; }
    public function getSupplier(): Supplier { return $this->supplier; }
    public function isPreferred(): bool { return $this->preferred; }
    public function setPreferred(bool $p): static { $this->preferred = $p; return $this; }
    public function getPrice(): ?float { return $this->price; }
    public function setPrice(?float $p): static { $this->price = null === $p ? null : max(0.0, $p); return $this; }
    public function getPackSize(): float { return $this->packSize; }
    public function setPackSize(float $s): static { $this->packSize = $s > 0 ? $s : 1.0; return $this; }

    public function getAsin(): ?string { return $this->asin; }
    public function setAsin(?string $a): static { $this->asin = null === $a ? null : strtoupper($a); return $this; }
    public function getProductUrl(): ?string { return $this->productUrl; }
    public function setProductUrl(?string $u): static { $this->productUrl = $u; return $this; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(), 'item' => '/api/stock-items/'.$this->item->getId()->toRfc4122(),
            'store' => ['id' => $this->supplier->getId()->toRfc4122(), 'name' => $this->supplier->getName(), 'kind' => $this->supplier->getKind()],
            'preferred' => $this->preferred, 'price' => $this->price, 'packSize' => $this->packSize, 'asin' => $this->asin, 'productUrl' => $this->productUrl,
        ];
    }
}

<?php

namespace App\Entity;

use App\Repository\SupplierRepository;
use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Where articles are bought: kind "store" (a shop you go to: address, coordinates, opening hours — the "magasins" of
 * the shopping cart) or "supplier" (a website, a wholesaler, a delivery). Items name their offers (ItemOffer).
 */
#[ORM\Entity(repositoryClass: SupplierRepository::class)]
class Supplier
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    private string $name;

    public const KINDS = ['supplier', 'store'];

    #[ORM\Column(length: 12)]
    private string $kind = 'supplier';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(nullable: true)]
    private ?float $lat = null;

    #[ORM\Column(nullable: true)]
    private ?float $lng = null;

    /** Free text ("lun-sam 8h30-20h, dim 9h-12h"). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $openingHours = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** Address purchase orders are e-mailed to (only on an explicit, confirmed request). */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $orderEmail = null;

    /** Product search on the store's website, "{ean}" (or "{name}") replaced: https://www.carrefour.fr/s?q={ean}. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $searchUrlTemplate = null;

    /** Amazon store: the cart page builds a pre-filled Amazon cart (the user pays on Amazon, never automatically). */
    #[ORM\Column(options: ['default' => false])]
    private bool $amazon = false;

    /** Amazon marketplace domain (amazon.fr, amazon.de…). */
    #[ORM\Column(length: 40, options: ['default' => 'amazon.fr'])]
    private string $amazonDomain = 'amazon.fr';

    use TrackedTrait;

    public function __construct(string $name)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
    }

    public function getId(): Uuid { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getKind(): string { return $this->kind; }
    public function setKind(string $k): static { $this->kind = $k; return $this; }
    public function setAddress(?string $v): static { $this->address = $v; return $this; }
    public function setLat(?float $v): static { $this->lat = $v; return $this; }
    public function setLng(?float $v): static { $this->lng = $v; return $this; }
    public function setOpeningHours(?string $v): static { $this->openingHours = $v; return $this; }
    public function getWebsite(): ?string { return $this->website; }
    public function setWebsite(?string $v): static { $this->website = $v; return $this; }
    public function setEmail(?string $v): static { $this->email = $v; return $this; }
    public function setPhone(?string $v): static { $this->phone = $v; return $this; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getOrderEmail(): ?string { return $this->orderEmail; }
    public function setOrderEmail(?string $v): static { $this->orderEmail = $v; return $this; }
    public function getSearchUrlTemplate(): ?string { return $this->searchUrlTemplate; }
    public function setSearchUrlTemplate(?string $v): static { $this->searchUrlTemplate = $v; return $this; }
    public function isAmazon(): bool { return $this->amazon; }
    public function setAmazon(bool $v): static { $this->amazon = $v; return $this; }
    public function getAmazonDomain(): string { return $this->amazonDomain; }
    public function setAmazonDomain(?string $v): static { $this->amazonDomain = null === $v || '' === $v ? 'amazon.fr' : $v; return $this; }

    /** URL of the product on the store's website from the template, or null. */
    public function searchUrl(?string $ean, string $name): ?string
    {
        if (null === $this->searchUrlTemplate || (null === $ean && !str_contains($this->searchUrlTemplate, '{name}'))) {
            return null;
        }

        return strtr($this->searchUrlTemplate, ['{ean}' => rawurlencode($ean ?? $name), '{name}' => rawurlencode($name)]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id->toRfc4122(), 'name' => $this->name, 'kind' => $this->kind, 'address' => $this->address, 'lat' => $this->lat, 'lng' => $this->lng, 'openingHours' => $this->openingHours, 'website' => $this->website, 'email' => $this->email, 'phone' => $this->phone, 'notes' => $this->notes, 'orderEmail' => $this->orderEmail, 'searchUrlTemplate' => $this->searchUrlTemplate, 'amazon' => $this->amazon, 'amazonDomain' => $this->amazonDomain];
    }
}

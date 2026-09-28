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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id->toRfc4122(), 'name' => $this->name, 'kind' => $this->kind, 'address' => $this->address, 'lat' => $this->lat, 'lng' => $this->lng, 'openingHours' => $this->openingHours, 'website' => $this->website, 'email' => $this->email, 'phone' => $this->phone, 'notes' => $this->notes];
    }
}

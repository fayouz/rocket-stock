<?php

namespace App\Entity;

use App\Repository\EquipmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A durable appliance of a place (washing machine, coffee machine…): serial number, purchase date, end of warranty and
 * the reference of its manual in Rocket Cloud (a file id, opened from Cloud). Place by id, like every stock row.
 */
#[ORM\Entity(repositoryClass: EquipmentRepository::class)]
class Equipment
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(name: 'place_id', length: 36, nullable: true)]
    private ?string $placeId = null;

    /** Sub-location within the place ("cuisine"…). */
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $room = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Item $item = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Supplier $supplier = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $serial = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $purchaseDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $warrantyEnd = null;

    /** Rocket Cloud reference of the manual (file id or path). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $manualDocumentRef = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    use TrackedTrait;

    public function __construct(string $name)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
    }

    public function getId(): Uuid { return $this->id; }
    public function getPlaceId(): ?string { return $this->placeId; }
    public function setName(string $n): static { $this->name = $n; return $this; }
    public function setPlaceId(?string $p): static { $this->placeId = $p; return $this; }
    public function setRoom(?string $r): static { $this->room = $r; return $this; }
    public function setItem(?Item $i): static { $this->item = $i; return $this; }
    public function setSupplier(?Supplier $s): static { $this->supplier = $s; return $this; }
    public function setSerial(?string $s): static { $this->serial = $s; return $this; }
    public function setPurchaseDate(?\DateTimeImmutable $d): static { $this->purchaseDate = $d; return $this; }
    public function getWarrantyEnd(): ?\DateTimeImmutable { return $this->warrantyEnd; }
    public function setWarrantyEnd(?\DateTimeImmutable $d): static { $this->warrantyEnd = $d; return $this; }
    public function setManualDocumentRef(?string $r): static { $this->manualDocumentRef = $r; return $this; }
    public function setNotes(?string $n): static { $this->notes = $n; return $this; }

    /** @return array<string, mixed> */
    public function toArray(\DateTimeImmutable $today = new \DateTimeImmutable('today')): array
    {
        return [
            'id' => $this->id->toRfc4122(), 'name' => $this->name, 'placeId' => $this->placeId, 'room' => $this->room,
            'item' => null === $this->item ? null : ['id' => $this->item->getId()->toRfc4122(), 'name' => $this->item->getName()],
            'supplier' => null === $this->supplier ? null : ['id' => $this->supplier->getId()->toRfc4122(), 'name' => $this->supplier->getName()],
            'serial' => $this->serial, 'purchaseDate' => $this->purchaseDate?->format('Y-m-d'), 'warrantyEnd' => $this->warrantyEnd?->format('Y-m-d'),
            'underWarranty' => null === $this->warrantyEnd ? null : $this->warrantyEnd >= $today,
            'manualDocumentRef' => $this->manualDocumentRef, 'notes' => $this->notes,
        ];
    }
}

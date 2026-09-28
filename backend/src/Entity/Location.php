<?php

namespace App\Entity;

use App\Repository\LocationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Where stock is kept: a place (by id, the one of Rocket Place or of a local Site — never a foreign key) and an
 * optional sub-location ("réserve", "cuisine"…; "" = the place as a whole).
 */
#[ORM\Entity(repositoryClass: LocationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_location_place_name', columns: ['place_id', 'name'])]
class Location
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'place_id', length: 36)]
    private string $placeId;

    #[ORM\Column(length: 80)]
    private string $name;

    public function __construct(string $placeId, string $name = '')
    {
        $this->id = Uuid::v7();
        $this->placeId = $placeId;
        $this->name = $name;
    }

    public function getId(): Uuid { return $this->id; }
    public function getPlaceId(): string { return $this->placeId; }
    public function getName(): string { return $this->name; }

    /** @return array{id: string, placeId: string, name: string} */
    public function toArray(): array
    {
        return ['id' => $this->id->toRfc4122(), 'placeId' => $this->placeId, 'name' => $this->name];
    }
}

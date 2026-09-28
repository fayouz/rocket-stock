<?php

namespace App\Stock;

use App\Entity\Item;
use App\Entity\Level;
use App\Entity\Location;
use App\Entity\Movement;
use App\Place\PlaceDirectory;
use App\Repository\LevelRepository;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The stock ledger: locations and levels found or created on demand, movements applied to the levels, and low-stock
 * alerts (StockAlerter) when a level drops to low/empty. Callers flush.
 */
final class StockBook
{
    /** @var array<string, Level> levels created in this request, not flushed yet */
    private array $pending = [];

    public function __construct(
        private readonly LocationRepository $locations,
        private readonly LevelRepository $levels,
        private readonly PlaceDirectory $places,
        private readonly StockAlerter $alerter,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** The location of a place (checked through PlaceDirectory when created), "" = the place as a whole. */
    public function location(string $placeId, ?string $name = null): Location
    {
        $name = mb_substr(trim((string) $name), 0, 80);
        $location = $this->locations->findOneBy(['placeId' => strtolower($placeId), 'name' => $name]);
        if (null === $location) {
            $placeId = $this->places->get($placeId)->getId();
            $this->em->persist($location = new Location($placeId, $name));
            $this->em->flush(); // unique (place, name): visible to the next lookup of this request
        }

        return $location;
    }

    public function level(Location $location, Item $item): Level
    {
        $key = $location->getId()->toRfc4122().'/'.$item->getId()->toRfc4122();
        $level = $this->pending[$key] ?? $this->levels->findOneBy(['location' => $location, 'item' => $item]);
        if (null === $level) {
            $this->em->persist($level = new Level($location, $item));
            $this->pending[$key] = $level;
        }

        return $level;
    }

    /** Sets the quantity and/or the state of a level, alerting when it drops. */
    public function set(Level $level, ?float $quantity, ?string $state): void
    {
        $before = $level->getLevel();
        if (null !== $quantity) {
            $level->setQuantity($quantity);
        }
        if (null !== $state) {
            $level->setLevel($state);
        }
        $this->alerter->changed($level, $before);
    }

    /** Applies a new movement to the levels it touches. */
    public function apply(Movement $m): void
    {
        $level = $this->level($m->getLocation(), $m->getItem());
        $current = $level->getQuantity() ?? 0.0;
        match ($m->getType()) {
            'in' => $this->set($level, $current + $m->getQuantity(), null),
            'out', 'consume' => $this->set($level, $current - $m->getQuantity(), null),
            'adjust' => $this->set($level, $m->getQuantity(), null),
            'transfer' => $this->transfer($m, $level, $current),
            default => throw new HttpException(422, 'Type de mouvement inconnu.'),
        };
    }

    private function transfer(Movement $m, Level $from, float $current): void
    {
        $to = $m->getToLocation() ?? throw new HttpException(422, 'Transfert : « toLocation » ou « toPlaceId » requis.');
        if ($to === $m->getLocation()) {
            throw new HttpException(422, 'Transfert vers le même emplacement.');
        }
        $this->set($from, $current - $m->getQuantity(), null);
        $target = $this->level($to, $m->getItem());
        $this->set($target, ($target->getQuantity() ?? 0.0) + $m->getQuantity(), null);
    }
}

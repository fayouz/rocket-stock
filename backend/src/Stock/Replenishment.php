<?php

namespace App\Stock;

use App\Entity\ItemOffer;
use App\Entity\Level;
use App\Repository\ItemOfferRepository;
use App\Repository\LevelRepository;

/**
 * What to buy: the levels below their threshold (low/empty or a known quantity under it), and for each the quantity
 * to go back to the level's target (targetQuantity, default twice the threshold) rounded up to the pack size of the
 * article's preferred offer (else its cheapest, else packs of 1) — at least one pack.
 */
final class Replenishment
{
    public function __construct(private readonly LevelRepository $levels, private readonly ItemOfferRepository $offers)
    {
    }

    /** @return list<array{level: Level, offer: ?ItemOffer, quantity: float}> */
    public function needs(?string $placeId): array
    {
        $qb = $this->levels->createQueryBuilder('l')->join('l.location', 'loc')->join('l.item', 'i')->addSelect('loc', 'i')
            ->orderBy('i.name')->addOrderBy('loc.placeId')->addOrderBy('loc.name');
        if (null !== $placeId) {
            $qb->andWhere('loc.placeId = :place')->setParameter('place', $placeId);
        }
        $out = [];
        foreach ($qb->getQuery()->getResult() as $level) {
            /** @var Level $level */
            if (!$level->needsRestock()) {
                continue;
            }
            $offer = $this->offerFor($level);
            $pack = $offer?->getPackSize() ?? 1.0;
            $need = max($level->target() - $level->estimatedQuantity(), 0.0);
            $out[] = ['level' => $level, 'offer' => $offer, 'quantity' => max(1.0, ceil(round($need / $pack, 6))) * $pack];
        }

        return $out;
    }

    private function offerFor(Level $level): ?ItemOffer
    {
        $offers = $this->offers->findBy(['item' => $level->getItem()]);
        usort($offers, static fn (ItemOffer $a, ItemOffer $b) => [!$a->isPreferred(), $a->getPrice() ?? \PHP_FLOAT_MAX] <=> [!$b->isPreferred(), $b->getPrice() ?? \PHP_FLOAT_MAX]);

        return $offers[0] ?? null;
    }
}

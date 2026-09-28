<?php

namespace App\Controller;

use App\Place\PlaceDirectory;
use App\Stock\Payload;
use App\Stock\Replenishment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The shopping list, computed on the fly (nothing stored; a ShoppingCart is its stored, tickable form): the levels
 * below their threshold with the quantity to buy (App\Stock\Replenishment), grouped by store, with estimated costs.
 */
#[IsGranted('STOCK_READ')]
final class ShoppingListController extends AbstractController
{
    public function __construct(private readonly Replenishment $replenishment, private readonly PlaceDirectory $places)
    {
    }

    /** Query: place=<IRI|id>, subscription=0 to leave out articles on subscription. */
    #[Route('/api/shopping-list', name: 'api_shopping_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $placeId = $request->query->has('place') ? (Payload::id($request->query->get('place')) ?? '-') : null;
        $withSubscriptions = $request->query->getBoolean('subscription', true);
        $lines = [];
        $groups = [];
        $total = 0.0;
        foreach ($this->replenishment->needs($placeId) as ['level' => $level, 'offer' => $offer, 'quantity' => $qty]) {
            $item = $level->getItem();
            if (!$withSubscriptions && $item->isSubscription()) {
                continue;
            }
            $pack = $offer?->getPackSize() ?? 1.0;
            $cost = null !== $offer?->getPrice() ? round(ceil(round($qty / $pack, 6)) * $offer->getPrice(), 2) : (null === $item->getUnitCost() ? null : round($item->getUnitCost() * $qty, 2));
            $store = $offer?->getSupplier() ?? $item->getSupplier();
            $key = $store?->getId()->toRfc4122() ?? '';
            $groups[$key] ??= ['store' => null === $store ? null : ['id' => $key, 'name' => $store->getName(), 'kind' => $store->getKind()], 'lines' => 0, 'estimatedCost' => 0.0];
            ++$groups[$key]['lines'];
            $groups[$key]['estimatedCost'] = round($groups[$key]['estimatedCost'] + ($cost ?? 0.0), 2);
            $total += $cost ?? 0.0;
            $lines[] = [
                'levelId' => $level->getId()->toRfc4122(),
                'item' => ['id' => $item->getId()->toRfc4122(), 'name' => $item->getName(), 'unit' => $item->getUnit(), 'category' => $item->getCategory(), 'sku' => $item->getSku(), 'asin' => $item->getAsin()],
                'placeId' => $level->getLocation()->getPlaceId(),
                'placeName' => $this->places->cachedName($level->getLocation()->getPlaceId()),
                'location' => $level->getLocation()->getName(),
                'level' => $level->getLevel(), 'quantity' => $level->getQuantity(), 'threshold' => $level->threshold(), 'target' => $level->target(),
                'suggestedQty' => $qty, 'packSize' => $pack, 'subscription' => $item->isSubscription(),
                'store' => $groups[$key]['store'], 'estimatedCost' => $cost,
            ];
        }

        return $this->json([
            'generatedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'lines' => $lines,
            'byStore' => array_values($groups),
            'estimatedTotal' => round($total, 2),
        ]);
    }
}

<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemOffer;
use App\Entity\Supplier;
use App\Repository\ItemOfferRepository;
use App\Repository\ItemRepository;
use App\Stock\Payload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The catalogue, at the paths and with the fields of Rocket Place's /api/stock-items (name, asin, reorderQty,
 * subscription) plus unit, category, sku, reorderThreshold, unitCost, supplier (id or IRI), notes. Writes: STOCK_MANAGE.
 * Query of the list: category=…, q=<name contains>.
 */
#[IsGranted('STOCK_READ')]
final class ItemController extends AbstractController
{
    public function __construct(private readonly ItemRepository $items, private readonly ItemOfferRepository $offers, private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/stock-items', name: 'api_stock_items', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $criteria = [];
        if (\in_array($c = $request->query->get('category'), Item::CATEGORIES, true)) {
            $criteria['category'] = $c;
        }
        $items = $this->items->findBy($criteria, ['name' => 'ASC']);
        $q = mb_strtolower(trim((string) $request->query->get('q', '')));
        if ('' !== $q) {
            $items = array_values(array_filter($items, static fn (Item $i) => str_contains(mb_strtolower($i->getName()), $q)));
        }

        $offers = [];
        foreach ($this->offers->findAll() as $o) {
            $offers[$o->getItem()->getId()->toRfc4122()][] = $o->toArray();
        }

        return $this->json(array_map(static fn (Item $i) => $i->toArray() + ['offers' => $offers[$i->getId()->toRfc4122()] ?? []], $items));
    }

    #[Route('/api/stock-items/{id}/offers', name: 'api_stock_item_offers', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function offers(#[MapEntity] Item $item): JsonResponse
    {
        return $this->json($this->offersOf($item));
    }

    /** JSON list [{"store": id|IRI, "preferred"?: bool, "price"?: number, "packSize"?: number}]: replaces the offers of the article. */
    #[Route('/api/stock-items/{id}/offers', name: 'api_stock_item_offers_update', methods: ['PUT'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('STOCK_MANAGE')]
    public function setOffers(#[MapEntity] Item $item, Request $request): JsonResponse
    {
        $rows = $request->toArray();
        if (!array_is_list($rows)) {
            throw new HttpException(422, 'Liste d’offres attendue.');
        }
        foreach ($this->offers->findBy(['item' => $item]) as $o) {
            $this->em->remove($o);
        }
        $this->em->flush();
        $seen = [];
        foreach ($rows as $row) {
            $p = new Payload(\is_array($row) ? $row : []);
            $raw = $p->raw('store') ?? $p->raw('supplier');
            $store = $this->em->find(Supplier::class, Payload::id(\is_array($raw) ? ($raw['id'] ?? null) : $raw) ?? '-') ?? throw new HttpException(422, 'Magasin inconnu.');
            if (isset($seen[$store->getId()->toRfc4122()])) {
                continue;
            }
            $seen[$store->getId()->toRfc4122()] = true;
            $this->em->persist((new ItemOffer($item, $store))->setPreferred((bool) $p->raw('preferred'))->setPrice($p->float('price'))->setPackSize($p->float('packSize') ?? 1.0));
        }
        $this->em->flush();

        return $this->json($this->offersOf($item));
    }

    /** @return list<array<string, mixed>> */
    private function offersOf(Item $item): array
    {
        return array_map(static fn (ItemOffer $o) => $o->toArray(), $this->offers->findBy(['item' => $item], ['preferred' => 'DESC']));
    }

    #[Route('/api/stock-items/{id}', name: 'api_stock_item', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function show(#[MapEntity] Item $item): JsonResponse
    {
        return $this->json($item->toArray() + ['offers' => $this->offersOf($item)]);
    }

    #[Route('/api/stock-items', name: 'api_stock_item_create', methods: ['POST'])]
    #[IsGranted('STOCK_MANAGE')]
    public function create(Request $request): JsonResponse
    {
        $p = new Payload($request->toArray());
        $item = new Item((string) $p->string('name', 120, true));
        $this->apply($item, $p);
        $this->em->persist($item);
        $this->em->flush();

        return $this->json($item->toArray(), 201);
    }

    #[Route('/api/stock-items/{id}', name: 'api_stock_item_update', methods: ['PATCH', 'PUT'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('STOCK_MANAGE')]
    public function update(#[MapEntity] Item $item, Request $request): JsonResponse
    {
        $p = new Payload($request->toArray());
        if ($p->has('name')) {
            $item->setName((string) $p->string('name', 120, true));
        }
        $this->apply($item, $p);
        $this->em->flush();

        return $this->json($item->toArray());
    }

    #[Route('/api/stock-items/{id}', name: 'api_stock_item_delete', methods: ['DELETE'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('STOCK_MANAGE')]
    public function delete(#[MapEntity] Item $item): Response
    {
        $this->em->remove($item);
        $this->em->flush();

        return new Response(null, 204);
    }

    private function apply(Item $item, Payload $p): void
    {
        if ($p->has('unit')) {
            $item->setUnit($p->string('unit', 24) ?? 'unité');
        }
        if ($p->has('category')) {
            $item->setCategory($p->choice('category', Item::CATEGORIES));
        }
        foreach (['sku' => 64, 'asin' => 32] as $key => $max) {
            if ($p->has($key)) {
                $item->{'set'.ucfirst($key)}($p->string($key, $max));
            }
        }
        if ($p->has('notes')) {
            $item->setNotes($p->string('notes', 2000));
        }
        if ($p->has('reorderThreshold')) {
            $item->setReorderThreshold($p->float('reorderThreshold') ?? 0.0);
        }
        if ($p->has('reorderQty')) {
            $item->setReorderQty((int) ($p->float('reorderQty') ?? 0));
        }
        if ($p->has('subscription')) {
            $item->setSubscription((bool) $p->raw('subscription'));
        }
        if ($p->has('unitCost')) {
            $item->setUnitCost($p->float('unitCost'));
        }
        if ($p->has('supplier')) {
            $raw = $p->raw('supplier');
            $id = Payload::id(\is_array($raw) ? ($raw['id'] ?? null) : $raw);
            $item->setSupplier(null === $raw || '' === $raw ? null : ($this->em->find(Supplier::class, (string) $id) ?? throw new HttpException(422, 'Fournisseur inconnu.')));
        }
    }
}

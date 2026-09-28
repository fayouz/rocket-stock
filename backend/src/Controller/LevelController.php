<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\Level;
use App\Entity\Location;
use App\Repository\LevelRepository;
use App\Repository\LocationRepository;
use App\Stock\Payload;
use App\Stock\StockBook;
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
 * Levels (an article tracked at a location of a place), at the paths and with the fields of Rocket Place's
 * /api/stock-levels ("place" and "item" IRIs, "level" ok|low|empty; PATCH {"level"} unchanged) plus "quantity",
 * "thresholdOverride" and "location" (sub-location name). Any user may set a level (whoever cleans or restocks);
 * sub-locations are managed by STOCK_MANAGE.
 */
#[IsGranted('STOCK_READ')]
final class LevelController extends AbstractController
{
    public function __construct(
        private readonly LevelRepository $levels,
        private readonly LocationRepository $locations,
        private readonly StockBook $book,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** Query: place=<IRI|id>, item=<IRI|id>, location=<location id>, state=ok|low|empty. */
    #[Route('/api/stock-levels', name: 'api_stock_levels', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $placeId = $request->query->has('place') ? (Payload::id($request->query->get('place')) ?? '-') : null;

        return $this->json($this->search($placeId, $request));
    }

    #[Route('/api/places/{placeId}/stock', name: 'api_place_stock', methods: ['GET'], requirements: ['placeId' => Requirement::UUID])]
    public function listForPlace(string $placeId, Request $request): JsonResponse
    {
        return $this->json($this->search(strtolower($placeId), $request));
    }

    #[Route('/api/stock-levels/{id}', name: 'api_stock_level', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function show(#[MapEntity] Level $level): JsonResponse
    {
        return $this->json($level->toArray());
    }

    /**
     * JSON {"place": IRI|id, "item": IRI|id, "location"?: sub-location, "level"?: ok|low|empty, "quantity"?: number,
     * "thresholdOverride"?: number, "targetQuantity"?: number}. Tracking an article already tracked there answers 200 with the (updated) level.
     */
    #[Route('/api/stock-levels', name: 'api_stock_level_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body = $request->toArray();

        return $this->track(Payload::id($body['place'] ?? null) ?? throw new HttpException(422, 'Champ « place » requis.'), new Payload($body));
    }

    /** Same as POST /api/stock-levels, for this place. */
    #[Route('/api/places/{placeId}/stock', name: 'api_place_stock_create', methods: ['POST'], requirements: ['placeId' => Requirement::UUID])]
    public function createForPlace(string $placeId, Request $request): JsonResponse
    {
        return $this->track($placeId, new Payload($request->toArray()));
    }

    /** JSON {"level"?: ok|low|empty, "quantity"?: number|null, "thresholdOverride"?: number|null, "targetQuantity"?: number|null} (merge-patch as Place). */
    #[Route('/api/stock-levels/{id}', name: 'api_stock_level_update', methods: ['PATCH', 'PUT'], requirements: ['id' => Requirement::UUID])]
    public function update(#[MapEntity] Level $level, Request $request): JsonResponse
    {
        $this->apply($level, new Payload($request->toArray()));
        $this->em->flush();

        return $this->json($level->toArray());
    }

    /** The place stops tracking this article there. */
    #[Route('/api/stock-levels/{id}', name: 'api_stock_level_delete', methods: ['DELETE'], requirements: ['id' => Requirement::UUID])]
    public function delete(#[MapEntity] Level $level): Response
    {
        $this->em->remove($level);
        $this->em->flush();

        return new Response(null, 204);
    }

    #[Route('/api/places/{placeId}/locations', name: 'api_place_locations', methods: ['GET'], requirements: ['placeId' => Requirement::UUID])]
    public function locations(string $placeId): JsonResponse
    {
        return $this->json(array_map(static fn (Location $l) => $l->toArray(), $this->locations->findBy(['placeId' => strtolower($placeId)], ['name' => 'ASC'])));
    }

    /** JSON {"name": string}: a sub-location of the place (find-or-create). */
    #[Route('/api/places/{placeId}/locations', name: 'api_place_location_create', methods: ['POST'], requirements: ['placeId' => Requirement::UUID])]
    #[IsGranted('STOCK_MANAGE')]
    public function createLocation(string $placeId, Request $request): JsonResponse
    {
        $location = $this->book->location($placeId, (new Payload($request->toArray()))->string('name', 80, true));
        $this->em->flush();

        return $this->json($location->toArray(), 201);
    }

    /** Deletes a sub-location with its levels and movements. */
    #[Route('/api/locations/{id}', name: 'api_location_delete', methods: ['DELETE'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('STOCK_MANAGE')]
    public function deleteLocation(#[MapEntity] Location $location): Response
    {
        $this->em->remove($location);
        $this->em->flush();

        return new Response(null, 204);
    }

    /** @return list<array<string, mixed>> */
    private function search(?string $placeId, Request $request): array
    {
        $qb = $this->levels->createQueryBuilder('l')->join('l.location', 'loc')->join('l.item', 'i')->addSelect('loc', 'i')
            ->orderBy('loc.placeId')->addOrderBy('loc.name')->addOrderBy('i.name');
        if (null !== $placeId) {
            $qb->andWhere('loc.placeId = :place')->setParameter('place', $placeId);
        }
        if ($request->query->has('item')) {
            $qb->andWhere('i.id = :item')->setParameter('item', Payload::id($request->query->get('item')) ?? '00000000-0000-0000-0000-000000000000');
        }
        if (null !== ($loc = Payload::id($request->query->get('location')))) {
            $qb->andWhere('loc.id = :loc')->setParameter('loc', $loc);
        }
        if (\in_array($state = $request->query->get('state'), Level::LEVELS, true)) {
            $qb->andWhere('l.level = :state')->setParameter('state', $state);
        }

        return array_map(static fn (Level $l) => $l->toArray(), $qb->getQuery()->getResult());
    }

    private function track(string $placeId, Payload $p): JsonResponse
    {
        $itemId = Payload::id($p->raw('item')) ?? throw new HttpException(422, 'Champ « item » requis.');
        $item = $this->em->find(Item::class, $itemId) ?? throw new HttpException(422, 'Article inconnu.');
        $location = $this->book->location($placeId, $p->string('location', 80));
        $existing = $this->levels->findOneBy(['location' => $location, 'item' => $item]);
        $level = $existing ?? $this->book->level($location, $item);
        $this->apply($level, $p);
        $this->em->flush();

        return $this->json($level->toArray(), null === $existing ? 201 : 200);
    }

    private function apply(Level $level, Payload $p): void
    {
        if ($p->has('thresholdOverride')) {
            $level->setThresholdOverride($p->float('thresholdOverride'));
        }
        if ($p->has('targetQuantity')) {
            $level->setTargetQuantity($p->float('targetQuantity'));
        }
        $state = $p->has('level') ? $p->choice('level', Level::LEVELS) : null;
        if ($p->has('quantity') && null === $p->raw('quantity')) {
            $level->setQuantity(null);
        }
        $this->book->set($level, $p->float('quantity'), $state);
    }
}

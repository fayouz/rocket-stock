<?php

namespace App\Controller;

use App\Entity\Equipment;
use App\Entity\Item;
use App\Entity\Supplier;
use App\Place\PlaceDirectory;
use App\Repository\EquipmentRepository;
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
 * Equipment of the places: {"name", "placeId"?, "room"?, "item"?, "supplier"?, "serial"?, "purchaseDate"?,
 * "warrantyEnd"? (YYYY-MM-DD), "manualDocumentRef"? (Rocket Cloud), "notes"?}. Writes: STOCK_MANAGE.
 */
#[IsGranted('STOCK_READ')]
final class EquipmentController extends AbstractController
{
    public function __construct(
        private readonly EquipmentRepository $equipment,
        private readonly PlaceDirectory $places,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** Query: place=<IRI|id>, warranty=expiring (ends within 60 days). */
    #[Route('/api/equipment', name: 'api_equipment', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $criteria = $request->query->has('place') ? ['placeId' => Payload::id($request->query->get('place')) ?? '-'] : [];
        $rows = $this->equipment->findBy($criteria, ['name' => 'ASC']);
        if ('expiring' === $request->query->get('warranty')) {
            $today = new \DateTimeImmutable('today');
            $rows = array_values(array_filter($rows, static fn (Equipment $e) => null !== $e->getWarrantyEnd() && $e->getWarrantyEnd() >= $today && $e->getWarrantyEnd() <= $today->modify('+60 days')));
        }

        return $this->json(array_map(static fn (Equipment $e) => $e->toArray(), $rows));
    }

    #[Route('/api/places/{placeId}/equipment', name: 'api_place_equipment', methods: ['GET'], requirements: ['placeId' => Requirement::UUID])]
    public function listForPlace(string $placeId): JsonResponse
    {
        return $this->json(array_map(static fn (Equipment $e) => $e->toArray(), $this->equipment->findBy(['placeId' => strtolower($placeId)], ['name' => 'ASC'])));
    }

    #[Route('/api/equipment/{id}', name: 'api_equipment_show', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function show(#[MapEntity] Equipment $equipment): JsonResponse
    {
        return $this->json($equipment->toArray());
    }

    #[Route('/api/equipment', name: 'api_equipment_create', methods: ['POST'])]
    #[IsGranted('STOCK_MANAGE')]
    public function create(Request $request): JsonResponse
    {
        $p = new Payload($request->toArray());
        $equipment = new Equipment((string) $p->string('name', 120, true));
        $this->apply($equipment, $p);
        $this->em->persist($equipment);
        $this->em->flush();

        return $this->json($equipment->toArray(), 201);
    }

    #[Route('/api/equipment/{id}', name: 'api_equipment_update', methods: ['PATCH', 'PUT'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('STOCK_MANAGE')]
    public function update(#[MapEntity] Equipment $equipment, Request $request): JsonResponse
    {
        $p = new Payload($request->toArray());
        if ($p->has('name')) {
            $equipment->setName((string) $p->string('name', 120, true));
        }
        $this->apply($equipment, $p);
        $this->em->flush();

        return $this->json($equipment->toArray());
    }

    #[Route('/api/equipment/{id}', name: 'api_equipment_delete', methods: ['DELETE'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('STOCK_MANAGE')]
    public function delete(#[MapEntity] Equipment $equipment): Response
    {
        $this->em->remove($equipment);
        $this->em->flush();

        return new Response(null, 204);
    }

    private function apply(Equipment $e, Payload $p): void
    {
        if ($p->has('placeId') || $p->has('place')) {
            $raw = $p->raw('placeId') ?? $p->raw('place');
            $e->setPlaceId(null === $raw || '' === $raw ? null : $this->places->get(Payload::id($raw) ?? '-')->getId());
        }
        foreach (['room' => 80, 'serial' => 120, 'manualDocumentRef' => 255, 'notes' => 2000] as $key => $max) {
            if ($p->has($key)) {
                $e->{'set'.ucfirst($key)}($p->string($key, $max));
            }
        }
        if ($p->has('purchaseDate')) {
            $e->setPurchaseDate($p->date('purchaseDate'));
        }
        if ($p->has('warrantyEnd')) {
            $e->setWarrantyEnd($p->date('warrantyEnd'));
        }
        foreach (['item' => Item::class, 'supplier' => Supplier::class] as $key => $class) {
            if ($p->has($key)) {
                $raw = $p->raw($key);
                $entity = null === $raw || '' === $raw ? null : ($this->em->find($class, Payload::id(\is_array($raw) ? ($raw['id'] ?? null) : $raw) ?? '-') ?? throw new HttpException(422, \sprintf('« %s » inconnu.', $key)));
                $e->{'set'.ucfirst($key)}($entity);
            }
        }
    }
}

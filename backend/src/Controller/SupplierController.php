<?php

namespace App\Controller;

use App\Entity\Supplier;
use App\Repository\SupplierRepository;
use App\Stock\Payload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Suppliers and stores {"name", "kind"?: supplier|store, "address"?, "lat"?, "lng"?, "openingHours"?, "website"?, "email"?,
 * "phone"?, "notes"?}. Writes: STOCK_MANAGE. /api/stores is the same list restricted to kind "store".
 */
#[IsGranted('STOCK_READ')]
final class SupplierController extends AbstractController
{
    public function __construct(private readonly SupplierRepository $suppliers, private readonly EntityManagerInterface $em)
    {
    }

    /** Query: kind=supplier|store. */
    #[Route('/api/suppliers', name: 'api_suppliers', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $kind = $request->query->get('kind');

        return $this->json(array_map(static fn (Supplier $s) => $s->toArray(), $this->suppliers->findBy(\in_array($kind, Supplier::KINDS, true) ? ['kind' => $kind] : [], ['name' => 'ASC'])));
    }

    #[Route('/api/stores', name: 'api_stores', methods: ['GET'])]
    public function stores(): JsonResponse
    {
        return $this->json(array_map(static fn (Supplier $s) => $s->toArray(), $this->suppliers->findBy(['kind' => 'store'], ['name' => 'ASC'])));
    }

    #[Route('/api/suppliers/{id}', name: 'api_supplier', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function show(#[MapEntity] Supplier $supplier): JsonResponse
    {
        return $this->json($supplier->toArray());
    }

    #[Route('/api/suppliers', name: 'api_supplier_create', methods: ['POST'])]
    #[IsGranted('STOCK_MANAGE')]
    public function create(Request $request): JsonResponse
    {
        $p = new Payload($request->toArray());
        $supplier = new Supplier((string) $p->string('name', 120, true));
        $this->apply($supplier, $p);
        $this->em->persist($supplier);
        $this->em->flush();

        return $this->json($supplier->toArray(), 201);
    }

    #[Route('/api/suppliers/{id}', name: 'api_supplier_update', methods: ['PATCH', 'PUT'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('STOCK_MANAGE')]
    public function update(#[MapEntity] Supplier $supplier, Request $request): JsonResponse
    {
        $p = new Payload($request->toArray());
        if ($p->has('name')) {
            $supplier->setName((string) $p->string('name', 120, true));
        }
        $this->apply($supplier, $p);
        $this->em->flush();

        return $this->json($supplier->toArray());
    }

    #[Route('/api/suppliers/{id}', name: 'api_supplier_delete', methods: ['DELETE'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('STOCK_MANAGE')]
    public function delete(#[MapEntity] Supplier $supplier): Response
    {
        $this->em->remove($supplier);
        $this->em->flush();

        return new Response(null, 204);
    }

    private function apply(Supplier $s, Payload $p): void
    {
        if ($p->has('kind')) {
            $s->setKind($p->choice('kind', Supplier::KINDS));
        }
        foreach (['lat', 'lng'] as $key) {
            if ($p->has($key)) {
                $s->{'set'.ucfirst($key)}($p->float($key));
            }
        }
        foreach (['address' => 255, 'openingHours' => 255, 'website' => 255, 'email' => 180, 'phone' => 40, 'notes' => 2000] as $key => $max) {
            if ($p->has($key)) {
                $s->{'set'.ucfirst($key)}($p->string($key, $max));
            }
        }
    }
}

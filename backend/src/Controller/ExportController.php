<?php

namespace App\Controller;

use App\Entity\Movement;
use App\Place\PlaceDirectory;
use App\Repository\MovementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Exports for the host's bilan. Same filters as GET /api/movements (place, item, type, usage, origin, from, to) and
 * format=json (default) or csv (";"-separated, UTF-8 with BOM, for a spreadsheet).
 * - /api/export/movements: every movement, valued (quantity × unit cost at the time).
 * - /api/export/consumption: per article, what was used up (consume + out), and its cost — with usage=rental, the
 *   consumables of the rental activity.
 */
#[IsGranted('STOCK_READ')]
final class ExportController extends AbstractController
{
    public function __construct(private readonly MovementRepository $movements, private readonly PlaceDirectory $places)
    {
    }

    #[Route('/api/export/movements', name: 'api_export_movements', methods: ['GET'])]
    public function movements(Request $request): Response
    {
        $rows = array_map(fn (Movement $m) => [
            'date' => $m->getOccurredAt()->format('Y-m-d H:i'), 'place' => $this->places->cachedName($m->getPlaceId()), 'placeId' => $m->getPlaceId(),
            'location' => $m->getLocation()->getName(), 'item' => $m->getItem()->getName(), 'category' => $m->getItem()->getCategory(),
            'type' => $m->getType(), 'quantity' => $m->getQuantity(), 'unit' => $m->getItem()->getUnit(), 'unitCost' => $m->getUnitCost(), 'cost' => $m->cost(),
            'usage' => $m->getUsage(), 'origin' => $m->getOrigin(), 'originApp' => $m->getOriginApp(), 'externalRef' => $m->getExternalRef(), 'reason' => $m->getReason(),
        ], $this->query($request));

        return $this->respond($request, $rows, 'mouvements');
    }

    #[Route('/api/export/consumption', name: 'api_export_consumption', methods: ['GET'])]
    public function consumption(Request $request): Response
    {
        $byItem = [];
        foreach ($this->query($request) as $m) {
            if (!$m->isConsumption()) {
                continue;
            }
            $id = $m->getItem()->getId()->toRfc4122();
            $byItem[$id] ??= ['itemId' => $id, 'item' => $m->getItem()->getName(), 'category' => $m->getItem()->getCategory(), 'unit' => $m->getItem()->getUnit(), 'quantity' => 0.0, 'cost' => 0.0, 'unvalued' => 0.0];
            $byItem[$id]['quantity'] = round($byItem[$id]['quantity'] + $m->getQuantity(), 3);
            if (null === $m->cost()) {
                $byItem[$id]['unvalued'] = round($byItem[$id]['unvalued'] + $m->getQuantity(), 3);
            } else {
                $byItem[$id]['cost'] = round($byItem[$id]['cost'] + $m->cost(), 2);
            }
        }
        usort($byItem, static fn (array $a, array $b) => $b['cost'] <=> $a['cost'] ?: strcmp($a['item'], $b['item']));
        if ('csv' === $request->query->get('format')) {
            return $this->respond($request, $byItem, 'consommation');
        }

        return $this->json([
            'usage' => $request->query->get('usage'), 'from' => $request->query->get('from'), 'to' => $request->query->get('to'), 'place' => $request->query->get('place'),
            'items' => $byItem, 'totalCost' => round(array_sum(array_column($byItem, 'cost')), 2),
        ]);
    }

    /** @return list<Movement> */
    private function query(Request $request): array
    {
        return MovementController::filter($this->movements->createQueryBuilder('m'), $request)->join('m.item', 'i')->addSelect('i')
            ->orderBy('m.occurredAt', 'ASC')->getQuery()->getResult();
    }

    /** @param list<array<string, mixed>> $rows */
    private function respond(Request $request, array $rows, string $name): Response
    {
        if ('csv' !== $request->query->get('format')) {
            return new JsonResponse($rows);
        }
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\u{FEFF}");
        if ([] !== $rows) {
            fputcsv($out, array_keys($rows[0]), ';', '"', '');
        }
        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn ($v) => \is_float($v) ? str_replace('.', ',', (string) $v) : $v, $row), ';', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);

        return new Response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => \sprintf('attachment; filename="stock-%s-%s.csv"', $name, date('Y-m-d'))]);
    }
}

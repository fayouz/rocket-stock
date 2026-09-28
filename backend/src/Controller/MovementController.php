<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\Location;
use App\Entity\Movement;
use App\Repository\MovementRepository;
use App\Stock\Payload;
use App\Stock\StockBook;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Rocket\Core\Security\ApplicationUser;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The stock ledger. A movement is never edited nor deleted: a mistake is corrected by another movement (adjust).
 * Recording is open to any user and to client applications acting for themselves (Rocket Clean after a cleaning,
 * a PMS after a stay…), idempotent by "externalRef".
 */
#[IsGranted('STOCK_READ')]
final class MovementController extends AbstractController
{
    public function __construct(
        private readonly MovementRepository $movements,
        private readonly StockBook $book,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** Query: place, item (IRI|id), type, usage, origin, externalRef, from, to (YYYY-MM-DD, inclusive), limit (default 200). Newest first. */
    #[Route('/api/movements', name: 'api_movements', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $qb = self::filter($this->movements->createQueryBuilder('m'), $request)->orderBy('m.occurredAt', 'DESC')
            ->setMaxResults(max(1, min(1000, $request->query->getInt('limit', 200))));

        return $this->json(array_map(static fn (Movement $m) => $m->toArray(), $qb->getQuery()->getResult()));
    }

    #[Route('/api/movements/{id}', name: 'api_movement', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function show(#[MapEntity] Movement $movement): JsonResponse
    {
        return $this->json($movement->toArray());
    }

    /**
     * JSON {"item": IRI|id, "place"|"placeId": IRI|id, "location"?: sub-location, "type": in|out|consume|transfer|adjust,
     * "quantity": number > 0 (adjust: the counted quantity, ≥ 0), "reason"?, "externalRef"?, "origin"?: host|pms|place|clean|stock,
     * "originApp"?, "usage"?: rental|personal (default rental), "unitCost"?, "occurredAt"?: ISO-8601,
     * transfer: "toPlace"?|"toPlaceId"? (default: same place) and "toLocation"?}.
     * Or a JSON list of such objects (all or nothing): the answer is then the list.
     * An already recorded externalRef answers 200 with the existing movement (unchanged, not applied twice); else 201.
     */
    #[Route('/api/movements', name: 'api_movement_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body = $request->toArray();
        $connection = $this->em->getConnection();
        $connection->beginTransaction(); // all or nothing (StockBook flushes new locations on the way)
        try {
            $response = $this->handle($body);
            $connection->commit();

            return $response;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /** @param array<mixed> $body */
    private function handle(array $body): JsonResponse
    {
        if (array_is_list($body)) {
            if ([] === $body || \count($body) > 200) {
                throw new HttpException(422, 'Entre 1 et 200 mouvements par envoi.');
            }
            $out = [];
            $created = false;
            $refs = [];
            foreach ($body as $i => $row) {
                $ref = \is_array($row) && \is_string($row['externalRef'] ?? null) ? trim($row['externalRef']) : '';
                if ('' !== $ref && isset($refs[$ref])) {
                    continue; // the same reference twice in one list: recorded once
                }
                $refs[$ref] = true;
                [$movement, $new] = $this->record(\is_array($row) ? $row : throw new HttpException(422, "Mouvement n°$i invalide."));
                $out[] = $movement;
                $created = $created || $new;
            }
            $this->em->flush();

            return $this->json(array_map(static fn (Movement $m) => $m->toArray(), $out), $created ? 201 : 200);
        }
        [$movement, $new] = $this->record($body);
        $this->em->flush();

        return $this->json($movement->toArray(), $new ? 201 : 200);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: Movement, 1: bool} the movement and whether it is new
     */
    private function record(array $body): array
    {
        $p = new Payload($body);
        $ref = $p->string('externalRef', 120);
        if (null !== $ref && null !== ($existing = $this->movements->findOneBy(['externalRef' => $ref]))) {
            return [$existing, false];
        }
        $item = $this->em->find(Item::class, Payload::id($p->raw('item')) ?? throw new HttpException(422, 'Champ « item » requis.'))
            ?? throw new HttpException(422, 'Article inconnu.');
        $placeId = Payload::id($p->raw('placeId') ?? $p->raw('place')) ?? throw new HttpException(422, 'Champ « placeId » requis.');
        $type = $p->choice('type', Movement::TYPES);
        $quantity = (float) $p->float('quantity', true);
        if ($quantity < 0 || ('adjust' !== $type && 0.0 === $quantity)) {
            throw new HttpException(422, 'Quantité positive attendue.');
        }
        $at = null;
        if (null !== ($raw = $p->string('occurredAt', 40))) {
            try {
                $at = new \DateTimeImmutable($raw);
            } catch (\Exception) {
                throw new HttpException(422, 'Champ « occurredAt » : date ISO-8601 attendue.');
            }
        }
        $location = $this->book->location($placeId, $p->string('location', 80));
        $m = new Movement($item, $location, $type, $quantity, $at ?? new \DateTimeImmutable());
        if ('transfer' === $type) {
            $toPlace = Payload::id($p->raw('toPlaceId') ?? $p->raw('toPlace')) ?? $placeId;
            $m->setToLocation($this->book->location($toPlace, $p->string('toLocation', 80)));
        }
        $user = $this->getUser();
        $m->setReason($p->string('reason', 255))->setExternalRef($ref)
            ->setOrigin($p->choice('origin', Movement::ORIGINS, 'stock'))
            ->setUsage($p->choice('usage', Movement::USAGES, 'rental'))
            ->setOriginApp($user instanceof ApplicationUser ? mb_substr($user->getApplication()->getName(), 0, 120) : $p->string('originApp', 120));
        if ($p->has('unitCost')) {
            $m->setUnitCost($p->float('unitCost'));
        }
        $this->em->persist($m);
        $this->book->apply($m);

        return [$m, true];
    }

    /** Shared by the list and the export. */
    public static function filter(QueryBuilder $qb, Request $request): QueryBuilder
    {
        $q = $request->query;
        if ($q->has('place')) {
            $qb->andWhere('m.placeId = :place')->setParameter('place', Payload::id($q->get('place')) ?? '-');
        }
        if ($q->has('item')) {
            $qb->andWhere('IDENTITY(m.item) = :item')->setParameter('item', Payload::id($q->get('item')) ?? '00000000-0000-0000-0000-000000000000');
        }
        foreach (['type' => Movement::TYPES, 'usage' => Movement::USAGES, 'origin' => Movement::ORIGINS] as $key => $choices) {
            if (\in_array($v = $q->get($key), $choices, true)) {
                $qb->andWhere("m.$key = :$key")->setParameter($key, $v);
            }
        }
        if ('' !== ($ref = (string) $q->get('externalRef', ''))) {
            $qb->andWhere('m.externalRef = :ref')->setParameter('ref', $ref);
        }
        foreach (['from' => '>=', 'to' => '<'] as $key => $op) {
            if ('' !== ($v = (string) $q->get($key, ''))) {
                $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v) ?: throw new HttpException(422, "« $key » : date AAAA-MM-JJ attendue.");
                $qb->andWhere("m.occurredAt $op :$key")->setParameter($key, 'to' === $key ? $d->modify('+1 day') : $d);
            }
        }

        return $qb;
    }
}

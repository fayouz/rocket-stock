<?php

namespace App\Controller;

use App\Entity\CartLine;
use App\Entity\Movement;
use App\Entity\ShoppingCart;
use App\Place\PlaceDirectory;
use App\Repository\MovementRepository;
use App\Repository\ShoppingCartRepository;
use App\Stock\Payload;
use App\Stock\Replenishment;
use App\Stock\StockBook;
use Doctrine\ORM\EntityManagerInterface;
use Rocket\Core\Security\ApplicationUser;
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
 * Shopping carts: built from the levels below their threshold, grouped by preferred store, ticked while shopping,
 * then finished (an "in" movement per ticked line, idempotent). Any user (whoever shops) and client applications
 * (Rocket Host displays them) may use them; deleting is STOCK_MANAGE.
 */
#[IsGranted('STOCK_READ')]
final class ShoppingCartController extends AbstractController
{
    private const FLOW = [ShoppingCart::DRAFT => [ShoppingCart::IN_PROGRESS, ShoppingCart::DONE], ShoppingCart::IN_PROGRESS => [ShoppingCart::DRAFT, ShoppingCart::DONE], ShoppingCart::DONE => []];

    public function __construct(
        private readonly ShoppingCartRepository $carts,
        private readonly MovementRepository $movements,
        private readonly Replenishment $replenishment,
        private readonly StockBook $book,
        private readonly PlaceDirectory $places,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** Query: status=draft|in_progress|done, place=<IRI|id>. Newest first. */
    #[Route('/api/shopping-carts', name: 'api_shopping_carts', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $criteria = [];
        if (\in_array($s = $request->query->get('status'), ShoppingCart::STATUSES, true)) {
            $criteria['status'] = $s;
        }
        if ($request->query->has('place')) {
            $criteria['placeId'] = Payload::id($request->query->get('place')) ?? '-';
        }

        return $this->json(array_map(static fn (ShoppingCart $c) => $c->toArray(), $this->carts->findBy($criteria, ['id' => 'DESC'], 50)));
    }

    /** Builds a cart (201) from what needs restocking, for ?place=<IRI|id> or every place; 422 when nothing is needed. */
    #[Route('/api/shopping-carts', name: 'api_shopping_cart_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $placeId = null;
        if ($request->query->has('place') || '' !== $request->getContent()) {
            $raw = $request->query->get('place') ?? ('' !== $request->getContent() ? ($request->toArray()['place'] ?? $request->toArray()['placeId'] ?? null) : null);
            $placeId = null === $raw || '' === $raw ? null : $this->places->get(Payload::id($raw) ?? '-')->getId();
        }
        $cart = new ShoppingCart($placeId);
        foreach ($this->replenishment->needs($placeId) as $i => ['level' => $level, 'offer' => $offer, 'quantity' => $qty]) {
            $line = (new CartLine($cart, $level->getItem(), $level->getLocation(), $qty, $i))
                ->setStore($offer?->getSupplier() ?? $level->getItem()->getSupplier())
                ->setPackSize($offer?->getPackSize() ?? 1.0)->setPackPrice($offer?->getPrice())
                ->setAsin($offer?->getAsin() ?? $level->getItem()->getAsin())->setProductUrl($offer?->getProductUrl());
            $cart->addLine($line);
        }
        if (0 === $cart->getLines()->count()) {
            throw new HttpException(422, 'Rien à acheter : aucun stock sous son seuil.');
        }
        $this->em->persist($cart);
        $this->em->flush();

        return $this->json($cart->toArray(), 201);
    }

    #[Route('/api/shopping-carts/{id}', name: 'api_shopping_cart', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function show(#[MapEntity] ShoppingCart $cart): JsonResponse
    {
        return $this->json($cart->toArray());
    }

    /** The cart as a plain text list, to share (message, notes…). */
    #[Route('/api/shopping-carts/{id}/text', name: 'api_shopping_cart_text', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function text(#[MapEntity] ShoppingCart $cart): Response
    {
        $data = $cart->toArray();
        $out = ['Courses du '.$cart->getCreatedAt()?->format('d/m/Y')];
        foreach ($data['stores'] as $group) {
            $out[] = '';
            $out[] = '== '.($group['store']['name'] ?? 'Sans magasin').(0.0 < $group['estimatedTotal'] ? \sprintf(' (≈ %s €)', number_format($group['estimatedTotal'], 2, ',', ' ')) : '').' ==';
            foreach ($group['lines'] as $line) {
                $out[] = \sprintf('%s %s %s %s%s%s', $line['checked'] ? '[x]' : '[ ]', self::num($line['quantity']), $line['item']['unit'], $line['item']['name'],
                    $line['packSize'] > 1 ? \sprintf(' (%s × %s)', self::num($line['packs']), self::num($line['packSize'])) : '',
                    null !== $line['item']['ean'] ? ' — EAN '.$line['item']['ean'] : '');
            }
        }
        if (0.0 < $data['estimatedTotal']) {
            $out[] = '';
            $out[] = \sprintf('Total estimé : %s €', number_format($data['estimatedTotal'], 2, ',', ' '));
        }

        return new Response(implode("\n", $out)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * JSON {"status": draft|in_progress|done}. "done" records an "in" movement per ticked line into its location
     * (externalRef "cart:<cart id>:<line id>", origin stock, usage rental unless "usage": personal is given).
     */
    #[Route('/api/shopping-carts/{id}', name: 'api_shopping_cart_update', methods: ['PATCH'], requirements: ['id' => Requirement::UUID])]
    public function update(#[MapEntity] ShoppingCart $cart, Request $request): JsonResponse
    {
        $p = new Payload($request->toArray());
        $status = $p->choice('status', ShoppingCart::STATUSES);
        if ($status !== $cart->getStatus()) {
            if (!\in_array($status, self::FLOW[$cart->getStatus()], true)) {
                throw new HttpException(409, 'Ce panier est terminé.');
            }
            if (ShoppingCart::DONE === $status) {
                $this->finish($cart, $p->choice('usage', Movement::USAGES, 'rental'));
            }
            $cart->setStatus($status);
        }
        $this->em->flush();

        return $this->json($cart->toArray());
    }

    /** JSON {"checked"?: bool, "quantity"?: number}: ticks a line while shopping (moves a draft to in_progress). */
    #[Route('/api/shopping-carts/{id}/lines/{lineId}', name: 'api_shopping_cart_line', methods: ['PATCH'], requirements: ['id' => Requirement::UUID, 'lineId' => Requirement::UUID])]
    public function line(#[MapEntity] ShoppingCart $cart, string $lineId, Request $request): JsonResponse
    {
        if (ShoppingCart::DONE === $cart->getStatus()) {
            throw new HttpException(409, 'Ce panier est terminé.');
        }
        $line = $cart->getLines()->findFirst(static fn (int $k, CartLine $l) => $l->getId()->toRfc4122() === strtolower($lineId)) ?? throw new HttpException(404, 'Ligne inconnue.');
        $p = new Payload($request->toArray());
        if ($p->has('checked')) {
            $line->setChecked((bool) $p->raw('checked'));
        }
        if ($p->has('quantity')) {
            $line->setQuantity((float) $p->float('quantity', true));
        }
        if (ShoppingCart::DRAFT === $cart->getStatus()) {
            $cart->setStatus(ShoppingCart::IN_PROGRESS);
        }
        $this->em->flush();

        return $this->json($cart->toArray());
    }

    #[Route('/api/shopping-carts/{id}', name: 'api_shopping_cart_delete', methods: ['DELETE'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('STOCK_MANAGE')]
    public function delete(#[MapEntity] ShoppingCart $cart): Response
    {
        $this->em->remove($cart);
        $this->em->flush();

        return new Response(null, 204);
    }

    private function finish(ShoppingCart $cart, string $usage): void
    {
        $user = $this->getUser();
        foreach ($cart->getLines() as $line) {
            $ref = 'cart:'.$cart->getId()->toRfc4122().':'.$line->getId()->toRfc4122();
            if (!$line->isChecked() || $line->getQuantity() <= 0 || null !== $this->movements->findOneBy(['externalRef' => $ref])) {
                continue;
            }
            $m = (new Movement($line->getItem(), $line->getLocation(), 'in', $line->getQuantity()))
                ->setExternalRef($ref)->setReason('Courses')->setOrigin('stock')->setUsage($usage)
                ->setOriginApp($user instanceof ApplicationUser ? mb_substr($user->getApplication()->getName(), 0, 120) : null);
            $cost = $line->getEstimatedCost();
            if (null !== $cost) {
                $m->setUnitCost(round($cost / $line->getQuantity(), 4));
            }
            $this->em->persist($m);
            $this->book->apply($m);
        }
        $cart->setCompletedAt(new \DateTimeImmutable());
    }

    private static function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, ',', ''), '0'), ',');
    }
}

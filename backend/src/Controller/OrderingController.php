<?php

namespace App\Controller;

use App\Entity\PurchaseOrder;
use App\Entity\ShoppingCart;
use App\Entity\Supplier;
use App\Mailer\MailerClient;
use App\Ordering\PurchaseOrderBuilder;
use App\Ordering\StoreConnectorInterface;
use App\Repository\PurchaseOrderRepository;
use App\Stock\Payload;
use Doctrine\ORM\EntityManagerInterface;
use Rocket\Core\Security\ApplicationUser;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Ordering from a cart. Store carts: links that open the store's own cart pre-filled (Amazon), the user checks out
 * and pays there themselves; nothing is ever bought automatically. Purchase orders: an e-mail to a supplier's
 * orderEmail through Rocket Mailer, previewed first, sent only on POST {"confirm": true} by a person (STOCK_MANAGE),
 * once per cart and supplier (Idempotency-Key "po:<cart id>:<supplier id>").
 */
#[IsGranted('STOCK_READ')]
final class OrderingController extends AbstractController
{
    /** @param iterable<StoreConnectorInterface> $connectors */
    public function __construct(
        #[AutowireIterator(StoreConnectorInterface::TAG)] private readonly iterable $connectors,
        private readonly PurchaseOrderBuilder $builder,
        private readonly PurchaseOrderRepository $orders,
        private readonly MailerClient $mailer,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** [{store, urls: [...], lineCount, skipped: [article names without a product id]}] for the stores with a connector. */
    #[Route('/api/shopping-carts/{id}/store-carts', name: 'api_shopping_cart_store_carts', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function storeCarts(#[MapEntity] ShoppingCart $cart): JsonResponse
    {
        $byStore = [];
        foreach ($cart->getLines() as $line) {
            if (null !== $store = $line->getStore()) {
                $byStore[$store->getId()->toRfc4122()]['store'] = $store;
                $byStore[$store->getId()->toRfc4122()]['lines'][] = $line;
            }
        }
        $out = [];
        foreach ($byStore as ['store' => $store, 'lines' => $lines]) {
            foreach ($this->connectors as $connector) {
                if ($connector->supports($store)) {
                    $built = $connector->buildCart($store, $lines);
                    $out[] = ['store' => ['id' => $store->getId()->toRfc4122(), 'name' => $store->getName()], 'urls' => $built['urls'], 'lineCount' => \count($built['lines']),
                        'skipped' => array_map(static fn ($l) => $l->getItem()->getName(), $built['skipped'])];
                    break;
                }
            }
        }

        return $this->json($out);
    }

    #[Route('/api/shopping-carts/{id}/purchase-orders', name: 'api_shopping_cart_purchase_orders', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function list(#[MapEntity] ShoppingCart $cart): JsonResponse
    {
        return $this->json(array_map(static fn (PurchaseOrder $o) => $o->toArray(), $this->orders->findBy(['cart' => $cart], ['sentAt' => 'ASC'])));
    }

    /** What would be sent (nothing is sent): {to, subject, text, htmlBody, lines, estimatedTotal, alreadySent}. */
    #[Route('/api/shopping-carts/{id}/purchase-orders/{supplierId}/preview', name: 'api_shopping_cart_purchase_order_preview', methods: ['GET'], requirements: ['id' => Requirement::UUID, 'supplierId' => Requirement::UUID])]
    public function preview(#[MapEntity] ShoppingCart $cart, string $supplierId): JsonResponse
    {
        $supplier = $this->supplier($cart, $supplierId);

        return $this->json($this->builder->build($cart, $supplier, $this->sender()) + ['alreadySent' => $this->orders->findOneBy(['cart' => $cart, 'supplier' => $supplier])?->toArray()]);
    }

    /** JSON {"confirm": true}: e-mails the purchase order (201), or returns the one already sent (200). */
    #[Route('/api/shopping-carts/{id}/purchase-orders/{supplierId}', name: 'api_shopping_cart_purchase_order_send', methods: ['POST'], requirements: ['id' => Requirement::UUID, 'supplierId' => Requirement::UUID])]
    #[IsGranted('STOCK_MANAGE')]
    public function send(#[MapEntity] ShoppingCart $cart, string $supplierId, Request $request): JsonResponse
    {
        $supplier = $this->supplier($cart, $supplierId);
        if (true !== ('' === $request->getContent() ? null : (new Payload($request->toArray()))->raw('confirm'))) {
            throw new HttpException(422, 'Confirmation requise : relisez l’aperçu puis envoyez avec "confirm": true.');
        }
        if (null !== $existing = $this->orders->findOneBy(['cart' => $cart, 'supplier' => $supplier])) {
            return $this->json($existing->toArray());
        }
        $user = $this->getUser();
        if (null === $user || $user instanceof ApplicationUser) {
            throw new HttpException(403, 'Un bon de commande est envoyé par une personne, pas par une application.');
        }
        $as = $user->getUserIdentifier();
        $email = $this->builder->build($cart, $supplier, $as);
        $sent = $this->mailer->send($as, ['to' => $email['to'], 'subject' => $email['subject'], 'htmlBody' => $email['htmlBody']],
            'po:'.$cart->getId()->toRfc4122().':'.$supplier->getId()->toRfc4122());
        $order = new PurchaseOrder($cart, $supplier, $email['to'][0], $as, \count($email['lines']), $this->mailer->isDemo() ? 'demo' : 'queued', isset($sent['id']) ? mb_substr((string) $sent['id'], 0, 64) : null);
        $this->em->persist($order);
        $this->em->flush();

        return $this->json($order->toArray(), 201);
    }

    private function supplier(ShoppingCart $cart, string $supplierId): Supplier
    {
        $supplier = $this->em->find(Supplier::class, strtolower($supplierId)) ?? throw new HttpException(404, 'Fournisseur inconnu.');
        if (null === $supplier->getOrderEmail()) {
            throw new HttpException(422, 'Ce fournisseur n’a pas d’adresse de commande (orderEmail).');
        }
        if ([] === $this->builder->lines($cart, $supplier)) {
            throw new HttpException(422, 'Aucune ligne de ce panier chez ce fournisseur.');
        }

        return $supplier;
    }

    private function sender(): string
    {
        $user = $this->getUser();

        return null === $user || $user instanceof ApplicationUser ? 'Rocket Stock' : $user->getUserIdentifier();
    }
}

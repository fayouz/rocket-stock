<?php

namespace App\Entity;

use App\Repository\PurchaseOrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A purchase order e-mailed to a supplier for the lines of a cart at that supplier, through Rocket Mailer, only after
 * an explicit confirmation (one per cart and supplier: Idempotency-Key "po:<cart id>:<supplier id>").
 * status: "queued" (accepted by Rocket Mailer) or "demo" (DemoMailer, nothing left the server).
 */
#[ORM\Entity(repositoryClass: PurchaseOrderRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_purchase_order_cart_supplier', columns: ['cart_id', 'supplier_id'])]
class PurchaseOrder
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ShoppingCart $cart;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Supplier $supplier;

    #[ORM\Column(length: 180)]
    private string $recipient;

    #[ORM\Column(length: 180)]
    private string $sentBy;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $messageId = null;

    #[ORM\Column(length: 16)]
    private string $status;

    #[ORM\Column]
    private int $lineCount;

    public function __construct(ShoppingCart $cart, Supplier $supplier, string $recipient, string $sentBy, int $lineCount, string $status, ?string $messageId)
    {
        $this->id = Uuid::v7();
        $this->cart = $cart;
        $this->supplier = $supplier;
        $this->recipient = $recipient;
        $this->sentBy = $sentBy;
        $this->lineCount = $lineCount;
        $this->status = $status;
        $this->messageId = $messageId;
        $this->sentAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getCart(): ShoppingCart { return $this->cart; }
    public function getSupplier(): Supplier { return $this->supplier; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(), 'cartId' => $this->cart->getId()->toRfc4122(),
            'supplier' => ['id' => $this->supplier->getId()->toRfc4122(), 'name' => $this->supplier->getName()],
            'recipient' => $this->recipient, 'sentBy' => $this->sentBy, 'sentAt' => $this->sentAt->format(\DATE_ATOM),
            'messageId' => $this->messageId, 'status' => $this->status, 'lineCount' => $this->lineCount,
        ];
    }
}

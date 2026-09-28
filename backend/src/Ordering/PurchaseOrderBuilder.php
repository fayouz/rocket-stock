<?php

namespace App\Ordering;

use App\Entity\CartLine;
use App\Entity\ShoppingCart;
use App\Entity\Supplier;
use App\Place\PlaceDirectory;

/** The purchase order of a cart's lines at one supplier: subject, plain text and an HTML table (no PDF). */
final class PurchaseOrderBuilder
{
    public function __construct(private readonly PlaceDirectory $places)
    {
    }

    /** @return list<CartLine> */
    public function lines(ShoppingCart $cart, Supplier $supplier): array
    {
        return array_values(array_filter($cart->getLines()->toArray(), static fn (CartLine $l) => $l->getStore()?->getId()->equals($supplier->getId()) && $l->getQuantity() > 0));
    }

    /** @return array{to: list<string>, subject: string, text: string, htmlBody: string, lines: list<array<string, mixed>>, estimatedTotal: float} */
    public function build(ShoppingCart $cart, Supplier $supplier, string $from): array
    {
        $lines = $this->lines($cart, $supplier);
        $ref = 'BC-'.strtoupper(substr(str_replace('-', '', $cart->getId()->toRfc4122()), -8));
        $place = null === $cart->getPlaceId() ? null : $this->places->cachedName($cart->getPlaceId());
        $subject = \sprintf('Bon de commande %s%s', $ref, null === $place ? '' : ' — '.$place);
        $rows = [];
        $total = 0.0;
        foreach ($lines as $l) {
            $cost = $l->getEstimatedCost();
            $total += $cost ?? 0.0;
            $rows[] = [
                'name' => $l->getItem()->getName(), 'ean' => $l->getItem()->getEan(), 'reference' => $l->getItem()->getSku(),
                'quantity' => $l->getQuantity(), 'unit' => $l->getItem()->getUnit(), 'packs' => $l->packs(), 'packSize' => $l->getPackSize(),
                'packPrice' => $l->getPackPrice(), 'estimatedCost' => $cost,
            ];
        }
        $total = round($total, 2);

        $text = ["Bonjour,", '', \sprintf('Merci de bien vouloir nous livrer la commande %s%s :', $ref, null === $place ? '' : ' (pour '.$place.')'), ''];
        foreach ($rows as $r) {
            $text[] = \sprintf('- %s %s %s%s%s%s', self::num($r['quantity']), $r['unit'], $r['name'],
                $r['packSize'] > 1 ? \sprintf(' (%s × %s)', self::num($r['packs']), self::num($r['packSize'])) : '',
                null !== $r['ean'] ? ' — EAN '.$r['ean'] : '', null !== $r['reference'] ? ' — réf. '.$r['reference'] : '');
        }
        if ($total > 0) {
            $text[] = '';
            $text[] = 'Total estimé : '.self::euro($total);
        }
        $text = array_merge($text, ['', 'Merci de confirmer la commande et le délai de livraison en répondant à ce message.', '', 'Cordialement,', $from]);

        $tr = '';
        foreach ($rows as $r) {
            $tr .= \sprintf('<tr><td>%s</td><td>%s</td><td>%s</td><td style="text-align:right">%s %s</td><td style="text-align:right">%s</td><td style="text-align:right">%s</td></tr>',
                self::h($r['name']), self::h($r['ean'] ?? ''), self::h($r['reference'] ?? ''), self::num($r['quantity']), self::h($r['unit']),
                $r['packSize'] > 1 ? self::num($r['packs']).' × '.self::num($r['packSize']) : '', null === $r['estimatedCost'] ? '' : self::euro($r['estimatedCost']));
        }
        $html = '<p>Bonjour,</p><p>Merci de bien vouloir nous livrer la commande <b>'.$ref.'</b>'.(null === $place ? '' : ' (pour '.self::h($place).')').' :</p>'
            .'<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse"><thead><tr><th>Article</th><th>EAN</th><th>Réf.</th><th>Quantité</th><th>Paquets</th><th>Estimé</th></tr></thead><tbody>'.$tr.'</tbody>'
            .($total > 0 ? '<tfoot><tr><td colspan="5" style="text-align:right"><b>Total estimé</b></td><td style="text-align:right"><b>'.self::euro($total).'</b></td></tr></tfoot>' : '')
            .'</table><p>Merci de confirmer la commande et le délai de livraison en répondant à ce message.</p><p>Cordialement,<br>'.self::h($from).'</p>';

        return ['to' => null === $supplier->getOrderEmail() ? [] : [$supplier->getOrderEmail()], 'subject' => $subject, 'text' => implode("\n", $text)."\n", 'htmlBody' => $html, 'lines' => $rows, 'estimatedTotal' => $total];
    }

    private static function h(string $s): string
    {
        return htmlspecialchars($s, \ENT_QUOTES);
    }

    private static function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, ',', ''), '0'), ',');
    }

    private static function euro(float $n): string
    {
        return number_format($n, 2, ',', ' ').' €';
    }
}

<?php

namespace App\Tests\Unit;

use App\Entity\CartLine;
use App\Entity\Item;
use App\Entity\Location;
use App\Entity\ShoppingCart;
use App\Entity\Supplier;
use App\Ordering\AmazonConnector;
use App\Ordering\Ean;
use PHPUnit\Framework\TestCase;

/** EAN check digits and the Amazon pre-filled cart links (no network). */
final class OrderingTest extends TestCase
{
    public function testEan(): void
    {
        self::assertTrue(Ean::isValid('4006381333931'));
        self::assertTrue(Ean::isValid('96385074'));
        self::assertFalse(Ean::isValid('4006381333932'));
        self::assertFalse(Ean::isValid('12345'));
        self::assertSame('4006381333931', Ean::normalize(' 400638 133393-1 '));
        self::assertSame(1, Ean::checkDigit('400638133393'));
    }

    public function testAmazonCartSplitsBy50(): void
    {
        $store = (new Supplier('Amazon'))->setKind('store')->setAmazon(true)->setAmazonDomain('amazon.de');
        $cart = new ShoppingCart(null);
        $location = new Location('0192f7c4-0000-7000-8000-000000000001', '');
        $lines = [];
        for ($i = 0; $i < 52; ++$i) {
            $lines[] = (new CartLine($cart, new Item("A$i"), $location, 3, $i))->setStore($store)->setPackSize(2)->setAsin(\sprintf('B0%08d', $i));
        }
        $lines[] = (new CartLine($cart, new Item('Sans ASIN'), $location, 1, 99))->setStore($store);

        $connector = new AmazonConnector('rocket-21');
        self::assertTrue($connector->supports($store));
        self::assertFalse($connector->supports(new Supplier('Carrefour')));
        $built = $connector->buildCart($store, $lines);
        self::assertCount(2, $built['urls']);
        self::assertCount(1, $built['skipped']);
        self::assertStringStartsWith('https://www.amazon.de/gp/aws/cart/add.html?ASIN.1=B000000000&Quantity.1=2&', $built['urls'][0]);
        self::assertStringContainsString('ASIN.50=B000000049', $built['urls'][0]);
        self::assertStringNotContainsString('ASIN.51', $built['urls'][0]);
        self::assertStringContainsString('ASIN.2=B000000051', $built['urls'][1]);
        self::assertStringEndsWith('AssociateTag=rocket-21', $built['urls'][1]);
        self::assertStringNotContainsString('AssociateTag', (new AmazonConnector())->buildCart($store, $lines)['urls'][0]);
    }
}

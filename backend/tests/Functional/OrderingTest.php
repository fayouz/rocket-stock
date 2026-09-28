<?php

namespace App\Tests\Functional;

use App\Mailer\DemoMailer;
use App\Tests\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Barcodes and store links, Amazon cart links, purchase orders (preview, confirmation, DemoMailer only). */
final class OrderingTest extends WebTestCase
{
    use ApiTestTrait;

    public function testOrdering(): void
    {
        static::getContainer()->get(DemoMailer::class)->reset();
        $admin = 'Bearer '.$this->jwtFor($this->createUser('admin@example.org', ['ROLE_ADMIN']));
        $alice = 'Bearer '.$this->jwtFor($this->createUser('alice@example.org'));
        $place = $this->api('POST', '/api/places', ['name' => 'Le port'], $admin)['id'];

        $this->api('POST', '/api/stock-items', ['name' => 'Faux', 'ean' => '4006381333932'], $admin);
        $this->assertStatus(422);
        $paper = $this->api('POST', '/api/stock-items', ['name' => 'Papier toilette', 'reorderThreshold' => 6, 'ean' => '4006381 333931'], $admin);
        self::assertSame('4006381333931', $paper['ean']);
        $coffee = $this->api('POST', '/api/stock-items', ['name' => 'Café', 'reorderThreshold' => 10], $admin)['id'];
        $soap = $this->api('POST', '/api/stock-items', ['name' => 'Savon', 'reorderThreshold' => 2, 'ean' => '96385074'], $admin)['id'];

        $this->api('POST', '/api/suppliers', ['name' => 'X', 'searchUrlTemplate' => 'javascript:alert(1)'], $admin);
        $this->assertStatus(422);
        $this->api('POST', '/api/suppliers', ['name' => 'X', 'amazon' => true, 'amazonDomain' => 'evil.com'], $admin);
        $this->assertStatus(422);
        $carrefour = $this->api('POST', '/api/suppliers', ['name' => 'Carrefour', 'kind' => 'store', 'searchUrlTemplate' => 'https://www.carrefour.fr/s?q={ean}'], $admin)['id'];
        $amazon = $this->api('POST', '/api/suppliers', ['name' => 'Amazon', 'kind' => 'store', 'amazon' => true], $admin);
        self::assertSame('amazon.fr', $amazon['amazonDomain']);
        $wholesaler = $this->api('POST', '/api/suppliers', ['name' => 'Grossiste', 'orderEmail' => 'commandes@grossiste.example'], $admin)['id'];

        $this->api('PUT', "/api/stock-items/{$paper['id']}/offers", [['store' => $carrefour, 'preferred' => true, 'price' => 5.4, 'packSize' => 12]], $admin);
        $this->api('PUT', "/api/stock-items/$coffee/offers", [['store' => $amazon['id'], 'preferred' => true, 'price' => 11.9, 'packSize' => 40, 'asin' => 'b07abc1234']], $admin);
        $this->api('PUT', "/api/stock-items/$soap/offers", [['store' => $wholesaler, 'preferred' => true, 'price' => 2]], $admin);
        foreach ([$paper['id'] => 4, $coffee => 2, $soap => 0] as $item => $qty) {
            $this->api('POST', "/api/places/$place/stock", ['item' => $item, 'quantity' => $qty], $alice);
        }

        $cart = $this->api('POST', "/api/shopping-carts?place=$place", null, $alice);
        $stores = array_column(array_map(static fn ($g) => $g + ['name' => $g['store']['name']], $cart['stores']), null, 'name');
        $paperLine = $stores['Carrefour']['lines'][0];
        self::assertSame('4006381333931', $paperLine['item']['ean']);
        self::assertSame('https://www.carrefour.fr/s?q=4006381333931', $paperLine['productUrl']);
        self::assertTrue($stores['Amazon']['store']['amazon']);
        self::assertSame('B07ABC1234', $stores['Amazon']['lines'][0]['asin']);
        self::assertTrue($stores['Grossiste']['store']['orderEmail']);

        $links = $this->api('GET', "/api/shopping-carts/{$cart['id']}/store-carts", null, $alice);
        self::assertCount(1, $links);
        self::assertSame(['https://www.amazon.fr/gp/aws/cart/add.html?ASIN.1=B07ABC1234&Quantity.1=1'], $links[0]['urls']);

        // purchase order: preview, then only an explicit confirmation sends (DemoMailer: nothing leaves)
        $this->api('GET', "/api/shopping-carts/{$cart['id']}/purchase-orders/$carrefour/preview", null, $alice);
        $this->assertStatus(422);
        $preview = $this->api('GET', "/api/shopping-carts/{$cart['id']}/purchase-orders/$wholesaler/preview", null, $alice);
        self::assertSame(['commandes@grossiste.example'], $preview['to']);
        self::assertStringContainsString('Savon', $preview['text']);
        self::assertStringContainsString('EAN 96385074', $preview['text']);
        self::assertStringContainsString('<table', $preview['htmlBody']);
        self::assertNull($preview['alreadySent']);
        self::assertSame([], static::getContainer()->get(DemoMailer::class)->sent());

        $this->api('POST', "/api/shopping-carts/{$cart['id']}/purchase-orders/$wholesaler", ['confirm' => true], $alice);
        $this->assertStatus(403);
        $this->api('POST', "/api/shopping-carts/{$cart['id']}/purchase-orders/$wholesaler", [], $admin);
        $this->assertStatus(422);
        $order = $this->api('POST', "/api/shopping-carts/{$cart['id']}/purchase-orders/$wholesaler", ['confirm' => true], $admin);
        $this->assertStatus(201);
        self::assertSame('demo', $order['status']);
        self::assertSame('admin@example.org', $order['sentBy']);
        $again = $this->api('POST', "/api/shopping-carts/{$cart['id']}/purchase-orders/$wholesaler", ['confirm' => true], $admin);
        $this->assertStatus(200);
        self::assertSame($order['id'], $again['id']);
        $sent = static::getContainer()->get(DemoMailer::class)->sent();
        self::assertCount(1, $sent);
        self::assertSame('po:'.$cart['id'].':'.$wholesaler, $sent[0]['idempotencyKey']);
        self::assertSame('admin@example.org', $sent[0]['as']);
        self::assertCount(1, $this->api('GET', "/api/shopping-carts/{$cart['id']}/purchase-orders", null, $alice));
        static::getContainer()->get(DemoMailer::class)->reset();
    }
}

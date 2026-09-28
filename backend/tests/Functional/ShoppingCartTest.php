<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Stores, offers, shopping list and carts: build, tick while shopping, finish into "in" movements (idempotent). */
final class ShoppingCartTest extends WebTestCase
{
    use ApiTestTrait;

    public function testCartFromLowLevelsGroupedByStore(): void
    {
        $admin = 'Bearer '.$this->jwtFor($this->createUser('admin@example.org', ['ROLE_ADMIN']));
        $alice = 'Bearer '.$this->jwtFor($this->createUser('alice@example.org'));
        $place = $this->api('POST', '/api/places', ['name' => 'Le port'], $admin)['id'];
        $store = $this->api('POST', '/api/suppliers', ['name' => 'Carrefour', 'kind' => 'store', 'address' => '1 quai', 'lat' => 43.4, 'lng' => 3.69, 'openingHours' => '8h-20h'], $admin);
        self::assertSame('store', $store['kind']);
        self::assertCount(1, $this->api('GET', '/api/stores', null, $alice));

        $paper = $this->api('POST', '/api/stock-items', ['name' => 'Papier toilette', 'reorderThreshold' => 6], $admin)['id'];
        $soap = $this->api('POST', '/api/stock-items', ['name' => 'Savon', 'reorderThreshold' => 1, 'unitCost' => 2], $admin)['id'];
        $coffee = $this->api('POST', '/api/stock-items', ['name' => 'Café', 'reorderThreshold' => 10], $admin)['id'];
        $offers = $this->api('PUT', "/api/stock-items/$paper/offers", [['store' => $store['id'], 'preferred' => true, 'price' => 5.4, 'packSize' => 12]], $admin);
        self::assertCount(1, $offers);
        self::assertCount(1, array_column($this->api('GET', '/api/stock-items', null, $alice), null, 'name')['Papier toilette']['offers']);
        $this->api('POST', "/api/places/$place/stock", ['item' => $paper, 'quantity' => 4], $alice);      // low: target 12 → need 8 → 1 pack of 12
        $this->api('POST', "/api/places/$place/stock", ['item' => $soap, 'level' => 'empty'], $alice);    // empty: target 2 → 2
        $this->api('POST', "/api/places/$place/stock", ['item' => $coffee, 'quantity' => 30], $alice);    // ok: not bought

        $list = $this->api('GET', "/api/shopping-list?place=$place", null, $alice);
        self::assertCount(2, $list['lines']);

        $cart = $this->api('POST', "/api/shopping-carts?place=$place", null, $alice);
        $this->assertStatus(201);
        self::assertSame('draft', $cart['status']);
        self::assertSame(2, $cart['lineCount']);
        self::assertSame('Carrefour', $cart['stores'][0]['store']['name']);
        $paperLine = $cart['stores'][0]['lines'][0];
        self::assertEqualsWithDelta(12, $paperLine['quantity'], 0.001, 'rounded up to the pack size');
        self::assertEqualsWithDelta(5.4, $cart['stores'][0]['estimatedTotal'], 0.001);
        self::assertNull($cart['stores'][1]['store']);
        self::assertEqualsWithDelta(2, $cart['stores'][1]['lines'][0]['quantity'], 0.001);
        self::assertEqualsWithDelta(9.4, $cart['estimatedTotal'], 0.001);

        $cart = $this->api('PATCH', "/api/shopping-carts/{$cart['id']}/lines/{$paperLine['id']}", ['checked' => true], $alice);
        self::assertSame('in_progress', $cart['status']);
        $this->client->request('GET', "/api/shopping-carts/{$cart['id']}/text", server: ['HTTP_AUTHORIZATION' => $alice]);
        self::assertStringContainsString('[x] 12 unité Papier toilette', (string) $this->client->getResponse()->getContent());

        $done = $this->api('PATCH', "/api/shopping-carts/{$cart['id']}", ['status' => 'done'], $alice);
        self::assertSame('done', $done['status']);
        $stock = array_column($this->api('GET', "/api/places/$place/stock", null, $alice), null, 'name');
        self::assertEqualsWithDelta(16, $stock['Papier toilette']['quantity'], 0.001, 'only the ticked line came in');
        self::assertSame('empty', $stock['Savon']['level']);
        $moves = $this->api('GET', '/api/movements?type=in', null, $alice);
        self::assertCount(1, $moves);
        self::assertStringStartsWith('cart:'.$cart['id'].':', $moves[0]['externalRef']);

        $this->api('PATCH', "/api/shopping-carts/{$cart['id']}", ['status' => 'in_progress'], $alice);
        $this->assertStatus(409);
        $this->api('DELETE', "/api/shopping-carts/{$cart['id']}", null, $alice);
        $this->assertStatus(403);

        [, $token] = $this->createApplication(false, 'Rocket Host');
        self::assertCount(1, $this->api('GET', '/api/shopping-carts', null, 'Bearer '.$token));
    }

    public function testNothingToBuy(): void
    {
        $alice = 'Bearer '.$this->jwtFor($this->createUser('alice@example.org'));
        $this->api('POST', '/api/shopping-carts', null, $alice);
        $this->assertStatus(422);
    }
}

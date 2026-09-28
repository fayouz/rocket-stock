<?php

namespace App\Tests\Functional;

use App\Mailer\DemoMailer;
use App\Tests\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Catalogue, levels (Rocket Place compatible), movements, export, equipment and access. No network (standalone places, DemoMailer). */
final class StockTest extends WebTestCase
{
    use ApiTestTrait;

    private string $admin;
    private string $alice;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->admin = 'Bearer '.$this->jwtFor($this->createUser('admin@example.org', ['ROLE_ADMIN']));
        $this->alice = 'Bearer '.$this->jwtFor($this->createUser('alice@example.org'));
    }

    public function testCatalogueIsAdminOnlyAndKeepsPlaceFields(): void
    {
        $this->api('POST', '/api/stock-items', ['name' => 'Papier toilette'], $this->alice);
        $this->assertStatus(403);
        $item = $this->api('POST', '/api/stock-items', ['name' => 'Papier toilette', 'asin' => 'B07PGL7C4L', 'reorderQty' => 12, 'subscription' => false, 'unit' => 'rouleau', 'reorderThreshold' => 4, 'unitCost' => 0.5], $this->admin);
        $this->assertStatus(201);
        foreach (['id', 'name', 'asin', 'reorderQty', 'subscription'] as $key) {
            self::assertArrayHasKey($key, $item, 'Rocket Place field');
        }
        self::assertSame('consumable', $item['category']);
        $this->api('PATCH', "/api/stock-items/{$item['id']}", ['category' => 'nope'], $this->admin);
        $this->assertStatus(422);
        self::assertCount(1, $this->api('GET', '/api/stock-items', null, $this->alice));
    }

    public function testLevelsAreCompatibleWithRocketPlace(): void
    {
        $place = $this->api('POST', '/api/places', ['name' => 'Le port'], $this->admin)['id'];
        $item = $this->api('POST', '/api/stock-items', ['name' => 'Café dosettes', 'reorderThreshold' => 5], $this->admin)['id'];

        $level = $this->api('POST', '/api/stock-levels', ['place' => "/api/places/$place", 'item' => "/api/stock-items/$item", 'level' => 'ok'], $this->alice);
        $this->assertStatus(201);
        self::assertSame("/api/places/$place", $level['place']);
        self::assertSame("/api/stock-items/$item", $level['item']);
        self::assertSame('Café dosettes', $level['name']);

        self::assertSame('low', $this->api('PATCH', "/api/stock-levels/{$level['id']}", ['level' => 'low'], $this->alice)['level']);
        $levels = $this->api('GET', '/api/stock-levels?'.http_build_query(['place' => "/api/places/$place"]), null, $this->alice);
        self::assertCount(1, $levels);
        self::assertCount(1, $this->api('GET', "/api/places/$place/stock", null, $this->alice));

        // a quantity recomputes the state against the threshold (override first)
        self::assertSame('ok', $this->api('PATCH', "/api/stock-levels/{$level['id']}", ['quantity' => 12], $this->alice)['level']);
        self::assertSame('low', $this->api('PATCH', "/api/stock-levels/{$level['id']}", ['thresholdOverride' => 20], $this->alice)['level']);
        $empty = $this->api('PATCH', "/api/stock-levels/{$level['id']}", ['level' => 'empty'], $this->alice);
        self::assertSame(0.0, (float) $empty['quantity']);

        // a sub-location is another level of the same place
        $reserve = $this->api('POST', "/api/places/$place/stock", ['item' => $item, 'location' => 'réserve', 'quantity' => 40], $this->alice);
        $this->assertStatus(201);
        self::assertSame('réserve', $reserve['location']['name']);
        self::assertCount(2, $this->api('GET', "/api/places/$place/locations", null, $this->alice));

        $this->api('DELETE', "/api/stock-levels/{$level['id']}", null, $this->alice);
        $this->assertStatus(204);
        $this->api('POST', '/api/stock-levels', ['place' => '/api/places/0192f7c4-0000-7000-8000-00000000dead', 'item' => $item], $this->alice);
        $this->assertStatus(404);
    }

    public function testMovementsAreIdempotentAndApplied(): void
    {
        $place = $this->api('POST', '/api/places', ['name' => 'Le port'], $this->admin)['id'];
        $other = $this->api('POST', '/api/places', ['name' => 'Les vignes'], $this->admin)['id'];
        $item = $this->api('POST', '/api/stock-items', ['name' => 'Café dosettes', 'reorderThreshold' => 5, 'unitCost' => 0.3], $this->admin)['id'];

        $in = $this->api('POST', '/api/movements', ['item' => $item, 'placeId' => $place, 'type' => 'in', 'quantity' => 40], $this->alice);
        $this->assertStatus(201);
        self::assertSame('rental', $in['usage']);
        $consume = ['item' => "/api/stock-items/$item", 'placeId' => $place, 'type' => 'consume', 'quantity' => 8, 'externalRef' => 'cleaning:42', 'origin' => 'clean'];
        $this->api('POST', '/api/movements', $consume, $this->alice);
        $this->assertStatus(201);
        $again = $this->api('POST', '/api/movements', $consume, $this->alice);
        $this->assertStatus(200);
        self::assertSame('cleaning:42', $again['externalRef']);
        self::assertEqualsWithDelta(32, $this->api('GET', "/api/places/$place/stock", null, $this->alice)[0]['quantity'], 0.001, 'applied once');

        $this->api('POST', '/api/movements', ['item' => $item, 'placeId' => $place, 'type' => 'consume', 'quantity' => 30, 'usage' => 'personal'], $this->alice);
        $level = $this->api('GET', "/api/places/$place/stock", null, $this->alice)[0];
        self::assertSame('low', $level['level']);

        $this->api('POST', '/api/movements', ['item' => $item, 'placeId' => $place, 'type' => 'transfer', 'quantity' => 2, 'toPlaceId' => $other], $this->alice);
        self::assertSame('empty', $this->api('GET', "/api/places/$place/stock", null, $this->alice)[0]['level']);
        self::assertEqualsWithDelta(2, $this->api('GET', "/api/places/$other/stock", null, $this->alice)[0]['quantity'], 0.001);

        $this->api('POST', '/api/movements', ['item' => $item, 'placeId' => $place, 'type' => 'adjust', 'quantity' => 10], $this->alice);
        self::assertSame('ok', $this->api('GET', "/api/places/$place/stock", null, $this->alice)[0]['level']);

        // a list is recorded all or nothing
        $this->api('POST', '/api/movements', [['item' => $item, 'placeId' => $place, 'type' => 'consume', 'quantity' => 1, 'externalRef' => 'x:1'], ['item' => $item, 'placeId' => $place, 'type' => 'nope', 'quantity' => 1]], $this->alice);
        $this->assertStatus(422);
        self::assertSame([], $this->api('GET', '/api/movements?externalRef=x:1', null, $this->alice));
        $this->api('POST', '/api/movements', ['item' => $item, 'placeId' => $place, 'type' => 'consume', 'quantity' => 0], $this->alice);
        $this->assertStatus(422);

        self::assertCount(5, $this->api('GET', "/api/movements?place=$place", null, $this->alice));

        // export: rental consumption only, valued
        $bilan = $this->api('GET', '/api/export/consumption?usage=rental', null, $this->alice);
        self::assertCount(1, $bilan['items']);
        self::assertEqualsWithDelta(8, $bilan['items'][0]['quantity'], 0.001);
        self::assertEqualsWithDelta(2.4, $bilan['totalCost'], 0.001);
        $this->client->request('GET', '/api/export/movements?format=csv&usage=personal', server: ['HTTP_AUTHORIZATION' => $this->alice]);
        $this->assertStatus(200);
        self::assertStringContainsString('text/csv', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame(1, substr_count(trim((string) $this->client->getResponse()->getContent()), "\n"), 'header + one row');
    }

    public function testEquipmentAndSuppliers(): void
    {
        $place = $this->api('POST', '/api/places', ['name' => 'Le port'], $this->admin)['id'];
        $supplier = $this->api('POST', '/api/suppliers', ['name' => 'Darty', 'website' => 'https://www.darty.com'], $this->admin);
        $this->assertStatus(201);
        $this->api('POST', '/api/equipment', ['name' => 'Lave-linge'], $this->alice);
        $this->assertStatus(403);
        $eq = $this->api('POST', '/api/equipment', ['name' => 'Lave-linge', 'placeId' => $place, 'room' => 'buanderie', 'serial' => 'WAN-1', 'purchaseDate' => '2025-01-10',
            'warrantyEnd' => (new \DateTimeImmutable('+30 days'))->format('Y-m-d'), 'manualDocumentRef' => 'cloud:file-1', 'supplier' => $supplier['id']], $this->admin);
        $this->assertStatus(201);
        self::assertTrue($eq['underWarranty']);
        self::assertSame('Darty', $eq['supplier']['name']);
        self::assertCount(1, $this->api('GET', '/api/equipment?warranty=expiring', null, $this->alice));
        self::assertCount(1, $this->api('GET', "/api/places/$place/equipment", null, $this->alice));
        $this->api('PATCH', "/api/equipment/{$eq['id']}", ['purchaseDate' => '10/01/2025'], $this->admin);
        $this->assertStatus(422);
    }

    public function testApplicationActsForItselfAndLowStockAlertIsOptIn(): void
    {
        [, $token] = $this->createApplication(false, 'Rocket Clean');
        $app = 'Bearer '.$token;
        $place = $this->api('POST', '/api/places', ['name' => 'Le port'], $app)['id'];
        $this->assertStatus(201);
        $item = $this->api('POST', '/api/stock-items', ['name' => 'Café dosettes', 'reorderThreshold' => 5], $app)['id'];
        $m = $this->api('POST', '/api/movements', ['item' => $item, 'placeId' => $place, 'type' => 'in', 'quantity' => 3, 'origin' => 'clean', 'externalRef' => 'cleaning:7'], $app);
        $this->assertStatus(201);
        self::assertSame('Rocket Clean', $m['originApp']);
        $this->api('GET', '/api/shopping-list', null, $app);
        $this->assertStatus(200);
        $this->api('GET', '/api/users', null, $app);
        $this->assertStatus(403);

        // STOCK_ALERT_EMAILS is empty in tests: no alert e-mail
        self::assertSame([], static::getContainer()->get(DemoMailer::class)->sent());
    }
}

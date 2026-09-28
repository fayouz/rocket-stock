<?php

namespace App\Command;

use App\Entity\Equipment;
use App\Entity\Item;
use App\Entity\ItemOffer;
use App\Entity\Movement;
use App\Entity\Site;
use App\Entity\Supplier;
use App\Repository\ItemRepository;
use App\Repository\MovementRepository;
use App\Repository\SiteRepository;
use App\Repository\SupplierRepository;
use App\Stock\StockBook;
use Doctrine\ORM\EntityManagerInterface;
use Rocket\Core\Command\DemoSeederInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Demo data: two local places (same ids as Rocket Place's demo places), a store, Amazon and a supplier taking e-mail orders, a catalogue of
 * consumables/linen/equipment with offers, stock at each place (some low or empty), a few movements (rental and
 * personal) and two appliances. Idempotent (by names and externalRef).
 */
final class StockDemoSeeder implements DemoSeederInterface
{
    public const PORT = '0192f7c4-0000-7000-8000-000000000001';
    public const VIGNES = '0192f7c4-0000-7000-8000-000000000002';

    public function __construct(
        private readonly SiteRepository $sites,
        private readonly SupplierRepository $suppliers,
        private readonly ItemRepository $items,
        private readonly MovementRepository $movements,
        private readonly StockBook $book,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function seed(array $users, SymfonyStyle $io): void
    {
        foreach ([self::PORT => 'Le port', self::VIGNES => 'Les vignes'] as $id => $name) {
            if (null === $this->sites->find($id)) {
                $this->em->persist(new Site($name, Site::LOCAL, $id));
            }
        }
        $this->em->flush();

        $store = $this->supplier('Carrefour Market du port', fn (Supplier $s) => $s->setKind('store')->setAddress('12 quai des Pêcheurs, 34200 Sète')->setLat(43.4028)->setLng(3.6936)->setOpeningHours('lun-sam 8h30-20h30, dim 9h-12h30')->setSearchUrlTemplate('https://www.carrefour.fr/s?q={ean}'));
        $online = $this->supplier('Amazon', fn (Supplier $s) => $s->setKind('store')->setWebsite('https://www.amazon.fr')->setAmazon(true)->setAmazonDomain('amazon.fr'));
        $wholesaler = $this->supplier('Hygiène Pro Occitanie', fn (Supplier $s) => $s->setKind('supplier')->setEmail('contact@hygiene-pro.example')->setOrderEmail('commandes@hygiene-pro.example')->setPhone('04 67 00 00 00'));
        $catalogue = [
            // name, unit, category, threshold, reorderQty, unitCost, EAN (fictitious, valid check digit), offers [store, preferred, price, packSize, asin]
            ['Papier toilette', 'rouleau', 'consumable', 6, 12, 0.45, '3000000000014', [[$store, true, 5.40, 12, null], [$online, false, 7.90, 24, 'B0DEMO0001']]],
            ['Café dosettes', 'dosette', 'consumable', 10, 40, 0.30, '3000000000021', [[$online, true, 11.90, 40, 'B0DEMO0002'], [$store, false, 4.20, 10, null]]],
            ['Liquide vaisselle', 'flacon', 'consumable', 1, 2, 2.10, '3000000000038', [[$store, true, 2.10, 1, null]]],
            ['Sacs poubelle 30 L', 'sac', 'consumable', 10, 50, 0.08, '3000000000045', [[$wholesaler, true, 2.00, 25, null], [$store, false, 2.40, 25, null]]],
            ['Draps 160×200', 'parure', 'linen', 2, 2, 35.00, null, []],
            ['Serviettes de bain', 'serviette', 'linen', 4, 4, 9.00, null, []],
            ['Aspirateur balai', 'unité', 'equipment', 0, 1, 180.00, null, []],
        ];
        $items = [];
        foreach ($catalogue as [$name, $unit, $category, $threshold, $qty, $cost, $ean, $offers]) {
            $item = $this->items->findOneBy(['name' => $name]);
            if (null === $item) {
                $this->em->persist($item = (new Item($name))->setUnit($unit)->setCategory($category)->setReorderThreshold($threshold)->setReorderQty($qty)->setUnitCost($cost)->setEan($ean)->setSupplier($offers[0][0] ?? null));
                foreach ($offers as [$s, $preferred, $price, $pack, $asin]) {
                    $this->em->persist((new ItemOffer($item, $s))->setPreferred($preferred)->setPrice($price)->setPackSize($pack)->setAsin($asin));
                }
            }
            $items[$name] = $item;
        }
        $this->em->flush();

        $stock = [
            // place, sub-location, item, quantity
            [self::PORT, '', 'Papier toilette', 4], [self::PORT, '', 'Café dosettes', 32], [self::PORT, 'cuisine', 'Liquide vaisselle', 0],
            [self::PORT, 'réserve', 'Sacs poubelle 30 L', 40], [self::PORT, 'réserve', 'Draps 160×200', 3], [self::PORT, 'réserve', 'Serviettes de bain', 8],
            [self::VIGNES, '', 'Papier toilette', 18], [self::VIGNES, '', 'Café dosettes', 6], [self::VIGNES, '', 'Serviettes de bain', 6],
        ];
        foreach ($stock as [$placeId, $sub, $name, $qty]) {
            $level = $this->book->level($this->book->location($placeId, $sub), $items[$name]);
            if (null === $level->getQuantity()) {
                $this->book->set($level, $qty, null);
            }
        }
        $this->em->flush();

        $movements = [
            ['demo-conso-1', self::PORT, '', 'Café dosettes', 'consume', 8, 'rental', 'clean', '-2 days'],
            ['demo-conso-2', self::PORT, '', 'Papier toilette', 'consume', 4, 'rental', 'clean', '-2 days'],
            ['demo-conso-3', self::VIGNES, '', 'Café dosettes', 'consume', 6, 'personal', 'stock', '-1 day'],
        ];
        foreach ($movements as [$ref, $placeId, $sub, $name, $type, $qty, $usage, $origin, $when]) {
            if (null === $this->movements->findOneBy(['externalRef' => $ref])) {
                // history only: the levels above are already the result of these
                $this->em->persist((new Movement($items[$name], $this->book->location($placeId, $sub), $type, $qty, new \DateTimeImmutable($when)))->setExternalRef($ref)->setUsage($usage)->setOrigin($origin)->setReason('Démo'));
            }
        }
        foreach ([['Lave-linge Bosch Serie 4', self::PORT, 'buanderie', 'WAN28208FF-0042', '-400 days', '+330 days'], ['Machine à café Nespresso', self::VIGNES, 'cuisine', 'XN9101-7781', '-800 days', '-70 days']] as [$name, $placeId, $room, $serial, $bought, $warranty]) {
            if (null === $this->em->getRepository(Equipment::class)->findOneBy(['name' => $name])) {
                $this->em->persist((new Equipment($name))->setPlaceId($placeId)->setRoom($room)->setSerial($serial)
                    ->setPurchaseDate(new \DateTimeImmutable("today $bought"))->setWarrantyEnd(new \DateTimeImmutable("today $warranty"))->setManualDocumentRef('demo/manuels/'.$serial.'.pdf'));
            }
        }
        $this->em->flush();

        $io->text('Rocket Stock : 2 lieux locaux, 3 magasins/fournisseurs (dont Amazon), '.\count($items).' articles, 9 niveaux, 3 mouvements, 2 équipements.');
    }

    private function supplier(string $name, callable $init): Supplier
    {
        $s = $this->suppliers->findOneBy(['name' => $name]);
        if (null === $s) {
            $this->em->persist($s = $init(new Supplier($name)));
        }

        return $s;
    }
}

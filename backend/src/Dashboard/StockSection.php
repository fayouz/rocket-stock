<?php

namespace App\Dashboard;

use App\Entity\Item;
use App\Entity\Level;
use App\Entity\Movement;
use App\Place\PlaceDirectory;
use App\Stock\Replenishment;
use Doctrine\ORM\EntityManagerInterface;
use Rocket\Core\Dashboard\DashboardSectionInterface;
use Rocket\Core\Entity\User;

/** Stock on the dashboard: articles, levels to restock, rental consumption of the period, and the low/empty levels. */
final class StockSection implements DashboardSectionInterface
{
    public function __construct(
        private readonly Replenishment $replenishment,
        private readonly PlaceDirectory $places,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function build(User $user, bool $admin, \DateTimeImmutable $from, \DateTimeImmutable $previousFrom): array
    {
        $needs = $this->replenishment->needs(null);
        $cost = 0.0;
        foreach ($this->em->getRepository(Movement::class)->createQueryBuilder('m')->where('m.occurredAt >= :from')->andWhere('m.usage = :rental')
            ->setParameter('from', $from)->setParameter('rental', 'rental')->getQuery()->getResult() as $m) {
            /** @var Movement $m */
            $cost += $m->isConsumption() ? ($m->cost() ?? 0.0) : 0.0;
        }

        return [
            'kpis' => [
                ['id' => 'stock_items', 'label' => 'Articles', 'value' => $this->em->getRepository(Item::class)->count([]), 'format' => 'number', 'icon' => 'i-lucide-package', 'tone' => 'bg-primary/10 text-primary'],
                ['id' => 'stock_alerts', 'label' => 'À réassortir', 'value' => \count($needs), 'format' => 'number', 'icon' => 'i-lucide-triangle-alert', 'tone' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400'],
                ['id' => 'stock_rental_cost', 'label' => 'Consommé location (€)', 'value' => round($cost, 2), 'format' => 'number', 'icon' => 'i-lucide-receipt-euro', 'tone' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'],
            ],
            'series' => [],
            'daily' => [],
            'recent' => [
                'title' => 'Stock à réassortir',
                'link' => '/courses',
                'empty' => 'Tout est en stock.',
                'items' => array_map(fn (array $n) => [
                    'id' => $n['level']->getId()->toRfc4122(),
                    'title' => $n['level']->getItem()->getName(),
                    'subtitle' => $this->places->cachedName($n['level']->getLocation()->getPlaceId()).('' !== $n['level']->getLocation()->getName() ? ' · '.$n['level']->getLocation()->getName() : ''),
                    'at' => $n['level']->getUpdatedAt()?->format(\DATE_ATOM),
                    'badge' => Level::EMPTY === $n['level']->getLevel() ? 'Vide' : 'Bas',
                    'badgeColor' => Level::EMPTY === $n['level']->getLevel() ? 'error' : 'warning',
                    'link' => '/places/'.$n['level']->getLocation()->getPlaceId(),
                ], \array_slice($needs, 0, 8)),
            ],
            'quickActions' => [
                ['label' => 'Courses', 'icon' => 'i-lucide-shopping-cart', 'to' => '/courses', 'tone' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400'],
                ['label' => 'Lieux', 'icon' => 'i-lucide-map-pin', 'to' => '/places', 'tone' => 'bg-primary/10 text-primary'],
                ['label' => 'Catalogue', 'icon' => 'i-lucide-package', 'to' => '/catalogue', 'tone' => 'bg-sky-500/10 text-sky-600 dark:text-sky-400'],
            ],
        ];
    }
}

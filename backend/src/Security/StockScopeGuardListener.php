<?php

namespace App\Security;

use Rocket\Core\Security\ApplicationUser;
use Rocket\Core\Security\ScopeGuardListener;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * rocket-core only lets an application that does not impersonate anyone call GET /api/me. Stock opens its own
 * business endpoints (places and their stock, catalogue, levels, movements, shopping list and carts, equipment,
 * suppliers and stores, export) to such applications, so a PMS, Rocket Clean or Rocket Place can use it server-to-server; every other
 * endpoint (users, applications, settings…) stays guarded by rocket-core's listener.
 */
#[AsDecorator(ScopeGuardListener::class)]
final class StockScopeGuardListener
{
    private const APPLICATION_PATTERN = '#^/api/(places(/[^/]+(/(stock|locations|equipment)(/[^/]+)?)?)?|locations(/[^/]+)?|stock-items(/[^/]+(/offers)?)?|stock-levels(/[^/]+)?|movements(/[^/]+)?|shopping-list|shopping-carts(/[^/]+(/(text|lines/[^/]+))?)?|equipment(/[^/]+)?|suppliers(/[^/]+)?|stores|export/[a-z-]+)$#';

    public function __construct(
        #[AutowireDecorated] private readonly ScopeGuardListener $inner,
        private readonly Security $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if ($event->isMainRequest() && $this->security->getUser() instanceof ApplicationUser
            && preg_match(self::APPLICATION_PATTERN, $event->getRequest()->getPathInfo())) {
            return;
        }

        ($this->inner)($event);
    }
}

<?php

namespace App\Security;

use Rocket\Core\Security\ApplicationUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Stock's own permissions, on top of rocket-core roles:
 * - STOCK_READ: any signed-in user (ROLE_USER) or an application acting on its own behalf — reading, setting a level,
 *   recording a movement (whoever cleans or restocks);
 * - STOCK_MANAGE: an administrator (ROLE_ADMIN) or an application acting on its own behalf — catalogue, suppliers,
 *   equipment, places, deleting.
 * An application token (Bearer rst_…) without X-Impersonate-User only carries ROLE_APPLICATION: it is a
 * server-to-server client of Stock (Rocket PMS, Rocket Clean, Rocket Place…). An impersonating application keeps the
 * delegated roles of the user (never ROLE_ADMIN), so it is not elevated here either.
 *
 * @extends Voter<string, mixed>
 */
final class StockAccessVoter extends Voter
{
    public const READ = 'STOCK_READ';
    public const MANAGE = 'STOCK_MANAGE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::READ === $attribute || self::MANAGE === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($token->getUser() instanceof ApplicationUser) {
            return true;
        }

        return $this->security->isGranted(self::READ === $attribute ? 'ROLE_USER' : 'ROLE_ADMIN');
    }
}

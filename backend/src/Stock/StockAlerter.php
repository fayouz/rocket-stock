<?php

namespace App\Stock;

use App\Entity\Level;
use App\Mailer\MailerClient;
use App\Place\PlaceDirectory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Rocket\Core\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Low-stock alerts by e-mail through Rocket Mailer, off by default: only when STOCK_ALERT_EMAILS lists recipients.
 * One e-mail per level that drops from ok to low/empty (or low to empty). A failed send never fails the caller.
 * Rocket Mailer sends on behalf of ROCKET_MAILER_SENDER, else the first administrator.
 */
final class StockAlerter
{
    public function __construct(
        private readonly MailerClient $mailer,
        private readonly PlaceDirectory $places,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $alertEmails = '',
        #[Autowire('%env(ROCKET_MAILER_SENDER)%')] private readonly string $sender = '',
    ) {
    }

    /** @return list<string> */
    public function recipients(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->alertEmails)), static fn (string $e) => false !== filter_var($e, \FILTER_VALIDATE_EMAIL)));
    }

    public function enabled(): bool
    {
        return [] !== $this->recipients();
    }

    public function changed(Level $level, string $before): bool
    {
        $rank = [Level::OK => 0, Level::LOW => 1, Level::EMPTY => 2];
        if (!$this->enabled() || $rank[$level->getLevel()] <= $rank[$before]) {
            return false;
        }
        $as = '' !== trim($this->sender) ? trim($this->sender) : $this->firstAdmin();
        if (null === $as) {
            return false;
        }
        $place = $this->places->cachedName($level->getLocation()->getPlaceId()).('' !== $level->getLocation()->getName() ? ' · '.$level->getLocation()->getName() : '');
        $state = Level::EMPTY === $level->getLevel() ? 'vide' : 'bas';
        $qty = null === $level->getQuantity() ? '' : \sprintf(' (%s %s restant)', rtrim(rtrim(number_format($level->getQuantity(), 3, ',', ''), '0'), ','), $level->getItem()->getUnit());
        try {
            $this->mailer->send($as, [
                'to' => $this->recipients(),
                'subject' => \sprintf('Stock %s : %s — %s', $state, $level->getItem()->getName(), $place),
                'htmlBody' => \sprintf('<p>Le stock de <b>%s</b> est <b>%s</b> à %s%s.</p><p>Voir la liste de courses dans Rocket Stock.</p>', htmlspecialchars($level->getItem()->getName()), $state, htmlspecialchars($place), $qty),
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Low-stock alert not sent: {message}', ['message' => $e->getMessage()]);

            return false;
        }
    }

    private function firstAdmin(): ?string
    {
        foreach ($this->em->getRepository(User::class)->findBy(['enabled' => true], ['email' => 'ASC']) as $u) {
            if (\in_array('ROLE_ADMIN', $u->getRoles(), true)) {
                return $u->getEmail();
            }
        }

        return null;
    }
}

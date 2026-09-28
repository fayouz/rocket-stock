<?php

namespace App\Place;

use App\Entity\Site;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Uid\Uuid;

/**
 * The places stock is kept in, whatever their source:
 * - Rocket Place configured (PlaceClient::isConfigured): places are Place's (GET /api/places); each place read is
 *   mirrored in a Site of source "place" (name cache).
 * - standalone: local Sites only (created in Rocket Stock).
 * Callers flush.
 */
final class PlaceDirectory
{
    public function __construct(
        private readonly PlaceClient $place,
        private readonly SiteRepository $sites,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function isRemote(): bool
    {
        return $this->place->isConfigured();
    }

    /** @return list<array{id: string, name: string, source: string}> */
    public function all(): array
    {
        if ($this->isRemote()) {
            return array_values(array_map(static fn (array $p) => ['id' => (string) $p['id'], 'name' => (string) ($p['name'] ?? ''), 'source' => Site::PLACE],
                array_filter($this->place->request('GET', '/api/places'), static fn ($p) => \is_array($p) && isset($p['id']))));
        }

        return array_map(static fn (Site $s) => $s->toArray(), $this->sites->findBy(['source' => Site::LOCAL], ['name' => 'ASC']));
    }

    /** Name of a known place without any network call (the Site cache), "Lieu" if never seen. */
    public function cachedName(string $placeId): string
    {
        return Uuid::isValid($placeId) ? ($this->sites->find($placeId)?->getName() ?? 'Lieu') : 'Lieu';
    }

    /** The Site of a place (a 404 if unknown), refreshed from Rocket Place when configured. */
    public function get(string $placeId): Site
    {
        if (!Uuid::isValid($placeId)) {
            throw new HttpException(404, 'Lieu inconnu.');
        }
        $site = $this->sites->find($placeId);
        if ($this->isRemote()) {
            $data = $this->place->request('GET', '/api/places/'.$placeId);
            $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 160) ?: 'Lieu';
            if (null === $site) {
                $this->em->persist($site = new Site($name, Site::PLACE, $placeId));
            }
            $site->setName($name);

            return $site;
        }
        if (null === $site || !$site->isLocal()) {
            throw new HttpException(404, 'Lieu inconnu.');
        }

        return $site;
    }
}

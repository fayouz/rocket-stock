<?php

namespace App\Tests\Unit;

use App\Secrets\IntegrationSecrets;
use App\Entity\Site;
use App\Place\PlaceClient;
use App\Place\PlaceDirectory;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Stock → Rocket Place (places), with a mocked HTTP client. No network. */
final class PlaceDirectoryTest extends TestCase
{
    private const PLACE = '0192f7c4-0000-7000-8000-000000000001';

    /** @var list<array{method: string, url: string, body: string, auth: string}> */
    private array $calls = [];

    private function directory(string $url = 'http://place.test', ?Site $cached = null): PlaceDirectory
    {
        $http = new MockHttpClient(function (string $method, string $u, array $options): MockResponse {
            $this->calls[] = ['method' => $method, 'url' => $u, 'body' => (string) ($options['body'] ?? ''), 'auth' => implode(',', $options['headers'] ?? [])];
            $path = (string) parse_url($u, \PHP_URL_PATH);
            $json = match (true) {
                '/api/places' === $path => [['id' => self::PLACE, 'name' => 'Le port']],
                '/api/places/'.self::PLACE === $path => ['id' => self::PLACE, 'name' => 'Le port'],
                default => null,
            };

            return null === $json ? new MockResponse('{"detail":"Lieu introuvable."}', ['http_code' => 404]) : new MockResponse(json_encode($json, \JSON_THROW_ON_ERROR));
        });
        $sites = $this->createStub(SiteRepository::class);
        $sites->method('find')->willReturn($cached);

        return new PlaceDirectory(new PlaceClient($http, $url, IntegrationSecrets::fixed(['rocket.place.token' => 'rpl_secret'])), $sites, $this->createStub(EntityManagerInterface::class));
    }

    public function testRemotePlaces(): void
    {
        $directory = $this->directory();
        self::assertTrue($directory->isRemote());
        self::assertSame([['id' => self::PLACE, 'name' => 'Le port', 'source' => 'place']], $directory->all());
        $site = $directory->get(self::PLACE);
        self::assertSame('Le port', $site->getName());
        self::assertFalse($site->isLocal());
        self::assertStringContainsString('Bearer rpl_secret', $this->calls[0]['auth']);
    }

    public function testUnknownPlaceErrorPassesThrough(): void
    {
        try {
            $this->directory()->get('0192f7c4-0000-7000-8000-000000000009');
            self::fail('404 expected');
        } catch (HttpException $e) {
            self::assertSame(404, $e->getStatusCode());
            self::assertSame('Lieu introuvable.', $e->getMessage());
        }
    }

    public function testStandaloneWithoutPlaceUrl(): void
    {
        $directory = $this->directory('', new Site('Chez moi'));
        self::assertFalse($directory->isRemote());
        self::assertSame('Chez moi', $directory->get(self::PLACE)->getName());
        self::assertSame([], $this->calls);
    }
}

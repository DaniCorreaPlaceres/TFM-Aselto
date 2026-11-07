<?php

namespace App\Tests\Cliente;

use App\Asesoria\Domain\Firm;
use App\Asesoria\Domain\FirmRepository;
use App\Cliente\Application\ShowClientHome;
use App\Cliente\Domain\Client;
use App\Cliente\Domain\ClientKind;
use App\Cliente\Domain\ClientRepository;
use PHPUnit\Framework\TestCase;

final class ClientHomeTest extends TestCase
{
    public function testAClientWithoutAFirmIsGreetedByName(): void
    {
        $clients = new HomeClientRepository();
        $clients->save(new Client(
            '00000000-0000-4000-8000-000000000021',
            '00000000-0000-4000-8000-000000000014',
            ClientKind::SelfEmployed,
            'Ana',
            null,
        ));

        $home = (new ShowClientHome($clients, new HomeFirmRepository()))
            ->execute('00000000-0000-4000-8000-000000000014');

        self::assertSame('Ana', $home->name());
        self::assertNull($home->firmName());
    }

    public function testALinkedClientSeesOnlyThatFirm(): void
    {
        $firms = new HomeFirmRepository();
        $firms->save(new Firm('00000000-0000-4000-8000-000000000001', 'Norte'));
        $firms->save(new Firm('00000000-0000-4000-8000-000000000002', 'Sur'));
        $clients = new HomeClientRepository();
        $clients->save(new Client(
            '00000000-0000-4000-8000-000000000021',
            '00000000-0000-4000-8000-000000000014',
            ClientKind::Company,
            'Taller Norte',
            '00000000-0000-4000-8000-000000000001',
        ));

        $home = (new ShowClientHome($clients, $firms))
            ->execute('00000000-0000-4000-8000-000000000014');

        self::assertSame('Taller Norte', $home->name());
        self::assertSame('Norte', $home->firmName());
    }

    public function testAMissingProfileHasNoName(): void
    {
        $home = (new ShowClientHome(new HomeClientRepository(), new HomeFirmRepository()))
            ->execute('00000000-0000-4000-8000-000000000014');

        self::assertNull($home->name());
        self::assertNull($home->firmName());
    }
}

final class HomeClientRepository implements ClientRepository
{
    /** @var array<string, Client> */
    private array $clients = [];

    public function find(string $id): ?Client
    {
        foreach ($this->clients as $client) {
            if ($client->id() === $id) {
                return $client;
            }
        }

        return null;
    }

    public function findByUserId(string $userId): ?Client
    {
        return $this->clients[$userId] ?? null;
    }

    public function findByFirm(string $firmId): array
    {
        return [];
    }

    public function findWithoutFirm(): array
    {
        return [];
    }

    public function save(Client $client): void
    {
        $this->clients[$client->userId()] = $client;
    }
}

final class HomeFirmRepository implements FirmRepository
{
    /** @var array<string, Firm> */
    private array $firms = [];

    public function find(string $id): ?Firm
    {
        return $this->firms[$id] ?? null;
    }

    public function all(): array
    {
        return array_values($this->firms);
    }

    public function save(Firm $firm): void
    {
        $this->firms[$firm->id()] = $firm;
    }
}

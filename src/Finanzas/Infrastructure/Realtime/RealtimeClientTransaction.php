<?php

namespace App\Finanzas\Infrastructure\Realtime;

use App\Shared\Domain\ClientTransaction;
use App\Shared\Infrastructure\DoctrineClientTransaction;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(decorates: DoctrineClientTransaction::class)]
final class RealtimeClientTransaction implements ClientTransaction
{
    public function __construct(#[AutowireDecorated] private readonly ClientTransaction $inner, private readonly QuarterUpdatePublisher $publisher)
    {
    }

    public function run(string $ownerId, callable $operation): mixed
    {
        $result = $this->inner->run($ownerId, $operation);
        $this->publisher->publish($ownerId);

        return $result;
    }
}

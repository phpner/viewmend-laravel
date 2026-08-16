<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Tests\Support;

use GuzzleHttp\Psr7\HttpFactory;
use ViewMend\Laravel\Contracts\ClientFactoryContract;
use ViewMend\ViewMend;

final class CountingClientFactory implements ClientFactoryContract
{
    /** @var list<string> */
    public array $connections = [];

    public function __construct(public readonly BombHttpClient $http = new BombHttpClient())
    {
    }

    public function make(string $connection, #[\SensitiveParameter] string $token): ViewMend
    {
        $this->connections[] = $connection;
        $factory = new HttpFactory();

        return ViewMend::withPsr18(
            token: $token,
            httpClient: $this->http,
            requestFactory: $factory,
            streamFactory: $factory,
        );
    }
}

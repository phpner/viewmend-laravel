<?php

declare(strict_types=1);

namespace ViewMend\Laravel;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use ViewMend\Laravel\Contracts\ClientFactoryContract;
use ViewMend\Laravel\Exception\MalformedConfigurationException;
use ViewMend\ViewMend;

final class DefaultClientFactory implements ClientFactoryContract
{
    public function __construct(private readonly ?ConfigRepository $config = null)
    {
    }

    public function make(string $connection, #[\SensitiveParameter] string $token): ViewMend
    {
        $key = sprintf('viewmend.connections.%s.api_base_url', $connection);
        $baseUrl = $this->config?->get($key) ?? ViewMend::PRODUCTION_API_BASE_URL;

        if (!is_string($baseUrl) || trim($baseUrl) === '' || trim($baseUrl) !== $baseUrl) {
            throw MalformedConfigurationException::at($key, 'a non-empty URL string without surrounding whitespace');
        }

        return ViewMend::client($token, $baseUrl);
    }

    /** @return array{} */
    public function __debugInfo(): array
    {
        return [];
    }
}

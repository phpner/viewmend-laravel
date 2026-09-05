<?php

declare(strict_types=1);

namespace ViewMend\Laravel;

use ViewMend\Cron\CronClient;
use ViewMend\Laravel\Contracts\ClientFactoryContract;
use ViewMend\Laravel\Contracts\ViewMendManagerContract;
use ViewMend\Laravel\Exception\MalformedConfigurationException;
use ViewMend\Laravel\Exception\MissingConnectionException;
use ViewMend\Laravel\Exception\MissingIntegrationIdException;
use ViewMend\Laravel\Exception\MissingTokenException;
use ViewMend\SiteTracker\SiteTrackerClient;
use ViewMend\ViewMend;

final class ViewMendManager implements ViewMendManagerContract
{
    /** @var array<string, ViewMendConnection> */
    private array $connections = [];

    /** @param array<mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly ClientFactoryContract $factory,
    ) {
    }

    public function connection(?string $name = null): ViewMendConnection
    {
        $name ??= $this->defaultConnectionName();

        if (trim($name) === '' || trim($name) !== $name) {
            throw MalformedConfigurationException::at(
                'viewmend.default',
                'a non-empty connection name',
            );
        }

        return $this->connections[$name] ??= $this->makeConnection($name);
    }

    public function siteTracker(): SiteTrackerClient
    {
        return $this->connection()->siteTracker();
    }

    public function client(): ViewMend
    {
        return $this->connection()->client();
    }

    public function cron(): CronClient
    {
        return $this->connection()->cron();
    }

    /** @return array{default: mixed, resolvedConnections: list<string>} */
    public function __debugInfo(): array
    {
        return [
            'default' => $this->config['default'] ?? null,
            'resolvedConnections' => array_keys($this->connections),
        ];
    }

    private function defaultConnectionName(): string
    {
        $default = $this->config['default'] ?? null;

        if (!is_string($default) || trim($default) === '' || trim($default) !== $default) {
            throw MalformedConfigurationException::at(
                'viewmend.default',
                'a non-empty string',
            );
        }

        return $default;
    }

    private function makeConnection(string $name): ViewMendConnection
    {
        $connections = $this->config['connections'] ?? null;
        if (!is_array($connections)) {
            throw MalformedConfigurationException::at('viewmend.connections', 'an array');
        }

        if (!array_key_exists($name, $connections) || $connections[$name] === null) {
            throw MissingConnectionException::forName($name);
        }

        $config = $connections[$name];
        if (!is_array($config)) {
            throw MalformedConfigurationException::at(
                sprintf('viewmend.connections.%s', $name),
                'an array',
            );
        }

        $token = $this->token($name, $config['token'] ?? null);
        $siteTracker = $config['site_tracker'] ?? null;
        $factory = $this->factory;

        return new ViewMendConnection(
            name: $name,
            clientResolver: static fn (): ViewMend => $factory->make($name, $token),
            siteTrackerIntegrationIdResolver: static fn (): string => self::integrationId(
                $name,
                $siteTracker,
            ),
        );
    }

    private function token(string $name, #[\SensitiveParameter] mixed $token): string
    {
        if ($token === null || (is_string($token) && trim($token) === '')) {
            throw MissingTokenException::forConnection($name);
        }

        if (!is_string($token)) {
            throw MalformedConfigurationException::at(
                sprintf('viewmend.connections.%s.token', $name),
                'a non-empty string',
            );
        }

        if (trim($token) !== $token) {
            throw MalformedConfigurationException::at(
                sprintf('viewmend.connections.%s.token', $name),
                'a non-empty string without surrounding whitespace',
            );
        }

        return $token;
    }

    private static function integrationId(string $name, mixed $siteTracker): string
    {
        if ($siteTracker === null) {
            throw MissingIntegrationIdException::forConnection($name);
        }

        if (!is_array($siteTracker)) {
            throw MalformedConfigurationException::at(
                sprintf('viewmend.connections.%s.site_tracker', $name),
                'an array',
            );
        }

        $integrationId = $siteTracker['integration_id'] ?? null;
        if ($integrationId === null || (is_string($integrationId) && trim($integrationId) === '')) {
            throw MissingIntegrationIdException::forConnection($name);
        }

        if (!is_string($integrationId)) {
            throw MalformedConfigurationException::at(
                sprintf('viewmend.connections.%s.site_tracker.integration_id', $name),
                'a non-empty string',
            );
        }

        if (trim($integrationId) !== $integrationId) {
            throw MalformedConfigurationException::at(
                sprintf('viewmend.connections.%s.site_tracker.integration_id', $name),
                'a non-empty string without surrounding whitespace',
            );
        }

        return $integrationId;
    }
}

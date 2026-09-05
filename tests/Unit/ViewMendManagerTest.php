<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use ViewMend\Laravel\Exception\MalformedConfigurationException;
use ViewMend\Laravel\Exception\MissingConnectionException;
use ViewMend\Laravel\Exception\MissingIntegrationIdException;
use ViewMend\Laravel\Exception\MissingTokenException;
use ViewMend\Laravel\Tests\Support\CountingClientFactory;
use ViewMend\Laravel\ViewMendManager;

final class ViewMendManagerTest extends TestCase
{
    public function testCronReusesTheCachedClientAndDoesNotResolveSiteTrackerConfiguration(): void
    {
        $factory = new CountingClientFactory();
        $manager = new ViewMendManager(self::tokenOnlyConfig(), $factory);

        $manager->cron();
        $manager->connection()->cron();
        $manager->client();
        $manager->connection('secondary')->cron();

        self::assertSame(['default', 'secondary'], $factory->connections);
        self::assertSame(0, $factory->http->requests);
    }

    public function testTokenOnlyDefaultConnectionCanReturnACachedSdkClient(): void
    {
        $factory = new CountingClientFactory();
        $manager = new ViewMendManager(self::tokenOnlyConfig(), $factory);

        $connection = $manager->connection();

        self::assertSame([], $factory->connections);
        self::assertSame($connection->client(), $connection->client());
        self::assertSame($connection->client(), $manager->client());
        self::assertSame(['default'], $factory->connections);
        self::assertSame(0, $factory->http->requests);
    }

    public function testTokenOnlyNamedConnectionCanReturnACachedSdkClient(): void
    {
        $factory = new CountingClientFactory();
        $manager = new ViewMendManager(self::tokenOnlyConfig(), $factory);

        $connection = $manager->connection('secondary');

        self::assertSame([], $factory->connections);
        self::assertSame($connection->client(), $connection->client());
        self::assertSame(['secondary'], $factory->connections);
        self::assertSame(0, $factory->http->requests);
    }

    public function testTokenOnlyConnectionRequiresIntegrationOnlyWhenIntegrationIdIsRequested(): void
    {
        $factory = new CountingClientFactory();
        $connection = (new ViewMendManager(self::tokenOnlyConfig(), $factory))->connection();

        $connection->client();
        self::assertSame(['default'], $factory->connections);

        foreach ([$connection->siteTrackerIntegrationId(...), $connection->siteTracker(...)] as $resolve) {
            try {
                $resolve();
                self::fail('Expected missing Site Tracker configuration to fail.');
            } catch (MissingIntegrationIdException $exception) {
                self::assertStringContainsString(
                    'viewmend.connections.default.site_tracker.integration_id',
                    $exception->getMessage(),
                );
            }
        }

        self::assertSame(['default'], $factory->connections);
        self::assertSame(0, $factory->http->requests);
    }

    public function testTokenOnlyConnectionRequiresIntegrationBeforeConstructingSiteTrackerClient(): void
    {
        $factory = new CountingClientFactory();
        $connection = (new ViewMendManager(self::tokenOnlyConfig(), $factory))->connection();

        try {
            $connection->siteTracker();
            self::fail('Expected Site Tracker configuration resolution to fail.');
        } catch (MissingIntegrationIdException $exception) {
            self::assertStringContainsString(
                'viewmend.connections.default.site_tracker.integration_id',
                $exception->getMessage(),
            );
        }

        self::assertSame([], $factory->connections);
        self::assertSame(0, $factory->http->requests);
    }

    public function testMalformedSiteTrackerConfigurationIsLazyForBothAccessors(): void
    {
        $config = self::tokenOnlyConfig();
        $config['connections']['default']['site_tracker'] = 'invalid';
        $factory = new CountingClientFactory();
        $connection = (new ViewMendManager($config, $factory))->connection();

        $connection->client();
        self::assertSame(['default'], $factory->connections);

        try {
            $connection->siteTrackerIntegrationId();
            self::fail('Expected malformed Site Tracker configuration to fail.');
        } catch (MalformedConfigurationException $exception) {
            self::assertStringContainsString(
                'viewmend.connections.default.site_tracker',
                $exception->getMessage(),
            );
        }

        try {
            $connection->siteTracker();
            self::fail('Expected malformed Site Tracker configuration to fail.');
        } catch (MalformedConfigurationException $exception) {
            self::assertStringContainsString(
                'viewmend.connections.default.site_tracker',
                $exception->getMessage(),
            );
        }

        $freshFactory = new CountingClientFactory();
        $fresh = (new ViewMendManager($config, $freshFactory))->connection();
        try {
            $fresh->siteTracker();
            self::fail('Expected malformed Site Tracker configuration to fail.');
        } catch (MalformedConfigurationException $exception) {
            self::assertStringContainsString(
                'viewmend.connections.default.site_tracker',
                $exception->getMessage(),
            );
        }

        self::assertSame([], $freshFactory->connections);
        self::assertSame(0, $freshFactory->http->requests);
    }

    public function testClientsAreConstructedLazilyAndCachedPerConnection(): void
    {
        $factory = new CountingClientFactory();
        $manager = new ViewMendManager($this->config(), $factory);

        self::assertSame([], $factory->connections);
        $default = $manager->connection();
        self::assertSame([], $factory->connections);
        self::assertSame('default', $default->name());
        self::assertSame('tracker-default', $default->siteTrackerIntegrationId());

        self::assertSame($default->client(), $default->client());
        self::assertSame(['default'], $factory->connections);
        self::assertSame($default->client(), $manager->client());

        $secondary = $manager->connection('secondary');
        self::assertSame(['default'], $factory->connections);
        $secondary->siteTracker();
        self::assertSame(['default', 'secondary'], $factory->connections);
        self::assertSame($secondary, $manager->connection('secondary'));
        self::assertSame(0, $factory->http->requests);
    }

    public function testDefaultSiteTrackerUsesDefaultConnection(): void
    {
        $factory = new CountingClientFactory();
        $manager = new ViewMendManager($this->config(), $factory);

        self::assertInstanceOf(
            \ViewMend\SiteTracker\SiteTrackerClient::class,
            $manager->siteTracker(),
        );
        self::assertSame(['default'], $factory->connections);
        self::assertSame(0, $factory->http->requests);
    }

    /**
     * @param array<mixed> $config
     * @param callable(ViewMendManager): mixed $resolve
     * @param class-string<Throwable> $exception
     */
    #[DataProvider('invalidConfigurations')]
    public function testConfigurationFailuresAreSpecificAndSafe(
        array $config,
        callable $resolve,
        string $exception,
        string $key,
    ): void {
        $secret = 'sensitive-exception-test-value';
        $config['unused_secret'] = $secret;
        $manager = new ViewMendManager($config, new CountingClientFactory());

        try {
            $resolve($manager);
            self::fail('Expected configuration resolution to fail.');
        } catch (Throwable $throwable) {
            self::assertInstanceOf($exception, $throwable);
            self::assertStringContainsString($key, $throwable->getMessage());
            self::assertStringNotContainsString($secret, $throwable->getMessage());
        }
    }

    /** @return iterable<string, array{array<mixed>, callable(ViewMendManager): mixed, class-string<Throwable>, string}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'missing default name' => [
            ['connections' => []],
            static fn (ViewMendManager $manager) => $manager->connection(),
            MalformedConfigurationException::class,
            'viewmend.default',
        ];
        yield 'blank default name' => [
            ['default' => ' ', 'connections' => []],
            static fn (ViewMendManager $manager) => $manager->connection(),
            MalformedConfigurationException::class,
            'viewmend.default',
        ];
        yield 'connections is not an array' => [
            ['default' => 'default', 'connections' => 'invalid'],
            static fn (ViewMendManager $manager) => $manager->connection(),
            MalformedConfigurationException::class,
            'viewmend.connections',
        ];
        yield 'connection is absent' => [
            ['default' => 'default', 'connections' => []],
            static fn (ViewMendManager $manager) => $manager->connection(),
            MissingConnectionException::class,
            'viewmend.connections.default',
        ];
        yield 'connection is malformed' => [
            ['default' => 'default', 'connections' => ['default' => 'invalid']],
            static fn (ViewMendManager $manager) => $manager->connection(),
            MalformedConfigurationException::class,
            'viewmend.connections.default',
        ];
        yield 'token is absent' => [
            self::connectionConfig(null, 'tracker-id'),
            static fn (ViewMendManager $manager) => $manager->connection(),
            MissingTokenException::class,
            'viewmend.connections.default.token',
        ];
        yield 'token is blank' => [
            self::connectionConfig(' ', 'tracker-id'),
            static fn (ViewMendManager $manager) => $manager->connection(),
            MissingTokenException::class,
            'viewmend.connections.default.token',
        ];
        yield 'token has wrong type' => [
            self::connectionConfig(123, 'tracker-id'),
            static fn (ViewMendManager $manager) => $manager->connection(),
            MalformedConfigurationException::class,
            'viewmend.connections.default.token',
        ];
        yield 'token has surrounding whitespace' => [
            self::connectionConfig(' token', 'tracker-id'),
            static fn (ViewMendManager $manager) => $manager->connection(),
            MalformedConfigurationException::class,
            'viewmend.connections.default.token',
        ];
        yield 'site tracker config is absent' => [
            ['default' => 'default', 'connections' => ['default' => ['token' => 'token']]],
            static fn (ViewMendManager $manager) => $manager->connection()->siteTrackerIntegrationId(),
            MissingIntegrationIdException::class,
            'viewmend.connections.default.site_tracker.integration_id',
        ];
        yield 'site tracker config is malformed' => [
            ['default' => 'default', 'connections' => ['default' => [
                'token' => 'token',
                'site_tracker' => 'invalid',
            ]]],
            static fn (ViewMendManager $manager) => $manager->connection()->siteTrackerIntegrationId(),
            MalformedConfigurationException::class,
            'viewmend.connections.default.site_tracker',
        ];
        yield 'integration is absent' => [
            self::connectionConfig('token', null),
            static fn (ViewMendManager $manager) => $manager->connection()->siteTrackerIntegrationId(),
            MissingIntegrationIdException::class,
            'viewmend.connections.default.site_tracker.integration_id',
        ];
        yield 'integration has wrong type' => [
            self::connectionConfig('token', 123),
            static fn (ViewMendManager $manager) => $manager->connection()->siteTrackerIntegrationId(),
            MalformedConfigurationException::class,
            'viewmend.connections.default.site_tracker.integration_id',
        ];
        yield 'integration has surrounding whitespace' => [
            self::connectionConfig('token', ' tracker-id'),
            static fn (ViewMendManager $manager) => $manager->connection()->siteTrackerIntegrationId(),
            MalformedConfigurationException::class,
            'viewmend.connections.default.site_tracker.integration_id',
        ];
        yield 'explicit name is blank' => [
            self::connectionConfig('token', 'tracker-id'),
            static fn (ViewMendManager $manager) => $manager->connection(' '),
            MalformedConfigurationException::class,
            'viewmend.default',
        ];
    }

    public function testDebugOutputRedactsConfigurationToken(): void
    {
        $secret = 'sensitive-debug-test-value';
        $config = self::connectionConfig($secret, 'tracker-id');
        $manager = new ViewMendManager($config, new CountingClientFactory());
        $connection = $manager->connection();
        $client = $connection->client();

        ob_start();
        var_dump($manager, $connection, $client);
        $debug = ob_get_clean();

        self::assertIsString($debug);
        self::assertStringNotContainsString($secret, $debug);
        self::assertStringContainsString('[REDACTED]', $debug);
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return [
            'default' => 'default',
            'connections' => [
                'default' => [
                    'token' => 'test-default-token',
                    'site_tracker' => ['integration_id' => 'tracker-default'],
                ],
                'secondary' => [
                    'token' => 'test-secondary-token',
                    'site_tracker' => ['integration_id' => 'tracker-secondary'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function connectionConfig(mixed $token, mixed $integration): array
    {
        return [
            'default' => 'default',
            'connections' => [
                'default' => [
                    'token' => $token,
                    'site_tracker' => ['integration_id' => $integration],
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     default: string,
     *     connections: array{
     *         default: array{token: string},
     *         secondary: array{token: string}
     *     }
     * }
     */
    private static function tokenOnlyConfig(): array
    {
        return [
            'default' => 'default',
            'connections' => [
                'default' => ['token' => 'test-default-token'],
                'secondary' => ['token' => 'test-secondary-token'],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Tests\Feature;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;
use ViewMend\Laravel\Contracts\ClientFactoryContract;
use ViewMend\Laravel\Contracts\ViewMendManagerContract;
use ViewMend\Laravel\Facades\ViewMend as ViewMendFacade;
use ViewMend\Laravel\Tests\Support\CountingClientFactory;
use ViewMend\Laravel\Tests\TestCase;
use ViewMend\Laravel\ViewMendManager;
use ViewMend\Laravel\ViewMendServiceProvider;

final class ServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        ViewMendFacade::clearResolvedInstances();

        parent::tearDown();
    }

    public function testContractAndFacadeResolveTheSameSingleton(): void
    {
        $manager = $this->application()->make(ViewMendManagerContract::class);

        self::assertInstanceOf(ViewMendManager::class, $manager);
        self::assertSame($manager, $this->application()->make(ViewMendManagerContract::class));
        self::assertSame($manager, ViewMendFacade::getFacadeRoot());
        self::assertSame('default', ViewMendFacade::connection()->name());
    }

    public function testProviderMergesConfigurationWithoutOverwritingApplicationValues(): void
    {
        $config = $this->application()->make(ConfigRepository::class);
        self::assertSame('default', $config->get('viewmend.default'));
        self::assertSame(
            'test-default-token',
            $config->get('viewmend.connections.default.token'),
        );
        $packageConfig = require __DIR__ . '/../../config/viewmend.php';
        self::assertIsArray($packageConfig);
        self::assertArrayHasKey('connections', $packageConfig);
    }

    public function testConfigurationIsPublishableUnderDocumentedTag(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            ViewMendServiceProvider::class,
            'viewmend-config',
        );

        self::assertSame(
            $this->application()->configPath('viewmend.php'),
            $paths[dirname(__DIR__, 2) . '/src/../config/viewmend.php'] ?? null,
        );
    }

    public function testProviderAndUnrelatedResolutionDoNotConstructAClientOrSendTraffic(): void
    {
        $factory = new CountingClientFactory();
        $this->application()->instance(ClientFactoryContract::class, $factory);

        self::assertSame(0, $this->command('list')->run());
        self::assertSame([], $factory->connections);
        self::assertSame(0, $factory->http->requests);

        $manager = $this->application()->make(ViewMendManagerContract::class);

        self::assertSame([], $factory->connections);
        self::assertSame(0, $factory->http->requests);

        $manager->connection();
        self::assertSame([], $factory->connections);
        self::assertSame(0, $factory->http->requests);

        $manager->siteTracker();
        self::assertSame(['default'], $factory->connections);
        self::assertSame(0, $factory->http->requests);
    }

    public function testConfigurationArrayCanBeExportedAndRehydrated(): void
    {
        $exported = var_export(self::validConfig(), true);
        $cached = eval(sprintf('return %s;', $exported));
        self::assertIsArray($cached);

        $manager = new ViewMendManager($cached, new CountingClientFactory());

        self::assertSame('tracker-default', $manager->connection()->siteTrackerIntegrationId());
    }

    public function testDeploymentCommandIsRegisteredInConsole(): void
    {
        $this->command('list')
            ->expectsOutputToContain('viewmend:deployment')
            ->assertExitCode(0);
    }
}

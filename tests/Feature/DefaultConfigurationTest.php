<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Tests\Feature;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Orchestra\Testbench\TestCase;
use ViewMend\Laravel\ViewMendServiceProvider;

final class DefaultConfigurationTest extends TestCase
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [ViewMendServiceProvider::class];
    }

    public function testProviderMergesPackageDefaults(): void
    {
        if ($this->app === null) {
            self::fail('The Testbench application is not available.');
        }

        $config = $this->app->make(ConfigRepository::class);

        self::assertSame('default', $config->get('viewmend.default'));
        $connections = $config->get('viewmend.connections', []);
        self::assertIsArray($connections);
        self::assertArrayHasKey('default', $connections);
        self::assertNull($config->get('viewmend.connections.default.token'));
        self::assertNull($config->get('viewmend.connections.default.api_base_url'));
        self::assertNull($config->get('viewmend.connections.default.site_tracker.integration_id'));
    }
}

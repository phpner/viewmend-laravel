<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Tests;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Application;
use Illuminate\Testing\PendingCommand;
use LogicException;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use ViewMend\Laravel\ViewMendServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [ViewMendServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->make(ConfigRepository::class)->set('viewmend', self::validConfig());
    }

    protected function application(): Application
    {
        if (!$this->app instanceof Application) {
            throw new LogicException('The Testbench application is not available.');
        }

        return $this->app;
    }

    /** @param array<string, mixed> $parameters */
    protected function command(string $name, array $parameters = []): PendingCommand
    {
        $command = $this->artisan($name, $parameters);
        if (!$command instanceof PendingCommand) {
            throw new LogicException('Expected a pending Artisan command.');
        }

        return $command;
    }

    /** @return array<string, mixed> */
    protected static function validConfig(): array
    {
        return [
            'default' => 'default',
            'connections' => [
                'default' => [
                    'token' => 'test-default-token',
                    'site_tracker' => [
                        'integration_id' => 'tracker-default',
                    ],
                ],
                'secondary' => [
                    'token' => 'test-secondary-token',
                    'site_tracker' => [
                        'integration_id' => 'tracker/secondary',
                    ],
                ],
            ],
        ];
    }
}

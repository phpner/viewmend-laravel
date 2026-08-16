<?php

declare(strict_types=1);

namespace ViewMend\Laravel;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use ViewMend\Laravel\Commands\SendDeploymentCommand;
use ViewMend\Laravel\Contracts\ClientFactoryContract;
use ViewMend\Laravel\Contracts\ViewMendManagerContract;
use ViewMend\Laravel\Exception\MalformedConfigurationException;

final class ViewMendServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/viewmend.php', 'viewmend');

        $this->app->singleton(ClientFactoryContract::class, DefaultClientFactory::class);
        $this->app->singleton(
            ViewMendManagerContract::class,
            static function (Application $app): ViewMendManagerContract {
                $config = $app->make(ConfigRepository::class)->get('viewmend');
                if (!is_array($config)) {
                    throw MalformedConfigurationException::at('viewmend', 'an array');
                }

                return new ViewMendManager(
                    $config,
                    $app->make(ClientFactoryContract::class),
                );
            },
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/viewmend.php' => $this->app->configPath('viewmend.php'),
        ], 'viewmend-config');

        if ($this->app->runningInConsole()) {
            $this->commands([SendDeploymentCommand::class]);
        }
    }
}

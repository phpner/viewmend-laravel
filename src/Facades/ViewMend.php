<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Facades;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Facade;
use LogicException;
use ViewMend\Cron\CronClient;
use ViewMend\Laravel\Contracts\ViewMendManagerContract;
use ViewMend\Laravel\Testing\ViewMendFake;

/**
 * @method static \ViewMend\Laravel\ViewMendConnection connection(?string $name = null)
 * @method static \ViewMend\ViewMend client()
 * @method static \ViewMend\SiteTracker\SiteTrackerClient siteTracker()
 *
 * @see ViewMendManagerContract
 */
final class ViewMend extends Facade
{
    public static function cron(): CronClient
    {
        return static::connection()->cron();
    }

    public static function fake(): ViewMendFake
    {
        $app = static::getFacadeApplication();
        if ($app === null) {
            throw new LogicException('The ViewMend facade has no application container.');
        }

        $config = $app->make(ConfigRepository::class)->get('viewmend');
        if (!is_array($config)) {
            $config = [];
        }

        $fake = new ViewMendFake($config);
        $app->instance(ViewMendManagerContract::class, $fake);
        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return ViewMendManagerContract::class;
    }
}

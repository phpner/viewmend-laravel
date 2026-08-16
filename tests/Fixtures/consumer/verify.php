<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Application;
use ViewMend\Laravel\Contracts\ViewMendManagerContract;
use ViewMend\Laravel\Facades\ViewMend as ViewMendFacade;
use ViewMend\Laravel\Testing\RecordedEvent;
use ViewMend\Laravel\ViewMendManager;
use ViewMend\Laravel\ViewMendServiceProvider;

require __DIR__ . '/vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (!$app->configurationIsCached()) {
    throw new RuntimeException('The consumer application did not load cached configuration.');
}

$packages = require __DIR__ . '/bootstrap/cache/packages.php';
if (!is_array($packages)) {
    throw new RuntimeException('Laravel did not generate a valid package manifest.');
}

$package = $packages['viewmend/laravel'] ?? null;
if (!is_array($package)) {
    throw new RuntimeException('Composer package discovery omitted the ViewMend package.');
}

$providers = $package['providers'] ?? null;
if (!is_array($providers) || !in_array(ViewMendServiceProvider::class, $providers, true)) {
    throw new RuntimeException('Composer package discovery did not register the ViewMend provider.');
}

$container = $app->make(Container::class);
$manager = $container->get(ViewMendManagerContract::class);
if (!$manager instanceof ViewMendManager) {
    throw new RuntimeException('The ViewMend manager contract did not resolve.');
}

$connection = $manager->connection();
if ($connection->name() !== 'default') {
    throw new RuntimeException('The cached default ViewMend connection did not resolve.');
}
$connection->client();
if ($connection->siteTrackerIntegrationId() !== 'consumer-smoke-integration') {
    throw new RuntimeException('The cached Site Tracker integration ID did not resolve.');
}

$fake = ViewMendFacade::fake();
$result = ViewMendFacade::siteTracker()
    ->events()
    ->deployment('consumer-smoke-event', 'Consumer smoke deployment')
    ->site('https://example.com')
    ->page('https://example.com/pricing')
    ->environment('testing')
    ->send();

if ($result->duplicate) {
    throw new RuntimeException('The consumer fake unexpectedly returned a duplicate delivery.');
}

$fake->assertSent(
    'deployment',
    static fn (RecordedEvent $event): bool => $event->id === 'consumer-smoke-event'
        && $event->connection === 'default'
        && $event->integrationId === 'consumer-smoke-integration'
        && $event->site === 'https://example.com'
        && $event->hasPage('https://example.com/pricing')
        && $event->environment === 'testing',
);

fwrite(STDOUT, sprintf(
    "Consumer smoke passed on Laravel %s.\n",
    $app->version(),
));

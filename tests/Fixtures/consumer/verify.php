<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Application;
use ViewMend\Laravel\Contracts\ViewMendManagerContract;
use ViewMend\Laravel\Facades\ViewMend as ViewMendFacade;
use ViewMend\Laravel\Testing\RecordedEvent;
use ViewMend\Laravel\Testing\RecordedRequest;
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
ob_start();
var_dump($connection->client());
$clientDebug = ob_get_clean();
if (!is_string($clientDebug) || !str_contains($clientDebug, 'http://127.0.0.1:8079/api/v1')) {
    throw new RuntimeException('The cached API base URL was not applied by the default factory.');
}
$manager->cron();
if ($connection->siteTrackerIntegrationId() !== 'consumer-smoke-integration') {
    throw new RuntimeException('The cached Site Tracker integration ID did not resolve.');
}

$fake = ViewMendFacade::fake();
$fake->respondNext(['error' => ['code' => 'registration_not_found']], 404);
if (ViewMendFacade::cron()->current() !== null) {
    throw new RuntimeException('The consumer Cron fake did not return an absent registration.');
}
$fake->assertRequestSent(static fn (RecordedRequest $request): bool =>
    $request->connection === 'default'
    && $request->method === 'GET'
    && $request->path === '/api/v1/cron/registration');

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

/** @return array<string, mixed> */
$viewMendFixture = static function (string $name): array {
    $body = file_get_contents(__DIR__ . '/site-tracker-' . $name . '.json');
    if ($body === false) {
        throw new RuntimeException('Could not read a Site Tracker fixture.');
    }
    $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid Site Tracker fixture.');
    }
    $data = [];
    foreach ($decoded as $key => $value) {
        if (!is_string($key)) {
            throw new RuntimeException('Invalid Site Tracker fixture key.');
        }
        $data[$key] = $value;
    }

    return $data;
};

$fake->respondNext($viewMendFixture('dashboard'));
$dashboard = ViewMendFacade::siteTracker()->dashboard(device: 'mobile');
if ($dashboard->scope->device !== 'mobile' || $dashboard->latestCheck !== null) {
    throw new RuntimeException('The consumer did not map the Site Tracker dashboard response.');
}
$fake->respondNext($viewMendFixture('resources'));
$resources = ViewMendFacade::siteTracker()->resources(
    '123e4567-e89b-42d3-a456-426614174001',
    'other',
    page: 3,
    perPage: 300,
);
if ($resources->pagination->page !== 3 || $resources->items !== []) {
    throw new RuntimeException('The consumer did not map the Site Tracker resource response.');
}

fwrite(STDOUT, sprintf(
    "Consumer smoke passed on Laravel %s.\n",
    $app->version(),
));

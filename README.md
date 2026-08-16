# ViewMend Laravel SDK

The official Laravel SDK for the [ViewMend website monitoring platform](https://viewmend.com/). This package adapts the framework-agnostic [`viewmend/sdk`](https://packagist.org/packages/viewmend/sdk) to Laravel with container bindings, named connections, a facade, an Artisan command, and a network-free testing fake.

## Available modules

### Site Tracker Events

Site Tracker Events is the first available module. Laravel applications can report deployments, content updates, cache clears, and maintenance activity so ViewMend can connect those changes with subsequent website checks. Learn more about [ViewMend Site Tracker for website change monitoring](https://viewmend.com/site-tracker).

## Requirements

- PHP 8.3, 8.4, or 8.5
- Laravel 12 or 13

## Installation

```bash
composer require viewmend/laravel
```

Laravel discovers the service provider automatically. Every connection needs an API token:

```dotenv
VIEWMEND_API_TOKEN=your-api-token
```

Site Tracker calls, including the deployment command, additionally need a Site Tracker integration ID:

```dotenv
VIEWMEND_SITE_TRACKER_INTEGRATION_ID=your-integration-id
```

The integration ID is not required for general access through `$viewMend->client()` or `$viewMend->connection('name')->client()`. The optional `VIEWMEND_CONNECTION` selects a different configured default connection.

## Quick start

Inject the package contract and use the public SDK builders directly:

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use ViewMend\Laravel\Contracts\ViewMendManagerContract;
use ViewMend\SiteTracker\Response\DeliveryResult;

final readonly class ReportDeployment
{
    public function __construct(private ViewMendManagerContract $viewMend)
    {
    }

    public function handle(string $eventId): DeliveryResult
    {
        return $this->viewMend
            ->siteTracker()
            ->events()
            ->deployment($eventId, 'Application deployed')
            ->site('https://example.com')
            ->page('https://example.com/')
            ->environment('production')
            ->send();
    }
}
```

Creating or enriching a builder does not perform a request. Delivery occurs only when `send()` is called, or when the Artisan command below reaches its delivery step.

The facade is an optional alternative:

```php
use ViewMend\Laravel\Facades\ViewMend;

$result = ViewMend::siteTracker()
    ->events()
    ->cacheCleared('cache-2026-08-16', 'Application cache cleared')
    ->send();
```

## Deployment pipelines

ViewMend does not infer deployments from Laravel. Call the command explicitly from the successful part of your deployment pipeline:

```bash
php artisan viewmend:deployment \
  --event-id="deploy-${GITHUB_SHA}" \
  --title="Production deployment" \
  --site="https://example.com" \
  --page="https://example.com/" \
  --page="https://example.com/pricing" \
  --environment="production" \
  --reference="${GITHUB_SERVER_URL}/${GITHUB_REPOSITORY}/actions/runs/${GITHUB_RUN_ID}"
```

`--event-id` is required and is never generated. Reusing the same stable ID lets the ViewMend API report a duplicate delivery safely when a deployment step is retried. Confirmed accepted and duplicate deliveries exit with code `0`; configuration or delivery failures return a non-zero code. The command never prints the API token.

## Named connections

Publish the configuration when you need additional connections:

```bash
php artisan vendor:publish --tag=viewmend-config
```

```php
// config/viewmend.php
'default' => env('VIEWMEND_CONNECTION', 'default'),

'connections' => [
    'default' => [
        'token' => env('VIEWMEND_API_TOKEN'),
        'site_tracker' => [
            'integration_id' => env('VIEWMEND_SITE_TRACKER_INTEGRATION_ID'),
        ],
    ],
    'secondary' => [
        'token' => env('VIEWMEND_SECONDARY_API_TOKEN'),
        'site_tracker' => [
            'integration_id' => env('VIEWMEND_SECONDARY_SITE_TRACKER_INTEGRATION_ID'),
        ],
    ],
],
```

```php
$result = $viewMend
    ->connection('secondary')
    ->siteTracker()
    ->events()
    ->contentUpdate('content-123', 'Pricing updated')
    ->send();
```

The `site_tracker` block is optional until `siteTracker()` or `siteTrackerIntegrationId()` is called on that connection. A token-only connection can still expose the shared SDK client for other ViewMend modules.

Environment access stays in the configuration file, so the package works with `php artisan config:cache`.

## Error handling

Invalid Laravel configuration throws a specific exception under `ViewMend\Laravel\Exception`. SDK validation, API, and transport failures keep their original type and extend `ViewMend\Exception\ViewMendException`:

```php
use ViewMend\Exception\ViewMendException;
use ViewMend\Laravel\Exception\ConfigurationException;

try {
    $result = $viewMend->siteTracker()->events()
        ->deployment('deploy-123', 'Production deployment')
        ->send();
} catch (ConfigurationException $exception) {
    // Fix the named Laravel configuration key in the exception message.
} catch (ViewMendException $exception) {
    // Decide how this application handles a failed API delivery.
}
```

Configuration errors name the affected connection and configuration key without including secret values. The package does not log requests, responses, or authorization headers.

## Testing

The fake replaces both facade and contract resolution. It uses the real SDK builders and records only events that reach `send()`:

```php
use ViewMend\Laravel\Facades\ViewMend;
use ViewMend\Laravel\Testing\RecordedEvent;

$fake = ViewMend::fake();

$serviceUnderTest->publish();

$fake->assertSent(
    'deployment',
    fn (RecordedEvent $event): bool => $event->id === 'deploy-123'
        && $event->connection === 'default'
        && $event->integrationId === 'tracker-default'
        && $event->site === 'https://example.com'
        && $event->hasPage('https://example.com/pricing')
        && $event->environment === 'production',
);
```

Call `ViewMend::fake()` before resolving the service under test. The fake performs no network request; it verifies the SDK event sent by application code, not remote backend acceptance, authentication, or retry behavior.

## Advanced usage

Applications can replace `ViewMend\Laravel\Contracts\ClientFactoryContract` before the manager is first resolved, including to use the SDK's public `ViewMend::withPsr18()` factory. See [architecture and extension points](docs/architecture.md).

## License and support

This package is available under the [MIT License](LICENSE). Security reports are handled according to [SECURITY.md](SECURITY.md). For product support, contact [support@viewmend.com](mailto:support@viewmend.com).

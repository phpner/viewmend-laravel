# Architecture

## Package boundary

`viewmend/laravel` is a Laravel adapter over `viewmend/sdk`. Authentication, HTTP transport, request mapping, safe retries, DTOs, API exceptions, and Site Tracker event builders remain owned by the SDK. The adapter uses only the SDK's public API and never imports `ViewMend\Internal` classes.

The package deliberately has no routes, controllers, views, migrations, frontend assets, incoming webhooks, global event listeners, HTTP client, or delivery queue.

## Runtime flow

`ViewMendServiceProvider` merges configuration during `register()`, registers singleton bindings, publishes configuration during `boot()`, and registers the deployment command only in console applications. None of those operations create an SDK client or perform I/O.

`ViewMendManagerContract` is the container-facing API. `ViewMendManager` validates one named connection when it is requested and caches a `ViewMendConnection` per name. The connection lazily creates one SDK `ViewMend` client on its first `client()` or `siteTracker()` call. Calling an SDK event builder remains side-effect free until `send()`.

The connection wrapper exists only to bind a configured Site Tracker integration ID to a named SDK client. From `siteTracker()` onward, callers receive the public SDK types `SiteTrackerClient`, `Events`, `PendingEvent`, and `DeliveryResult`; the adapter does not wrap the fluent event API.

## Configuration

The supported structure is intentionally small:

```php
[
    'default' => 'default',
    'connections' => [
        'default' => [
            'token' => '...',
            'site_tracker' => [
                'integration_id' => '...',
            ],
        ],
    ],
]
```

The adapter does not expose an API base URL or retry options because its standard factory cannot honestly apply those settings through `ViewMend::client()`. Environment lookups exist only in `config/viewmend.php`, which makes the resolved array compatible with Laravel configuration caching.

Connection names, tokens, nested Site Tracker configuration, and integration IDs are validated before client use. Package exceptions identify only the relevant key and connection. Manager and connection debug output omit configuration and client internals; SDK token objects provide their own redacted debug representation.

## Client factory extension point

The default `ClientFactoryContract` implementation calls `ViewMend\ViewMend::client()` and retains all standard SDK transport behavior.

An application that owns a PSR-18 stack can replace the binding in `AppServiceProvider::register()`:

```php
use ViewMend\Laravel\Contracts\ClientFactoryContract;

public function register(): void
{
    $this->app->singleton(ClientFactoryContract::class, ApplicationClientFactory::class);
}
```

The replacement must be registered before `ViewMendManagerContract` is first resolved. Its `make(string $connection, string $token)` method can select infrastructure by connection and return a client created with the SDK's public `ViewMend::withPsr18()` method. The token parameter is marked sensitive and must not be stored, logged, or included in exceptions.

## Testing fake

The SDK fluent classes are final and their constructors are internal. Reimplementing them would duplicate validation and payload behavior. The package fake therefore creates a real SDK client with public `ViewMend::withPsr18()` and a recording PSR-18 client.

When application code calls `send()`, the recorder extracts an allowlisted event snapshot: connection, integration ID, event type, caller-provided event ID, title, site, pages, environment, description, reference, and the decoded public payload. It does not retain the PSR request, raw body, headers, authorization value, or token. A valid synthetic SDK response keeps application return types identical to production.

Assertions occur after `send()`. Creating a builder alone records nothing. This seam tests which event application code attempted to deliver; it does not claim to test authentication, SDK retry policy, or ViewMend backend behavior.

The fake has immediate accepted and duplicate results. `failNext()` accepts only non-retryable 4xx statuses so tests never trigger SDK sleeps or accidentally turn a queued failure into a later success.

## Deployment command and queue decision

`viewmend:deployment` is synchronous by design. A deployment pipeline needs the confirmed `DeliveryResult` to distinguish accepted/duplicate delivery from failure and set its exit code. A random event ID would break safe deduplication, so `--event-id` is mandatory.

No Laravel queue layer is included. The SDK already performs bounded retries for documented safe failures with the identical serialized event and event ID. A second delivery system would obscure the pipeline result, duplicate retry policy, and introduce worker and serialization concerns without improving the command's contract.

## Dependencies

Production dependencies are limited to:

- `viewmend/sdk` for all ViewMend API behavior;
- `illuminate/contracts`, `illuminate/support`, and `illuminate/console` for the container, provider/facade, and command integration;
- `guzzlehttp/psr7`, already present in the SDK transport graph, as the declared PSR-17/PSR-7 implementation used by the public testing fake.

The package does not require `laravel/framework` in production. Testbench supplies complete Laravel 12/13 applications for development tests.

## Possible SDK enhancement

No SDK change is required for this package. A future backward-compatible public event observation or immutable snapshot seam could let framework adapters record a pre-delivery event without decoding the documented wire payload. That seam should not expose the SDK's current internal transport or event objects.

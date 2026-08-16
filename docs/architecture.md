# Architecture

## Package boundary

`viewmend/laravel` is a Laravel adapter over `viewmend/sdk`. Authentication, HTTP transport, request mapping, safe retries, DTOs, API exceptions, and Site Tracker event builders remain owned by the SDK. The adapter uses only the SDK's public API and never imports `ViewMend\Internal` classes.

The package deliberately has no routes, controllers, views, migrations, frontend assets, incoming webhooks, global event listeners, HTTP client, or delivery queue.

## Runtime flow

`ViewMendServiceProvider` merges configuration during `register()`, registers singleton bindings, publishes configuration during `boot()`, and registers the deployment command only in console applications. None of those operations create an SDK client or perform I/O.

`ViewMendManagerContract` is the container-facing API. `ViewMendManager` validates the selected connection and its token when it is requested, then caches a `ViewMendConnection` per name. The connection lazily creates one SDK `ViewMend` client on its first `client()` or successful module access. Calling an SDK event builder remains side-effect free until `send()`.

Site Tracker configuration is a separate lazy concern. A connection resolves and validates `site_tracker.integration_id` only when `siteTrackerIntegrationId()` or `siteTracker()` is called. Token-only connections therefore remain valid shared ViewMend clients for other current or future public SDK modules. A missing or malformed Site Tracker block fails before constructing a Site Tracker client or performing I/O.

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

The connection token is required. The nested `site_tracker` block is optional for general `client()` access and required only for Site Tracker operations on that connection.

The adapter does not expose an API base URL or retry options because its standard factory cannot honestly apply those settings through `ViewMend::client()`. Environment lookups exist only in `config/viewmend.php`, which makes the resolved array compatible with Laravel configuration caching.

Connection names and tokens are validated when the connection is selected. Nested Site Tracker configuration and integration IDs are validated only at the Site Tracker boundary. Package exceptions identify only the relevant key and connection. Manager and connection debug output omit configuration, resolvers, tokens, client internals, and the integration ID value; SDK token objects provide their own redacted debug representation.

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

The package does not require `laravel/framework` in production. Testbench supplies complete Laravel 12/13 applications for development tests. A disposable native Laravel consumer smoke test separately verifies Composer auto-discovery and real cached-configuration bootstrapping without a network request; Testbench's custom configuration bootstrapper is not used as evidence for Laravel `config:cache` behavior.

## Possible SDK enhancement

No SDK change is required for this package. A future backward-compatible public event observation or immutable snapshot seam could let framework adapters record a pre-delivery event without decoding the documented wire payload. That seam should not expose the SDK's current internal transport or event objects.

# Cron in Laravel

Cron uses a dedicated ViewMend connection token with a different scope from a Site Tracker token. Keep it in a separate named connection when the application uses both products:

```php
// config/viewmend.php, inside connections
'cron' => [
    'token' => env('VIEWMEND_CRON_TOKEN'),
    'api_base_url' => env('VIEWMEND_API_BASE_URL'),
],
```

No `site_tracker` configuration is needed for this connection.

```php
use ViewMend\Laravel\Facades\ViewMend;

$cron = ViewMend::connection('cron')->cron();
$registration = $cron->register(
    cron: '*/15 * * * *',
    timezone: 'Europe/London',
    endpointPath: '/api/viewmend/cron',
);

$saved = $cron->current(); // RegistrationResult, or null if not registered
$cron->disable();
```

These are separate operations: call `register()` when saving settings, `current()` when loading them, and `disable()` when pausing the schedule. Each performs a request immediately. Creating the client with `cron()` performs no I/O. Dependency injection exposes the same API through `ViewMendManagerContract`.

The application owns its callback route. Verify the exact request bytes with the public SDK:

```php
$callback = ViewMend::connection('cron')->cron()->verifyCallback(
    $request->headers->all(),
    $request->getContent(),
);
```

For a verification challenge, return `$callback->verificationResponseBody()` as JSON with a successful status. For a run, perform the scheduled work and acknowledge success. Delivery is at least once: persist completed run IDs and use `$callback->runId` to prevent repeated side effects. Keep callback failures as failures. The package installs no route, middleware, scheduler entry, or queue worker.

`TokenScopeException` indicates a Site Tracker token used for Cron; catch it before `AuthenticationException`, its parent. Other validation, registration, and callback exceptions also remain the original SDK types. Use `respondNext()` and `assertRequestSent()` from the [testing fake](../README.md#testing) for network-free registration tests. Signature verification itself remains real SDK behavior.

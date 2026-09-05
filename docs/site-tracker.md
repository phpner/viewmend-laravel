# Site Tracker dashboard and resources

Dashboard and resource reads are included through the required `viewmend/sdk ^1.3` dependency.

Configure the Site Tracker token and integration ID as shown in the [README](../README.md), then inject `ViewMendManagerContract` or use the facade:

```php
use ViewMend\Laravel\Facades\ViewMend;

$tracker = ViewMend::siteTracker();
$dashboard = $tracker->dashboard(device: 'mobile');

$health = $dashboard->summary->healthScore;
$pages = $dashboard->scope->availablePages;
$attention = $dashboard->needsAttention->items;

// Select a page ID from availablePages; omit it for the default page.
$dashboard = $tracker->dashboard(pageId: $pageId, device: 'desktop');
```

For another configured integration, use `ViewMend::connection('secondary')->siteTracker()`. Page IDs are UUIDs; devices are `desktop` and `mobile`.

```php
$runId = $dashboard->latestCheck?->runId;

if ($runId !== null && $dashboard->transfer->available) {
    $resources = $tracker->resources(
        runId: $runId,
        type: 'javascript',
        device: 'desktop',
        page: 1,
        perPage: 50,
    );

    foreach ($resources->items as $resource) {
        // $resource->url, $resource->transferredBytes, $resource->durationMs
    }
}
```

Resource types are `images`, `javascript`, `css`, and `other`; `perPage` accepts 1–300. Each call fetches one page. Use `pagination->lastPage` to decide whether to fetch more. Check `summary->truncated`: additional pages cannot recover resources omitted from the stored inventory.

Both methods make a GET request immediately and return immutable SDK DTOs. A missing page, run, or integration throws `ResourceNotFoundException`; an invalid API query throws `UnprocessableQueryException`. These remain SDK exceptions. Nullable measurements and missing checks remain null, and an empty dashboard is a valid result. The Laravel adapter adds no caching, polling, or automatic requests.

To test application behavior, queue the endpoint's complete JSON response with `ViewMend::fake()->respondNext($response)`. The actual SDK validates and maps that response. Inspect `requests()` or use `assertRequestSent()` to verify the selected integration, device, and pagination.

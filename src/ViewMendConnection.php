<?php

declare(strict_types=1);

namespace ViewMend\Laravel;

use Closure;
use ViewMend\SiteTracker\SiteTrackerClient;
use ViewMend\ViewMend;

final class ViewMendConnection
{
    private ?ViewMend $client = null;

    /** @param Closure(): ViewMend $clientResolver */
    public function __construct(
        private readonly string $name,
        private readonly Closure $clientResolver,
        private readonly string $siteTrackerIntegrationId,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function client(): ViewMend
    {
        return $this->client ??= ($this->clientResolver)();
    }

    public function siteTrackerIntegrationId(): string
    {
        return $this->siteTrackerIntegrationId;
    }

    public function siteTracker(): SiteTrackerClient
    {
        return $this->client()->siteTracker($this->siteTrackerIntegrationId);
    }

    /** @return array{name: string, client: string, siteTrackerIntegrationId: string} */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->name,
            'client' => $this->client === null ? 'not resolved' : ViewMend::class,
            'siteTrackerIntegrationId' => $this->siteTrackerIntegrationId,
        ];
    }
}

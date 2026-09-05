<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Contracts;

use ViewMend\Laravel\ViewMendConnection;
use ViewMend\SiteTracker\SiteTrackerClient;
use ViewMend\ViewMend;

interface ViewMendManagerContract
{
    public function connection(?string $name = null): ViewMendConnection;

    public function client(): ViewMend;

    public function siteTracker(): SiteTrackerClient;
}

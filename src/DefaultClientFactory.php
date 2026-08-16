<?php

declare(strict_types=1);

namespace ViewMend\Laravel;

use ViewMend\Laravel\Contracts\ClientFactoryContract;
use ViewMend\ViewMend;

final class DefaultClientFactory implements ClientFactoryContract
{
    public function make(string $connection, #[\SensitiveParameter] string $token): ViewMend
    {
        return ViewMend::client($token);
    }
}

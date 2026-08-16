<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Contracts;

use ViewMend\ViewMend;

interface ClientFactoryContract
{
    public function make(string $connection, #[\SensitiveParameter] string $token): ViewMend;
}

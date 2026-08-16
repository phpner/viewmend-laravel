<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Exception;

final class MissingIntegrationIdException extends ConfigurationException
{
    public static function forConnection(string $connection): self
    {
        return new self(sprintf(
            'ViewMend connection [%s] requires a Site Tracker integration ID at '
            . '[viewmend.connections.%s.site_tracker.integration_id].',
            $connection,
            $connection,
        ));
    }
}

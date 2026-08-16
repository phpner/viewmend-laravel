<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Exception;

final class MissingConnectionException extends ConfigurationException
{
    public static function forName(string $name): self
    {
        return new self(sprintf(
            'ViewMend connection [%s] is not configured at [viewmend.connections.%s].',
            $name,
            $name,
        ));
    }
}

<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Exception;

final class MissingTokenException extends ConfigurationException
{
    public static function forConnection(string $connection): self
    {
        return new self(sprintf(
            'ViewMend connection [%s] requires a token at [viewmend.connections.%s.token].',
            $connection,
            $connection,
        ));
    }
}

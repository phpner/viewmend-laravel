<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Exception;

final class MalformedConfigurationException extends ConfigurationException
{
    public static function at(string $key, string $expected): self
    {
        return new self(sprintf(
            'ViewMend configuration [%s] must be %s.',
            $key,
            $expected,
        ));
    }
}

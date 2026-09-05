<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Testing;

final readonly class RecordedRequest
{
    /**
     * @param array<int|string, mixed> $query
     * @param array<string, mixed>|null $payload
     */
    public function __construct(
        public string $connection,
        public string $method,
        public string $path,
        public array $query,
        public ?array $payload,
    ) {
    }
}

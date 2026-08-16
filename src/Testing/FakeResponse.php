<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Testing;

final readonly class FakeResponse
{
    private function __construct(
        public int $status,
        public bool $duplicate,
        public string $queueStatus,
    ) {
    }

    public static function accepted(string $queueStatus = 'queued'): self
    {
        return new self(202, false, $queueStatus);
    }

    public static function duplicate(string $queueStatus = 'queued'): self
    {
        return new self(200, true, $queueStatus);
    }

    public static function failure(int $status): self
    {
        return new self($status, false, 'recorded');
    }

    public function isSuccess(): bool
    {
        return in_array($this->status, [200, 202], true);
    }
}

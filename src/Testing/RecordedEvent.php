<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Testing;

final readonly class RecordedEvent
{
    /**
     * @param list<string> $pages
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $connection,
        public string $integrationId,
        public string $type,
        public string $id,
        public string $title,
        public ?string $site,
        public array $pages,
        public ?string $environment,
        public ?string $description,
        public ?string $reference,
        public array $payload,
    ) {
    }

    public function hasPage(string $url): bool
    {
        return in_array($url, $this->pages, true);
    }
}

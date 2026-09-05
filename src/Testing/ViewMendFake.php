<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Testing;

use AssertionError;
use GuzzleHttp\Psr7\Response;
use ViewMend\Cron\CronClient;
use ViewMend\Laravel\Contracts\ViewMendManagerContract;
use ViewMend\Laravel\ViewMendConnection;
use ViewMend\Laravel\ViewMendManager;
use ViewMend\SiteTracker\SiteTrackerClient;
use ViewMend\ViewMend;

final class ViewMendFake implements ViewMendManagerContract
{
    /** @param array<mixed> $config */
    public function __construct(
        array $config,
        private readonly FakeClientFactory $factory = new FakeClientFactory(),
    ) {
        $this->manager = new ViewMendManager($config, $this->factory);
    }

    private readonly ViewMendManager $manager;

    public function connection(?string $name = null): ViewMendConnection
    {
        return $this->manager->connection($name);
    }

    public function siteTracker(): SiteTrackerClient
    {
        return $this->manager->siteTracker();
    }

    public function client(): ViewMend
    {
        return $this->manager->client();
    }

    public function cron(): CronClient
    {
        return $this->manager->cron();
    }

    /** @param array<string, mixed>|null $body */
    public function respondNext(?array $body = null, int $status = 200): self
    {
        if ($status < 200 || $status >= 500 || $status === 429 || ($status >= 300 && $status < 400)) {
            throw new \InvalidArgumentException('An API fake response must use a 2xx or non-retryable 4xx status.');
        }

        $this->factory->queueApiResponse(new Response(
            $status,
            ['Content-Type' => 'application/json'],
            $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ));

        return $this;
    }

    /** @return list<RecordedRequest> */
    public function requests(): array
    {
        return $this->factory->requests();
    }

    /** @param callable(RecordedRequest): bool $callback */
    public function assertRequestSent(callable $callback): void
    {
        foreach ($this->requests() as $request) {
            if ($callback($request)) {
                return;
            }
        }

        throw new AssertionError('No matching ViewMend API request was sent.');
    }

    public function duplicateNext(string $queueStatus = 'queued'): self
    {
        $this->factory->queue(FakeResponse::duplicate($queueStatus));

        return $this;
    }

    public function failNext(int $status = 422): self
    {
        if ($status < 400 || $status === 429 || $status >= 500) {
            throw new \InvalidArgumentException(
                'A fake failure status must be a non-retryable HTTP status between 400 and 499.',
            );
        }

        $this->factory->queue(FakeResponse::failure($status));

        return $this;
    }

    /** @return list<RecordedEvent> */
    public function events(): array
    {
        return $this->factory->events();
    }

    /**
     * @param null|callable(RecordedEvent): bool $callback
     * @return list<RecordedEvent>
     */
    public function sent(string $type, ?callable $callback = null): array
    {
        return array_values(array_filter(
            $this->events(),
            static fn (RecordedEvent $event): bool => $event->type === $type
                && ($callback === null || $callback($event)),
        ));
    }

    /** @param null|callable(RecordedEvent): bool $callback */
    public function assertSent(string $type, ?callable $callback = null): void
    {
        if ($this->sent($type, $callback) === []) {
            throw new AssertionError(sprintf('No matching ViewMend [%s] event was sent.', $type));
        }
    }

    public function assertNothingSent(): void
    {
        if ($this->requests() !== []) {
            throw new AssertionError('Unexpected ViewMend requests were sent.');
        }
    }
}

<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Testing;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use JsonException;
use LogicException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ViewMend\Laravel\Contracts\ClientFactoryContract;
use ViewMend\ViewMend;

final class FakeClientFactory implements ClientFactoryContract
{
    /** @var list<RecordedEvent> */
    private array $events = [];

    /** @var list<FakeResponse> */
    private array $responses = [];

    /** @var list<RecordedRequest> */
    private array $requests = [];

    /** @var list<ResponseInterface> */
    private array $apiResponses = [];

    private int $deliverySequence = 0;

    public function make(string $connection, #[\SensitiveParameter] string $token): ViewMend
    {
        $factory = new HttpFactory();

        return ViewMend::withPsr18(
            token: $token,
            httpClient: $this->httpClient($connection),
            requestFactory: $factory,
            streamFactory: $factory,
        );
    }

    public function queue(FakeResponse $response): void
    {
        $this->responses[] = $response;
    }

    /** @return list<RecordedEvent> */
    public function events(): array
    {
        return $this->events;
    }

    public function queueApiResponse(ResponseInterface $response): void
    {
        $this->apiResponses[] = $response;
    }

    /** @return list<RecordedRequest> */
    public function requests(): array
    {
        return $this->requests;
    }

    private function httpClient(string $connection): ClientInterface
    {
        return new class ($connection, $this) implements ClientInterface {
            public function __construct(
                private readonly string $connection,
                private readonly FakeClientFactory $factory,
            ) {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->factory->handle($this->connection, $request);
            }
        };
    }

    public function handle(string $connection, RequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);
        $body = (string) $request->getBody();
        $payload = $body === '' ? null : $this->decodePayload($body);
        $this->requests[] = new RecordedRequest($connection, $request->getMethod(), $path, $query, $payload);

        if (
            $request->getMethod() !== 'POST'
            || preg_match('~/site-tracker/integrations/[^/]+/events$~', $path) !== 1
        ) {
            return array_shift($this->apiResponses)
                ?? throw new LogicException('Queue an API response with respondNext() before making this request.');
        }

        $event = $this->record($connection, $request);
        $response = array_shift($this->responses) ?? FakeResponse::accepted();

        if (!$response->isSuccess()) {
            return new Response($response->status, [], '{}');
        }

        $deliveryId = sprintf('fake-delivery-%d', ++$this->deliverySequence);
        $status = $response->status;
        $body = json_encode([
            'ok' => true,
            'delivery_id' => $deliveryId,
            'event_id' => sprintf('fake-event-%d', $this->deliverySequence),
            'duplicate' => $response->duplicate,
            'affected_pages' => count($event->pages),
            'ignored_urls' => 0,
            'checks_queued' => count($event->pages),
            'queue_status' => $response->queueStatus,
            'scheduled_for' => null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return new Response($status, ['X-ViewMend-Delivery' => $deliveryId], $body);
    }

    private function record(string $connection, RequestInterface $request): RecordedEvent
    {
        $path = $request->getUri()->getPath();
        $matched = preg_match('~/site-tracker/integrations/([^/]+)/events$~', $path, $matches);
        if ($matched !== 1) {
            throw new LogicException('The ViewMend fake received an unsupported request path.');
        }

        $object = $this->decodePayload((string) $request->getBody());

        $event = new RecordedEvent(
            connection: $connection,
            integrationId: rawurldecode($matches[1]),
            type: $this->requiredString($object, 'event_type'),
            id: $this->requiredString($object, 'event_id'),
            title: $this->requiredString($object, 'title'),
            site: $this->optionalString($object, 'site_url'),
            pages: $this->pages($object),
            environment: $this->optionalString($object, 'environment'),
            description: $this->optionalString($object, 'description'),
            reference: $this->optionalString($object, 'reference_url'),
            payload: $object,
        );

        $this->events[] = $event;

        return $event;
    }

    /** @return array<string, mixed> */
    private function decodePayload(string $body): array
    {
        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new LogicException('The ViewMend fake received invalid JSON.');
        }

        if (!is_array($payload) || array_is_list($payload)) {
            throw new LogicException('The ViewMend fake expected a JSON object.');
        }

        $object = [];
        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                throw new LogicException('The ViewMend fake expected string object keys.');
            }

            $object[$key] = $value;
        }

        return $object;
    }

    /** @param array<string, mixed> $payload */
    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value)) {
            throw new LogicException(sprintf('The ViewMend fake expected [%s] to be a string.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new LogicException(sprintf('The ViewMend fake expected [%s] to be a string.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function pages(array $payload): array
    {
        $pages = $payload['page_urls'] ?? [];
        if (!is_array($pages) || !array_is_list($pages)) {
            throw new LogicException('The ViewMend fake expected [page_urls] to be a list.');
        }

        foreach ($pages as $page) {
            if (!is_string($page)) {
                throw new LogicException('The ViewMend fake expected each page URL to be a string.');
            }
        }

        return $pages;
    }
}

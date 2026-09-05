<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Tests\Feature;

use ViewMend\Exception\ResourceNotFoundException;
use ViewMend\Exception\ValidationException;
use ViewMend\Laravel\Facades\ViewMend;
use ViewMend\Laravel\Testing\RecordedRequest;
use ViewMend\Laravel\Tests\TestCase;
use ViewMend\SiteTracker\Response\DashboardResult;
use ViewMend\SiteTracker\Response\ResourcesResult;

final class SiteTrackerReadTest extends TestCase
{
    protected function tearDown(): void
    {
        ViewMend::clearResolvedInstances();

        parent::tearDown();
    }

    public function testDashboardReturnsTheSdkResponseAndUsesTheNamedIntegration(): void
    {
        $fake = ViewMend::fake();
        $tracker = ViewMend::connection('secondary')->siteTracker();
        $fake->respondNext($this->fixture('dashboard'));
        $result = $tracker->dashboard(device: 'mobile');

        self::assertInstanceOf(DashboardResult::class, $result);
        self::assertSame('mobile', $result->scope->device);
        self::assertNull($result->latestCheck);
        $fake->assertRequestSent(static fn (RecordedRequest $request): bool =>
            $request->connection === 'secondary'
            && $request->method === 'GET'
            && $request->path === '/api/v1/site-tracker/integrations/tracker%2Fsecondary/dashboard'
            && $request->query === ['device' => 'mobile']
            && $request->payload === null);
        self::assertSame([], $fake->events());
    }

    public function testResourcesPreservesTheRunFilterAndPagination(): void
    {
        $fake = ViewMend::fake();
        $tracker = ViewMend::siteTracker();
        $fake->respondNext($this->fixture('resources'));
        $result = $tracker->resources('123e4567-e89b-42d3-a456-426614174001', 'other', page: 3, perPage: 300);

        self::assertInstanceOf(ResourcesResult::class, $result);
        self::assertSame(3, $result->pagination->page);
        self::assertSame(300, $result->pagination->perPage);
        $fake->assertRequestSent(static fn (RecordedRequest $request): bool =>
            $request->connection === 'default'
            && $request->method === 'GET'
            && str_ends_with($request->path, '/runs/123e4567-e89b-42d3-a456-426614174001/resources')
            && $request->query === ['type' => 'other', 'device' => 'desktop', 'page' => '3', 'per_page' => '300']);
        self::assertCount(1, $fake->requests());
    }

    public function testMissingDashboardPreservesTheSdkException(): void
    {
        ViewMend::fake()->respondNext(status: 404);
        $this->expectException(ResourceNotFoundException::class);

        ViewMend::siteTracker()->dashboard();
    }

    public function testInvalidPageIdFailsBeforeARequestIsRecorded(): void
    {
        $fake = ViewMend::fake();

        try {
            ViewMend::siteTracker()->dashboard(pageId: 'not-a-uuid');
            self::fail('An invalid page ID should fail SDK validation.');
        } catch (ValidationException) {
            $fake->assertNothingSent();
            self::assertSame([], $fake->requests());
        }
    }

    /** @return array<string, mixed> */
    private function fixture(string $name): array
    {
        $body = file_get_contents(__DIR__ . '/../Fixtures/site-tracker-' . $name . '.json');
        self::assertIsString($body);
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        $result = [];
        foreach ($data as $key => $value) {
            self::assertIsString($key);
            $result[$key] = $value;
        }

        return $result;
    }
}

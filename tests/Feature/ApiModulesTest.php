<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Tests\Feature;

use AssertionError;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use ViewMend\Cron\CronClient;
use ViewMend\Exception\TokenScopeException;
use ViewMend\Exception\UnexpectedResponseException;
use ViewMend\Laravel\Contracts\ViewMendManagerContract;
use ViewMend\Laravel\Facades\ViewMend;
use ViewMend\Laravel\Testing\RecordedRequest;
use ViewMend\Laravel\Tests\TestCase;

final class ApiModulesTest extends TestCase
{
    protected function tearDown(): void
    {
        ViewMend::clearResolvedInstances();

        parent::tearDown();
    }

    public function testCronWorksWithoutSiteTrackerConfigurationAndConstructsWithoutRequests(): void
    {
        $this->application()->make(ConfigRepository::class)->set('viewmend.connections.default', [
            'token' => 'test-cron-token',
        ]);
        $fake = ViewMend::fake();

        self::assertInstanceOf(CronClient::class, ViewMend::cron());
        self::assertInstanceOf(
            CronClient::class,
            $this->application()->make(ViewMendManagerContract::class)->cron(),
        );
        $fake->assertNothingSent();
        self::assertSame([], $fake->requests());
    }

    public function testCronRegisterReadAndDisableUseTheNamedConnection(): void
    {
        $fake = ViewMend::fake()
            ->respondNext($this->registration(), 201)
            ->respondNext($this->registration())
            ->respondNext(status: 204);
        $cron = ViewMend::connection('secondary')->cron();

        $result = $cron->register('*/15 * * * *', 'Europe/London', '/cron');
        self::assertSame('pending_verification', $result->status);
        self::assertSame('*/15 * * * *', $cron->current()?->cron);
        $cron->disable();

        self::assertSame(['PUT', 'GET', 'DELETE'], array_column($fake->requests(), 'method'));
        $fake->assertRequestSent(static fn (RecordedRequest $request): bool =>
            $request->connection === 'secondary'
            && $request->method === 'PUT'
            && $request->path === '/api/v1/cron/registration'
            && $request->payload === [
                'schedule' => ['cron' => '*/15 * * * *', 'timezone' => 'Europe/London'],
                'endpoint_path' => '/cron',
                'enabled' => true,
            ]);
        self::assertSame([], $fake->events());
        self::assertStringNotContainsString('test-secondary-token', var_export($fake->requests(), true));
    }

    public function testCronMissingRegistrationIsNull(): void
    {
        ViewMend::fake()->respondNext(['error' => ['code' => 'registration_not_found']], 404);

        self::assertNull(ViewMend::cron()->current());
    }

    public function testCronScopeExceptionIsPreserved(): void
    {
        ViewMend::fake()->respondNext(['error' => ['code' => 'token_scope_invalid']], 401);
        $this->expectException(TokenScopeException::class);

        ViewMend::cron()->current();
    }

    public function testUnconfiguredApiResponseFailsWithoutReturningInventedData(): void
    {
        ViewMend::fake();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('respondNext()');

        ViewMend::cron()->current();
    }

    public function testMalformedResponseStillUsesSdkValidation(): void
    {
        ViewMend::fake()->respondNext(['data' => ['invalid' => true]]);
        $this->expectException(UnexpectedResponseException::class);

        ViewMend::cron()->current();
    }

    #[DataProvider('unsafeStatuses')]
    public function testApiFakeRejectsStatusesThatCouldRetryOrRedirect(int $status): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ViewMend::fake()->respondNext(status: $status);
    }

    /** @return iterable<string, array{int}> */
    public static function unsafeStatuses(): iterable
    {
        foreach ([100, 302, 429, 500, 503] as $status) {
            yield (string) $status => [$status];
        }
    }

    public function testAssertNothingSentIncludesReads(): void
    {
        $fake = ViewMend::fake()->respondNext(status: 404);
        ViewMend::cron()->current();
        $this->expectException(AssertionError::class);

        $fake->assertNothingSent();
    }

    public function testApiResponseQueueDoesNotConsumeEventResponses(): void
    {
        $fake = ViewMend::fake()->duplicateNext()->respondNext(status: 404);
        self::assertNull(ViewMend::cron()->current());
        $result = ViewMend::siteTracker()->events()->deployment('deploy-123', 'Deployed')->send();

        self::assertTrue($result->duplicate);
        self::assertCount(2, $fake->requests());
        self::assertCount(1, $fake->events());
    }

    /** @return array<string, mixed> */
    private function registration(): array
    {
        return ['data' => [
            'id' => 'cron_' . str_repeat('c', 26),
            'connection_id' => 'cronconn_' . str_repeat('a', 26),
            'domain' => 'example.com',
            'endpoint_path' => '/cron',
            'endpoint_url' => 'https://example.com/cron',
            'method' => 'POST',
            'schedule' => ['cron' => '*/15 * * * *', 'timezone' => 'Europe/London'],
            'enabled' => true,
            'status' => 'pending_verification',
            'verified_at' => null,
            'next_run_at' => null,
            'last_run_at' => null,
            'consecutive_failures' => 0,
            'updated_at' => '2026-08-21T09:00:00+00:00',
        ]];
    }
}

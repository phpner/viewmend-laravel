<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Tests\Feature;

use AssertionError;
use ViewMend\Exception\AuthenticationException;
use ViewMend\Laravel\Contracts\ViewMendManagerContract;
use ViewMend\Laravel\Facades\ViewMend;
use ViewMend\Laravel\Testing\RecordedEvent;
use ViewMend\Laravel\Tests\TestCase;

final class FakeTest extends TestCase
{
    protected function tearDown(): void
    {
        ViewMend::clearResolvedInstances();

        parent::tearDown();
    }

    public function testFakeReplacesFacadeAndDependencyInjectionBinding(): void
    {
        $fake = ViewMend::fake();

        self::assertSame($fake, $this->application()->make(ViewMendManagerContract::class));
        self::assertSame($fake, ViewMend::getFacadeRoot());

        $result = $this->application()->make(ViewMendManagerContract::class)
            ->siteTracker()
            ->events()
            ->contentUpdate('content-123', 'Pricing updated')
            ->site('https://example.com')
            ->page('https://example.com/pricing')
            ->environment('staging')
            ->send();

        self::assertFalse($result->duplicate);
        self::assertSame('queued', $result->queueStatus->value);
        $fake->assertSent(
            'content_update',
            static fn (RecordedEvent $event): bool => $event->id === 'content-123'
                && $event->connection === 'default'
                && $event->integrationId === 'tracker-default'
                && $event->title === 'Pricing updated'
                && $event->site === 'https://example.com'
                && $event->hasPage('https://example.com/pricing')
                && $event->environment === 'staging',
        );
    }

    public function testCreatingABuilderDoesNotRecordAnEventUntilSend(): void
    {
        $fake = ViewMend::fake();

        ViewMend::siteTracker()
            ->events()
            ->deployment('deploy-not-sent', 'Not sent');

        $fake->assertNothingSent();
        self::assertSame([], $fake->events());
    }

    public function testAssertionsFailClearlyWhenNoEventMatches(): void
    {
        $fake = ViewMend::fake();

        $this->expectException(AssertionError::class);
        $this->expectExceptionMessage('No matching ViewMend [deployment] event was sent.');

        $fake->assertSent('deployment');
    }

    public function testFakeRejectsRetryableFailureStatuses(): void
    {
        $fake = ViewMend::fake();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-retryable HTTP status');

        $fake->failNext(503);
    }

    public function testSdkExceptionsKeepTheirOriginalType(): void
    {
        ViewMend::fake()->failNext(401);

        $this->expectException(AuthenticationException::class);

        ViewMend::siteTracker()
            ->events()
            ->deployment('deploy-unauthorized', 'Unauthorized delivery')
            ->send();
    }
}

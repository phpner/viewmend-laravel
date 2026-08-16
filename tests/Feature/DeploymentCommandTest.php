<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use ViewMend\Laravel\Facades\ViewMend;
use ViewMend\Laravel\Testing\RecordedEvent;
use ViewMend\Laravel\Tests\TestCase;

final class DeploymentCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        ViewMend::clearResolvedInstances();

        parent::tearDown();
    }

    public function testDeploymentCommandSendsFullContextAndRepeatedPages(): void
    {
        $fake = ViewMend::fake();

        $this->command('viewmend:deployment', [
            '--event-id' => 'deploy-abc123',
            '--title' => 'Homepage deployed',
            '--site' => 'https://example.com',
            '--page' => [
                'https://example.com/',
                'https://example.com/pricing',
            ],
            '--environment' => 'production',
            '--description' => 'Published release abc123.',
            '--reference' => 'https://example.com/releases/abc123',
            '--connection' => 'secondary',
        ])
            ->expectsOutputToContain('ViewMend deployment accepted for [deploy-abc123]')
            ->expectsOutputToContain('Queue status: queued')
            ->assertExitCode(Command::SUCCESS);

        $fake->assertSent(
            'deployment',
            static fn (RecordedEvent $event): bool => $event->id === 'deploy-abc123'
                && $event->title === 'Homepage deployed'
                && $event->connection === 'secondary'
                && $event->integrationId === 'tracker/secondary'
                && $event->site === 'https://example.com'
                && $event->pages === [
                    'https://example.com/',
                    'https://example.com/pricing',
                ]
                && $event->environment === 'production'
                && $event->description === 'Published release abc123.'
                && $event->reference === 'https://example.com/releases/abc123',
        );
    }

    public function testDuplicateDeliveryIsSuccessfulAndExplicit(): void
    {
        $fake = ViewMend::fake()->duplicateNext('processed');

        $this->command('viewmend:deployment', [
            '--event-id' => 'deploy-stable',
        ])
            ->expectsOutputToContain('ViewMend deployment duplicate for [deploy-stable]')
            ->expectsOutputToContain('Queue status: processed')
            ->assertExitCode(Command::SUCCESS);

        $fake->assertSent('deployment');
    }

    public function testMissingStableEventIdIsInvalidAndDoesNotSend(): void
    {
        $fake = ViewMend::fake();

        $this->command('viewmend:deployment')
            ->expectsOutputToContain('The --event-id option is required.')
            ->assertExitCode(Command::INVALID);

        $fake->assertNothingSent();
    }

    public function testEmptyTitleIsInvalidAndDoesNotSend(): void
    {
        $fake = ViewMend::fake();

        $this->command('viewmend:deployment', [
            '--event-id' => 'deploy-1',
            '--title' => '',
        ])
            ->expectsOutputToContain('The --title option must not be empty.')
            ->assertExitCode(Command::INVALID);

        $fake->assertNothingSent();
    }

    public function testFailedDeliveryReturnsNonZeroAndPreservesSdkMessage(): void
    {
        $fake = ViewMend::fake()->failNext(422);

        $this->command('viewmend:deployment', [
            '--event-id' => 'deploy-invalid',
        ])
            ->expectsOutputToContain(
                'ViewMend deployment delivery failed: ViewMend could not apply the event payload.',
            )
            ->assertExitCode(Command::FAILURE);

        $fake->assertSent('deployment');
    }

    public function testConfigurationFailureReturnsNonZeroWithoutExposingToken(): void
    {
        $secret = 'sensitive-command-test-value';
        $this->application()->make(ConfigRepository::class)->set('viewmend', [
            'default' => 'missing',
            'connections' => [
                'default' => [
                    'token' => $secret,
                    'site_tracker' => ['integration_id' => 'tracker-default'],
                ],
            ],
        ]);
        ViewMend::fake();

        $command = $this->command('viewmend:deployment', [
            '--event-id' => 'deploy-1',
        ])
            ->expectsOutputToContain('viewmend.connections.missing')
            ->doesntExpectOutputToContain($secret)
            ->assertExitCode(Command::FAILURE);

        self::assertSame(Command::FAILURE, $command->run());
    }
}

<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Tests\Feature;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use ViewMend\Exception\ConfigurationException as SdkConfigurationException;
use ViewMend\Laravel\Contracts\ClientFactoryContract;
use ViewMend\Laravel\Contracts\ViewMendManagerContract;
use ViewMend\Laravel\Exception\MalformedConfigurationException;
use ViewMend\Laravel\Tests\TestCase;

final class ApiBaseUrlTest extends TestCase
{
    public function testDefaultFactoryAppliesBaseUrlsPerConnectionWithoutRequests(): void
    {
        $this->application()->make(ConfigRepository::class)->set(
            'viewmend.connections.secondary.api_base_url',
            'http://127.0.0.1:8079/api/v1',
        );
        $manager = $this->application()->make(ViewMendManagerContract::class);

        ob_start();
        var_dump(
            $manager->client(),
            $manager->connection('secondary')->client(),
            $this->application()->make(ClientFactoryContract::class),
        );
        $debug = ob_get_clean();

        self::assertIsString($debug);
        self::assertStringContainsString('https://viewmend.com/api/v1', $debug);
        self::assertStringContainsString('http://127.0.0.1:8079/api/v1', $debug);
        self::assertStringNotContainsString('test-secondary-token', $debug);
    }

    #[DataProvider('invalidValues')]
    public function testInvalidBaseUrlConfigurationFailsLazily(mixed $value): void
    {
        $this->application()->make(ConfigRepository::class)->set(
            'viewmend.connections.default.api_base_url',
            $value,
        );
        $connection = $this->application()->make(ViewMendManagerContract::class)->connection();
        self::assertSame('default', $connection->name());
        $this->expectException(MalformedConfigurationException::class);
        $this->expectExceptionMessage('viewmend.connections.default.api_base_url');

        $connection->client();
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidValues(): iterable
    {
        yield 'integer' => [123];
        yield 'array' => [[]];
        yield 'blank' => [''];
        yield 'whitespace' => [' https://example.com/api/v1'];
    }

    public function testUrlSyntaxValidationRemainsOwnedByTheSdk(): void
    {
        $this->application()->make(ConfigRepository::class)->set(
            'viewmend.connections.default.api_base_url',
            'https://example.com/api/v1?invalid=true',
        );
        $this->expectException(SdkConfigurationException::class);

        $this->application()->make(ViewMendManagerContract::class)->client();
    }
}

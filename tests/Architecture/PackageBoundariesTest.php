<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class PackageBoundariesTest extends TestCase
{
    public function testComposerDeclaresLaravelAutoDiscoveryAndNoFrameworkDependency(): void
    {
        $composer = json_decode(
            (string) file_get_contents(__DIR__ . '/../../composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (!is_array($composer)) {
            self::fail('Expected composer.json to contain an object.');
        }
        $extra = $composer['extra'] ?? null;
        if (!is_array($extra)) {
            self::fail('Expected Composer extra metadata.');
        }
        $laravel = $extra['laravel'] ?? null;
        if (!is_array($laravel)) {
            self::fail('Expected Laravel package metadata.');
        }
        self::assertSame(
            ['ViewMend\\Laravel\\ViewMendServiceProvider'],
            $laravel['providers'] ?? null,
        );
        $require = $composer['require'] ?? null;
        if (!is_array($require)) {
            self::fail('Expected Composer production requirements.');
        }
        self::assertArrayNotHasKey('laravel/framework', $require);
        self::assertSame('^1.0', $require['viewmend/sdk'] ?? null);
    }

    public function testSourceDoesNotUseSdkInternalApis(): void
    {
        $source = '';
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__ . '/../../src'),
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $contents = file_get_contents($file->getPathname());
                self::assertIsString($contents);
                $source .= $contents;
            }
        }

        self::assertStringNotContainsString('ViewMend\\Internal', $source);
    }
}

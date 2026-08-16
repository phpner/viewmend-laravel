<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(static function (Middleware $middleware): void {
        // The smoke application has no HTTP middleware.
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        // The smoke application uses Laravel's default exception handling.
    })
    ->create();

<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\RequireAdministratorMfa;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureAccountIsActive::class]);
        $middleware->alias(['admin.mfa' => RequireAdministratorMfa::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

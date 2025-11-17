<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // BẬT CORS GLOBAL (QUAN TRỌNG NHẤT)
        $middleware->appendToGroup('web', [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->appendToGroup('api', [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Nếu bạn có custom CORS middleware → bỏ đi hoặc cho sau
        // $middleware->api(prepend: [
        //     \App\Http\Middleware\CorsMiddleware::class,
        // ]);

        $middleware->alias([
            'is_admin' => \App\Http\Middleware\IsAdmin::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

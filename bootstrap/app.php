<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php', 
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ✅ CORS middleware - ưu tiên cao nhất
        $middleware->api(prepend: [
            \App\Http\Middleware\CorsMiddleware::class,
        ]);

        // ✅ Thêm validateCsrfTokens exception cho API
        $middleware->validateCsrfTokens(except: [
            'api/*',  // Bỏ qua CSRF cho tất cả API routes
        ]);

        // ✅ Middleware alias
        $middleware->alias([
            'is_admin' => \App\Http\Middleware\IsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ✅ Custom error handler để CORS vẫn hoạt động khi có lỗi
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*')) {
                $response = response()->json([
                    'error' => $e->getMessage(),
                    'message' => 'Server Error',
                    'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                ], 500);

                // Thêm CORS headers vào error response
                $origin = $request->header('Origin');
                if ($origin) {
                    $response->headers->set('Access-Control-Allow-Origin', $origin);
                    $response->headers->set('Access-Control-Allow-Credentials', 'true');
                }

                return $response;
            }
        });
    })->create();
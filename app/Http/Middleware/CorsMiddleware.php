<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = [
            'https://ban-do-an.vercel.app',
            'http://localhost:5173',
            'http://localhost:3000',
        ];

        $origin = $request->header('Origin');

        // Xử lý preflight OPTIONS
        if ($request->getMethod() === 'OPTIONS') {
            $response = response()->json([], 200);
        } else {
            try {
                $response = $next($request);
            } catch (\Throwable $e) {
                // Nếu có lỗi, vẫn trả response JSON với CORS header
                $response = response()->json([
                    'message' => $e->getMessage(),
                ], 500);
            }
        }

        // Thêm CORS headers nếu origin hợp lệ
        if ($origin && (in_array($origin, $allowedOrigins) || preg_match('/^https:\/\/.*\.vercel\.app$/', $origin))) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Max-Age', '600');
        }

        return $response;
    }
}

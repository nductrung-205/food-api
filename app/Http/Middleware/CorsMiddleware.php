<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->header('Origin');
        
        $allowedOrigins = [
            'https://ban-do-an.vercel.app',
            'http://localhost:5173',
            'http://localhost:3000',
        ];

        $isAllowed = in_array($origin, $allowedOrigins) 
                     || preg_match('/^https:\/\/.*\.vercel\.app$/', $origin ?? '');

        // ✅ LOG để debug
        Log::info('CORS Middleware', [
            'origin' => $origin,
            'method' => $request->method(),
            'path' => $request->path(),
            'is_allowed' => $isAllowed
        ]);

        // ✅ Handle preflight OPTIONS request
        if ($request->getMethod() === "OPTIONS") {
            $response = response()->json([], 200);
            
            if ($isAllowed) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Max-Age', '86400');
            }
            
            return $response;
        }

        // ✅ Process request và thêm CORS headers
        try {
            $response = $next($request);
            
            if ($isAllowed) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
            
            return $response;
            
        } catch (\Exception $e) {
            // ✅ Nếu có lỗi, vẫn thêm CORS headers để frontend nhận được error
            Log::error('CORS Middleware Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorResponse = response()->json([
                'error' => 'Internal Server Error',
                'message' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
            
            if ($isAllowed) {
                $errorResponse->headers->set('Access-Control-Allow-Origin', $origin);
                $errorResponse->headers->set('Access-Control-Allow-Credentials', 'true');
            }
            
            return $errorResponse;
        }
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Product;

class ChatbotController extends Controller
{
    /**
     * Lấy danh sách món ăn từ database
     */
    private function getMenuItems()
    {
        try {
            $products = Product::select('name', 'description', 'price')
                              ->take(20)
                              ->get();

            return $products->map(function($product) {
                return [
                    'name' => $product->name,
                    'description' => $product->description ?? 'Món ăn ngon',
                    'price' => number_format($product->price, 0, ',', '.') . ' VNĐ'
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('Lỗi khi lấy menu: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Thử gọi Gemini API
     */
    private function callGeminiAPI($apiKey, $conversationText)
    {
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

        $requestData = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $conversationText]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 400,
            ]
        ];

        try {
            Log::info("🔄 Gọi Gemini API", [
                'endpoint' => $endpoint,
                'api_key_length' => strlen($apiKey)
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint . "?key={$apiKey}", $requestData);

            Log::info("📥 Response từ Gemini", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]
            ];

        } catch (\Exception $e) {
            Log::error("💥 Exception khi gọi Gemini", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => [
                    'status' => 500,
                    'body' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * API Chat sử dụng Google Gemini
     */
    public function chat(Request $request)
    {
        try {
            Log::info("🎯 Nhận request chat", [
                'method' => $request->method(),
                'origin' => $request->header('Origin'),
                'has_message' => $request->has('message')
            ]);

            // Xử lý preflight
            if ($request->method() === 'OPTIONS') {
                return response()->json([], 200)
                    ->header('Access-Control-Allow-Origin', 'https://ban-do-an.vercel.app')
                    ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
                    ->header('Access-Control-Allow-Credentials', 'true');
            }

            $userMessage = $request->input('message');
            $chatHistory = $request->input('chatHistory', []);

            if (empty($userMessage)) {
                Log::warning("⚠️ Tin nhắn trống");
                return response()->json(['error' => 'Tin nhắn không được để trống.'], 400)
                    ->header('Access-Control-Allow-Origin', 'https://ban-do-an.vercel.app')
                    ->header('Access-Control-Allow-Credentials', 'true');
            }

            // Kiểm tra API key
            $apiKey = env('GEMINI_API_KEY');
            Log::info("🔑 API Key check", [
                'exists' => !empty($apiKey),
                'length' => strlen($apiKey ?? ''),
                'starts_with' => substr($apiKey ?? '', 0, 10)
            ]);

            if (empty($apiKey)) {
                Log::error('❌ GEMINI_API_KEY không tồn tại');
                return response()->json([
                    'error' => 'Hệ thống AI chưa được cấu hình. Vui lòng thêm GEMINI_API_KEY.'
                ], 500)
                    ->header('Access-Control-Allow-Origin', 'https://ban-do-an.vercel.app')
                    ->header('Access-Control-Allow-Credentials', 'true');
            }

            // Lấy menu
            Log::info("📋 Đang lấy menu...");
            $currentMenuItems = $this->getMenuItems();
            Log::info("📋 Menu items count: " . count($currentMenuItems));

            $menuText = count($currentMenuItems) > 0 
                ? json_encode($currentMenuItems, JSON_UNESCAPED_UNICODE)
                : "Hiện tại chưa có thông tin thực đơn chi tiết.";

            $systemPrompt = "Bạn là trợ lý ảo của nhà hàng Ẩm Thực Việt. Trả lời ngắn gọn, thân thiện.

Thực đơn: {$menuText}

Hướng dẫn:
- Trả lời về món ăn, giá cả, địa chỉ
- Giờ mở cửa: 9:00-22:00
- SĐT: 0912-345-678";

            $conversationText = $systemPrompt . "\n\nKhách: {$userMessage}\nTrợ lý:";

            Log::info("📤 Gửi đến Gemini", [
                'message_length' => strlen($conversationText)
            ]);

            // Gọi API
            $result = $this->callGeminiAPI($apiKey, $conversationText);

            if (!$result['success']) {
                $error = $result['error'];
                Log::error('❌ Gemini API thất bại', $error);
                
                return response()->json([
                    'error' => 'AI không phản hồi. Vui lòng thử lại.',
                    'debug' => [
                        'status' => $error['status'],
                        'message' => substr($error['body'], 0, 200)
                    ]
                ], 500)
                    ->header('Access-Control-Allow-Origin', 'https://ban-do-an.vercel.app')
                    ->header('Access-Control-Allow-Credentials', 'true');
            }

            $responseData = $result['data'];

            // Kiểm tra lỗi
            if (isset($responseData['error'])) {
                $errorMsg = $responseData['error']['message'] ?? 'Lỗi không xác định';
                Log::error('❌ Lỗi từ Gemini', ['error' => $errorMsg]);
                return response()->json([
                    'error' => 'AI gặp lỗi: ' . $errorMsg
                ], 500)
                    ->header('Access-Control-Allow-Origin', 'https://ban-do-an.vercel.app')
                    ->header('Access-Control-Allow-Credentials', 'true');
            }

            // Lấy reply
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $reply = trim($responseData['candidates'][0]['content']['parts'][0]['text']);
                
                Log::info('✅ Thành công', ['reply_length' => strlen($reply)]);
                
                return response()->json(['reply' => $reply])
                    ->header('Access-Control-Allow-Origin', 'https://ban-do-an.vercel.app')
                    ->header('Access-Control-Allow-Credentials', 'true');
            }

            Log::error('❌ Không có text trong response', ['response' => $responseData]);
            return response()->json([
                'error' => 'Không nhận được phản hồi từ AI.'
            ], 500)
                ->header('Access-Control-Allow-Origin', 'https://ban-do-an.vercel.app')
                ->header('Access-Control-Allow-Credentials', 'true');

        } catch (\Exception $e) {
            Log::error('💥 Exception trong chat()', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500)
                ->header('Access-Control-Allow-Origin', 'https://ban-do-an.vercel.app')
                ->header('Access-Control-Allow-Credentials', 'true');
        }
    }
}
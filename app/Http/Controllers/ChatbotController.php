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
     * Gọi Gemini API
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
                ->post($endpoint . "?key={$apiKey}", $requestData);

            Log::info("📥 Response status: " . $response->status());

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
            Log::error("💥 Exception khi gọi Gemini: " . $e->getMessage());
            
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
     * API Chat
     */
    public function chat(Request $request)
    {
        try {
            Log::info("🎯 Nhận request chat", [
                'method' => $request->method(),
                'origin' => $request->header('Origin'),
                'has_message' => $request->has('message')
            ]);

            $userMessage = $request->input('message');

            if (empty($userMessage)) {
                Log::warning("⚠️ Tin nhắn trống");
                return response()->json(['error' => 'Tin nhắn không được để trống.'], 400);
            }

            // Kiểm tra API key
            $apiKey = env('GEMINI_API_KEY');
            Log::info("🔑 API Key", [
                'exists' => !empty($apiKey),
                'length' => strlen($apiKey ?? ''),
                'preview' => substr($apiKey ?? '', 0, 15) . '...'
            ]);

            if (empty($apiKey)) {
                Log::error('❌ GEMINI_API_KEY không tồn tại');
                return response()->json([
                    'error' => 'Hệ thống AI chưa được cấu hình.'
                ], 500);
            }

            // Lấy menu
            Log::info("📋 Đang lấy menu...");
            $currentMenuItems = $this->getMenuItems();
            Log::info("📋 Menu: " . count($currentMenuItems) . " items");

            $menuText = count($currentMenuItems) > 0 
                ? json_encode($currentMenuItems, JSON_UNESCAPED_UNICODE)
                : "Chưa có thông tin thực đơn.";

            $systemPrompt = "Bạn là trợ lý ảo của nhà hàng Ẩm Thực Việt. Trả lời ngắn gọn (2-3 câu), thân thiện.

Thực đơn: {$menuText}

Thông tin:
- Giờ mở cửa: 9:00-22:00
- SĐT: 0912-345-678
- Địa chỉ: 123 Nguyễn Huệ, Q1, HCM";

            $conversationText = $systemPrompt . "\n\nKhách: {$userMessage}\nTrợ lý:";

            Log::info("📤 Gửi đến Gemini");

            // Gọi API
            $result = $this->callGeminiAPI($apiKey, $conversationText);

            if (!$result['success']) {
                $error = $result['error'];
                Log::error('❌ Gemini thất bại', [
                    'status' => $error['status'],
                    'body' => substr($error['body'], 0, 200)
                ]);
                
                return response()->json([
                    'error' => 'AI không phản hồi. Vui lòng thử lại sau.'
                ], 500);
            }

            $responseData = $result['data'];

            // Kiểm tra lỗi từ API
            if (isset($responseData['error'])) {
                $errorMsg = $responseData['error']['message'] ?? 'Lỗi không xác định';
                Log::error('❌ Lỗi từ Gemini API: ' . $errorMsg);
                return response()->json([
                    'error' => 'AI gặp lỗi: ' . $errorMsg
                ], 500);
            }

            // Lấy reply
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $reply = trim($responseData['candidates'][0]['content']['parts'][0]['text']);
                
                Log::info('✅ Thành công', ['length' => strlen($reply)]);
                
                return response()->json(['reply' => $reply]);
            }

            Log::error('❌ Không có text trong response');
            return response()->json([
                'error' => 'Không nhận được phản hồi từ AI.'
            ], 500);

        } catch (\Exception $e) {
            Log::error('💥 Exception', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return response()->json([
                'error' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }
}
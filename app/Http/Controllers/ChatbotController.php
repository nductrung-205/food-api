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

            return $products->map(function ($product) {
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
     * Thử gọi Gemini API với nhiều phương án
     */
    private function callGeminiAPI($apiKey, $conversationText)
    {
        // Danh sách các endpoint để thử (theo thứ tự ưu tiên)
        $endpoints = [
            'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent',
            'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent',
            'https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent',
        ];

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
                'topP' => 0.95,
                'topK' => 40
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_NONE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_NONE'
                ]
            ]
        ];

        $lastError = null;

        // Thử từng endpoint
        foreach ($endpoints as $index => $endpoint) {
            try {
                Log::info("Đang thử endpoint " . ($index + 1) . ": " . $endpoint);

                $response = Http::timeout(30)
                    ->post($endpoint . "?key={$apiKey}", $requestData);

                if ($response->successful()) {
                    Log::info("✅ Thành công với endpoint: " . $endpoint);
                    return [
                        'success' => true,
                        'data' => $response->json()
                    ];
                } else {
                    $lastError = [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ];
                    Log::warning("❌ Endpoint thất bại: " . $endpoint, $lastError);
                }
            } catch (\Exception $e) {
                $lastError = [
                    'status' => 500,
                    'body' => $e->getMessage()
                ];
                Log::warning("❌ Exception với endpoint: " . $endpoint, ['error' => $e->getMessage()]);
            }
        }

        // Tất cả endpoints đều thất bại
        return [
            'success' => false,
            'error' => $lastError
        ];
    }

    /**
     * API Chat sử dụng Google Gemini (MIỄN PHÍ)
     */
    public function chat(Request $request)
    {
        header('Access-Control-Allow-Origin: https://ban-do-an.vercel.app');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Credentials: true');

        // Log để debug
        Log::info('Chat request received', [
            'origin' => $request->header('Origin'),
            'method' => $request->method(),
        ]);

        $userMessage = $request->input('message');
        $chatHistory = $request->input('chatHistory', []);

        if (empty($userMessage)) {
            return response()->json(['error' => 'Tin nhắn không được để trống.'], 400);
        }

        try {
            $apiKey = env('GEMINI_API_KEY');
            if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
                Log::error('GEMINI_API_KEY chưa được cấu hình đúng trong file .env');
                return response()->json([
                    'error' => 'Hệ thống AI chưa được cấu hình. Vui lòng thêm GEMINI_API_KEY vào file .env'
                ], 500);
            }

            $currentMenuItems = $this->getMenuItems();
            $menuText = count($currentMenuItems) > 0
                ? json_encode($currentMenuItems, JSON_UNESCAPED_UNICODE)
                : "Hiện tại chưa có thông tin thực đơn chi tiết.";

            $systemPrompt = "Bạn là trợ lý ảo thân thiện của nhà hàng \"Ẩm Thực Việt\", chuyên về các món ăn truyền thống Việt Nam.

📋 THÔNG TIN NHÀ HÀNG:
- Thực đơn: {$menuText}
- Địa chỉ: 123 Đường Nguyễn Huệ, Quận 1, TP.HCM
- Giờ mở cửa: 9:00 - 22:00 hàng ngày
- Số điện thoại đặt hàng: 0912-345-678

📌 NHIỆM VỤ CỦA BẠN:
- Trả lời thân thiện, nhiệt tình về thực đơn, giá cả, địa chỉ, giờ mở cửa
- Gợi ý món ăn phù hợp với nhu cầu khách hàng
- Hướng dẫn cách đặt món qua điện thoại hoặc website
- Trả lời ngắn gọn, súc tích (1-3 câu), dùng emoji phù hợp

❌ KHÔNG được:
- Trả lời về chủ đề không liên quan đến nhà hàng
- Đưa ra thông tin sai lệch về giá hoặc món ăn không có trong menu";

            $conversationText = $systemPrompt . "\n\n===== CUỘC HỘI THOẠI =====\n";

            $recentHistory = array_slice($chatHistory, -5);
            foreach ($recentHistory as $msg) {
                $role = $msg['sender'] === 'user' ? 'Khách hàng' : 'Trợ lý';
                $conversationText .= "{$role}: {$msg['text']}\n";
            }

            $conversationText .= "Khách hàng: {$userMessage}\nTrợ lý:";

            Log::info('Đang gửi request đến Google Gemini API', [
                'user_message' => $userMessage
            ]);

            // Gọi API với nhiều phương án dự phòng
            $result = $this->callGeminiAPI($apiKey, $conversationText);

            if (!$result['success']) {
                $error = $result['error'];
                Log::error('Tất cả endpoints Gemini đều thất bại', $error);

                $statusCode = $error['status'] ?? 500;

                if ($statusCode === 400) {
                    return response()->json([
                        'error' => 'API key không hợp lệ hoặc đã hết hạn. Vui lòng tạo key mới tại https://aistudio.google.com/apikey'
                    ], 500);
                } elseif ($statusCode === 429) {
                    return response()->json([
                        'error' => 'Đã vượt quá giới hạn request. Vui lòng thử lại sau ít phút.'
                    ], 500);
                }

                return response()->json([
                    'error' => 'Không thể kết nối đến AI. Vui lòng thử lại sau hoặc liên hệ quản trị viên.'
                ], 500);
            }

            $responseData = $result['data'];

            if (isset($responseData['error'])) {
                $errorMessage = $responseData['error']['message'] ?? 'Lỗi không xác định';
                Log::error('Lỗi từ Gemini API', ['error' => $errorMessage]);
                return response()->json(['error' => 'AI gặp lỗi: ' . $errorMessage], 500);
            }

            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $reply = $responseData['candidates'][0]['content']['parts'][0]['text'];
                $reply = trim($reply);

                Log::info('✅ Nhận phản hồi thành công từ Gemini', [
                    'reply_length' => strlen($reply)
                ]);

                return response()->json(['reply' => $reply]);
            }

            Log::error('Không có phản hồi hợp lệ từ Gemini', ['response' => $responseData]);
            return response()->json([
                'error' => 'Không thể nhận phản hồi từ AI. Vui lòng thử lại.'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Exception khi gọi Gemini API', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'error' => 'Rất tiếc, hệ thống đang gặp sự cố. Vui lòng thử lại sau.'
            ], 500);
        }
    }
}

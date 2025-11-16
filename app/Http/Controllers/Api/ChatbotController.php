<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    public function chat(Request $request)
    {
        Log::info('Chat request received', [
            'origin' => $request->header('Origin'),
            'method' => $request->method(),
        ]);

        $apiKey = config('services.google.api_key');

        Log::info('API Key Check', [
            'has_key' => !empty($apiKey),
            'key_length' => strlen($apiKey ?? ''),
            'env_value' => env('GOOGLE_API_KEY') ? 'exists' : 'missing'
        ]);

        if (!$apiKey) {
            return response()->json(['error' => 'Thiếu API Key'], 500);
        }

        $userMessage = $request->input('message');
        if (!$userMessage) {
            return response()->json(['error' => 'Thiếu message'], 400);
        }

        // Lấy chatHistory từ frontend (đã theo format Gemini)
        $chatHistory = $request->input('chatHistory', []);

        // Thêm tin nhắn người dùng mới
        $chatHistory[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]]
        ];

        // System instruction
        $systemInstruction = [
            'parts' => [
                [
                    'text' => 'Bạn là trợ lý ảo thông minh của nhà hàng "Ẩm Thực Việt". ' .
                        'Nhiệm vụ của bạn là tư vấn món ăn, giải đáp thắc mắc về thực đơn, ' .
                        'giá cả, và hỗ trợ khách hàng đặt món. Hãy thân thiện, nhiệt tình và chuyên nghiệp. ' .
                        'Khi khách hỏi về món ăn hoặc thực đơn, hãy sử dụng function get_menu_items hoặc search_dish để lấy thông tin chính xác.'
                ]
            ]
        ];

        // Khai báo function cho AI
        $tools = [
            [
                'functionDeclarations' => [
                    [
                        'name' => 'get_menu_items',
                        'description' => 'Lấy danh sách món ăn theo phân loại. Các phân loại có sẵn: "món chính", "đồ uống", "món phụ", "tráng miệng"',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'category' => [
                                    'type' => 'string',
                                    'description' => 'Tên phân loại món ăn (ví dụ: "món chính", "đồ uống")',
                                    'enum' => ['món chính', 'đồ uống', 'món phụ', 'tráng miệng']
                                ]
                            ],
                            'required' => ['category']
                        ]
                    ],
                    [
                        'name' => 'search_dish',
                        'description' => 'Tìm kiếm món ăn theo tên',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'dish_name' => [
                                    'type' => 'string',
                                    'description' => 'Tên món ăn cần tìm'
                                ]
                            ],
                            'required' => ['dish_name']
                        ]
                    ]
                ]
            ]
        ];

        // Payload gửi AI
        $payload = [
            'contents' => $chatHistory,
            'systemInstruction' => $systemInstruction,
            'tools' => $tools,
            'generationConfig' => [
                'temperature' => 0.7,
                'topP' => 0.8,
                'topK' => 40,
                'maxOutputTokens' => 1024,
            ]
        ];

        // Gọi AI
        $response = $this->callGeminiAPI($apiKey, $payload);

        if (!$response['success']) {
            Log::error('Gemini API Error:', ['error' => $response['error']]);
            return response()->json([
                'error' => 'Đã xảy ra lỗi khi gọi AI.',
                'detail' => $response['error']
            ], 500);
        }

        $responseData = $response['data'];

        if (!isset($responseData['candidates']) || empty($responseData['candidates'])) {
            Log::error('Invalid Gemini response structure:', $responseData);
            return response()->json([
                'error' => 'AI trả về dữ liệu không hợp lệ.',
                'detail' => $responseData['error']['message'] ?? 'Unknown error'
            ], 500);
        }

        $modelParts = $responseData['candidates'][0]['content']['parts'] ?? [];

        // ========================================
        // KIỂM TRA AI CÓ GỌI FUNCTION KHÔNG
        // ========================================
        $functionCall = null;
        foreach ($modelParts as $p) {
            if (isset($p['functionCall'])) {
                $functionCall = $p['functionCall'];
                break;
            }
        }

        // ========================================
        // XỬ LÝ KHI AI GỌI FUNCTION
        // ========================================
        if ($functionCall) {
            $functionName = $functionCall['name'] ?? null;
            $functionArgs = $functionCall['args'] ?? [];

            Log::info('Function Call Detected', [
                'name' => $functionName,
                'args' => $functionArgs
            ]);

            // Thực thi function
            $functionResult = $this->executeFunction($functionName, $functionArgs);

            // --- XỬ LÝ KẾT QUẢ TÌM KIẾM MÓN ĂN ---
            if ($functionName === 'search_dish') {
                if ($functionResult['success'] && !empty($functionResult['results'])) {
                    $dish = $functionResult['results'][0];

                    $replyText = "🍽️ **{$dish['name']}**\n\n";

                    if (!empty($dish['description'])) {
                        $replyText .= "📝 {$dish['description']}\n\n";
                    }

                    $replyText .= "💰 Giá: " . number_format($dish['price']) . "₫";

                    if (!empty($dish['category'])) {
                        $replyText .= "\n🏷️ Danh mục: {$dish['category']}";
                    }

                    if ($functionResult['count'] > 1) {
                        $replyText .= "\n\n💡 Tôi cũng tìm thấy " . ($functionResult['count'] - 1) . " món tương tự khác.";
                    }

                    return response()->json([
                        'reply' => $replyText,
                        'image_url' => $dish['image_url'] ?? null,
                        'image_alt' => $dish['name'],
                    ]);
                } else {
                    // Không tìm thấy món
                    $searchQuery = $functionArgs['dish_name'] ?? 'món bạn yêu cầu';
                    return response()->json([
                        'reply' => "😔 Xin lỗi, tôi không tìm thấy món \"{$searchQuery}\" trong thực đơn.\n\nBạn có thể thử tìm món khác hoặc xem danh mục để khám phá thêm nhé! 🍜",
                    ]);
                }
            }

            // --- XỬ LÝ KẾT QUẢ LẤY DANH SÁCH MÓN THEO DANH MỤC ---
            if ($functionName === 'get_menu_items') {
                if ($functionResult['success'] && !empty($functionResult['items'])) {
                    $replyText = "🍽️ **Danh sách món {$functionResult['category']}**\n\n";

                    // Hiển thị tối đa 8 món
                    $itemsList = array_slice($functionResult['items'], 0, 8);

                    foreach ($itemsList as $index => $item) {
                        $replyText .= ($index + 1) . ". **{$item['name']}** - " . number_format($item['price']) . "₫\n";
                        if (!empty($item['description'])) {
                            $replyText .= "   _{$item['description']}_\n";
                        }
                        $replyText .= "\n";
                    }

                    if ($functionResult['count'] > 8) {
                        $replyText .= "💡 Và còn " . ($functionResult['count'] - 8) . " món khác nữa!\n";
                    }

                    $replyText .= "\nBạn muốn tìm hiểu chi tiết món nào không? 😊";

                    return response()->json([
                        'reply' => $replyText,
                    ]);
                } else {
                    // Danh mục không có món hoặc lỗi
                    return response()->json([
                        'reply' => $functionResult['message'] ?? "😔 Hiện tại chưa có món nào trong danh mục này.",
                    ]);
                }
            }

            // Function không được hỗ trợ hoặc lỗi
            return response()->json([
                'reply' => "❌ Xin lỗi, tôi không thể thực hiện yêu cầu này. Vui lòng thử lại!",
            ]);
        }

        // ========================================
        // KHÔNG CÓ FUNCTION CALL → TRẢ VỀ TEXT
        // ========================================
        return response()->json([
            'reply' => $this->extractText($modelParts)
        ]);
    }

    //=====================
    // Gọi API Gemini
    //=====================
    private function callGeminiAPI($apiKey, $payload)
    {
        $model = config('services.google.model', 'gemini-1.5-flash-latest');

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                    $payload
                );

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            Log::error('Gemini API HTTP Error:', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['success' => false, 'error' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Gemini API Exception:', ['message' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function extractText($parts)
    {
        $text = '';
        foreach ($parts as $p) {
            if (isset($p['text'])) {
                $text .= $p['text'];
            }
        }
        return $text ?: 'AI không trả về phản hồi.';
    }

    //=====================
    // Function Backend
    //=====================
    private function executeFunction($name, $args)
    {
        return match ($name) {
            'get_menu_items' => $this->getMenuItems($args),
            'search_dish' => $this->searchDish($args),
            default => ['error' => 'Hàm không tồn tại'],
        };
    }

    private function getMenuItems($args)
    {
        $categoryName = trim($args['category'] ?? '');

        $category = \App\Models\Category::where('name', 'like', $categoryName)->first();

        if (!$category) {
            $available = \App\Models\Category::pluck('name')->toArray();
            return [
                'success' => false,
                'message' => "Không tìm thấy phân loại '$categoryName'. Các phân loại có sẵn: " . implode(', ', $available)
            ];
        }

        $items = $category->products()
            ->select('id', 'name', 'price', 'description', 'image')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'price' => $item->price,
                    'description' => $item->description,
                    'image_url' => $item->image_url
                ];
            })->toArray();

        if (empty($items)) {
            return [
                'success' => false,
                'message' => "Phân loại '$categoryName' hiện chưa có món ăn."
            ];
        }

        return [
            'success' => true,
            'category' => $category->name,
            'items' => $items,
            'count' => count($items)
        ];
    }

    private function searchDish($args)
    {
        $dishName = trim($args['dish_name'] ?? '');

        $results = \App\Models\Product::where('name', 'like', "%$dishName%")
            ->with('category:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'price' => $item->price,
                    'description' => $item->description,
                    'category' => $item->category->name ?? null,
                    'image_url' => $item->image_url
                ];
            })
            ->toArray();

        return [
            'success' => !empty($results),
            'query' => $dishName,
            'results' => $results,
            'count' => count($results)
        ];
    }
}

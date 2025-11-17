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
        try {
            // ✅ LOG CHI TIẾT ĐỂ DEBUG
            Log::info('=== CHAT REQUEST START ===', [
                'origin' => $request->header('Origin'),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'all_env' => [
                    'APP_ENV' => env('APP_ENV'),
                    'APP_DEBUG' => env('APP_DEBUG'),
                    'HAS_GOOGLE_KEY' => !empty(env('GOOGLE_API_KEY')),
                    'KEY_LENGTH' => env('GOOGLE_API_KEY') ? strlen(env('GOOGLE_API_KEY')) : 0,
                ]
            ]);

            // ✅ KIỂM TRA API KEY - ƯU TIÊN env() TRƯỚC
            $apiKey = env('GOOGLE_API_KEY') ?? config('services.google.api_key');
            
            Log::info('API Key Check', [
                'has_key' => !empty($apiKey),
                'key_length' => $apiKey ? strlen($apiKey) : 0,
                'from_env' => !empty(env('GOOGLE_API_KEY')) ? 'YES' : 'NO',
                'from_config' => !empty(config('services.google.api_key')) ? 'YES' : 'NO',
            ]);

            if (!$apiKey) {
                Log::error('❌ MISSING GOOGLE_API_KEY', [
                    'env_value' => env('GOOGLE_API_KEY'),
                    'config_value' => config('services.google.api_key'),
                    'all_config' => config('services.google')
                ]);
                
                return response()->json([
                    'error' => 'Chatbot tạm thời không khả dụng. Vui lòng thử lại sau.',
                    'debug' => env('APP_DEBUG') ? 'Missing GOOGLE_API_KEY' : null
                ], 500);
            }

            // ✅ Validate request
            $userMessage = $request->input('message');
            if (!$userMessage) {
                return response()->json(['error' => 'Vui lòng nhập tin nhắn'], 400);
            }

            // Lấy chatHistory từ frontend
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
                            'description' => 'Lấy danh sách món ăn theo phân loại',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'category' => [
                                        'type' => 'string',
                                        'description' => 'Tên phân loại món ăn'
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

            Log::info('📤 Calling Gemini API...');

            // Gọi AI
            $response = $this->callGeminiAPI($apiKey, $payload);

            if (!$response['success']) {
                Log::error('❌ Gemini API Error:', ['error' => $response['error']]);
                return response()->json([
                    'reply' => 'Xin lỗi, tôi đang gặp sự cố kỹ thuật. Vui lòng thử lại sau. 🙏',
                    'debug' => env('APP_DEBUG') ? $response['error'] : null
                ], 200);
            }

            $responseData = $response['data'];

            if (!isset($responseData['candidates']) || empty($responseData['candidates'])) {
                Log::error('Invalid Gemini response:', $responseData);
                return response()->json([
                    'reply' => 'Xin lỗi, AI tạm thời không thể phản hồi. Vui lòng thử lại. 🙏'
                ], 200);
            }

            $modelParts = $responseData['candidates'][0]['content']['parts'] ?? [];

            // Kiểm tra AI có gọi function không
            $functionCall = null;
            foreach ($modelParts as $p) {
                if (isset($p['functionCall'])) {
                    $functionCall = $p['functionCall'];
                    break;
                }
            }

            // Xử lý function call
            if ($functionCall) {
                return $this->handleFunctionCall($functionCall);
            }

            // Không có function call → trả về text
            return response()->json([
                'reply' => $this->extractText($modelParts)
            ]);

        } catch (\Exception $e) {
            Log::error('💥 Chatbot Exception:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'reply' => 'Xin lỗi, đã xảy ra lỗi không mong muốn. Vui lòng thử lại sau. 🙏',
                'error_detail' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Xử lý function call từ AI
     */
    private function handleFunctionCall($functionCall)
    {
        try {
            $functionName = $functionCall['name'] ?? null;
            $functionArgs = $functionCall['args'] ?? [];

            Log::info('🔧 Function Call Detected', [
                'name' => $functionName,
                'args' => $functionArgs
            ]);

            // Thực thi function
            $functionResult = $this->executeFunction($functionName, $functionArgs);

            // Xử lý kết quả search_dish
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
                    $searchQuery = $functionArgs['dish_name'] ?? 'món bạn yêu cầu';
                    return response()->json([
                        'reply' => "😔 Xin lỗi, tôi không tìm thấy món \"{$searchQuery}\" trong thực đơn.\n\nBạn có thể thử tìm món khác hoặc xem danh mục để khám phá thêm nhé! 🍜",
                    ]);
                }
            }

            // Xử lý kết quả get_menu_items
            if ($functionName === 'get_menu_items') {
                if ($functionResult['success'] && !empty($functionResult['items'])) {
                    $replyText = "🍽️ **Danh sách món {$functionResult['category']}**\n\n";

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
                    return response()->json([
                        'reply' => $functionResult['message'] ?? "😔 Hiện tại chưa có món nào trong danh mục này.",
                    ]);
                }
            }

            // Function không được hỗ trợ
            return response()->json([
                'reply' => "❌ Xin lỗi, tôi không thể thực hiện yêu cầu này.",
            ]);

        } catch (\Exception $e) {
            Log::error('Function Call Error:', [
                'message' => $e->getMessage(),
                'function' => $functionCall['name'] ?? 'unknown'
            ]);

            return response()->json([
                'reply' => 'Xin lỗi, tôi gặp lỗi khi xử lý yêu cầu này. 🙏'
            ]);
        }
    }

    /**
     * Gọi Gemini API
     */
    private function callGeminiAPI($apiKey, $payload)
    {
        $model = env('GOOGLE_MODEL') ?? config('services.google.model', 'gemini-1.5-flash-latest');

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            
            Log::info('📡 Gemini API Request', [
                'model' => $model,
                'url_length' => strlen($url),
                'has_api_key' => !empty($apiKey)
            ]);

            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);

            if ($response->successful()) {
                Log::info('✅ Gemini API Success');
                return ['success' => true, 'data' => $response->json()];
            }

            Log::error('❌ Gemini API HTTP Error:', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['success' => false, 'error' => $response->body()];
        } catch (\Exception $e) {
            Log::error('❌ Gemini API Exception:', ['message' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Extract text từ model parts
     */
    private function extractText($parts)
    {
        $text = '';
        foreach ($parts as $p) {
            if (isset($p['text'])) {
                $text .= $p['text'];
            }
        }
        return $text ?: 'Xin lỗi, tôi không thể trả lời câu hỏi này.';
    }

    /**
     * Thực thi function
     */
    private function executeFunction($name, $args)
    {
        return match ($name) {
            'get_menu_items' => $this->getMenuItems($args),
            'search_dish' => $this->searchDish($args),
            default => ['success' => false, 'message' => 'Hàm không tồn tại'],
        };
    }

    /**
     * Lấy danh sách món theo category
     */
    private function getMenuItems($args)
    {
        try {
            $categoryName = trim($args['category'] ?? '');

            $category = \App\Models\Category::where('name', 'like', "%{$categoryName}%")->first();

            if (!$category) {
                $available = \App\Models\Category::pluck('name')->toArray();
                return [
                    'success' => false,
                    'message' => "Không tìm thấy phân loại '{$categoryName}'. Các phân loại có sẵn: " . implode(', ', $available)
                ];
            }

            $items = $category->products()
                ->where('status', true)
                ->select('id', 'name', 'price', 'description', 'image')
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => $item->name,
                        'price' => $item->price,
                        'description' => $item->description ?? '',
                        'image_url' => $item->image_url ?? null
                    ];
                })->toArray();

            if (empty($items)) {
                return [
                    'success' => false,
                    'message' => "Phân loại '{$categoryName}' hiện chưa có món ăn."
                ];
            }

            return [
                'success' => true,
                'category' => $category->name,
                'items' => $items,
                'count' => count($items)
            ];

        } catch (\Exception $e) {
            Log::error('getMenuItems Error:', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách món ăn.'
            ];
        }
    }

    /**
     * Tìm kiếm món ăn
     */
    private function searchDish($args)
    {
        try {
            $dishName = trim($args['dish_name'] ?? '');

            if (empty($dishName)) {
                return [
                    'success' => false,
                    'message' => 'Vui lòng cung cấp tên món ăn.'
                ];
            }

            $results = \App\Models\Product::where('name', 'like', "%{$dishName}%")
                ->where('status', true)
                ->with('category:id,name')
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => $item->name,
                        'price' => $item->price,
                        'description' => $item->description ?? '',
                        'category' => $item->category->name ?? null,
                        'image_url' => $item->image_url ?? null
                    ];
                })
                ->toArray();

            return [
                'success' => !empty($results),
                'query' => $dishName,
                'results' => $results,
                'count' => count($results)
            ];

        } catch (\Exception $e) {
            Log::error('searchDish Error:', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Lỗi khi tìm kiếm món ăn.'
            ];
        }
    }
}
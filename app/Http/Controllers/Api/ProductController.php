<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductsImport;

class ProductController extends Controller
{
    /**
     * Danh sách sản phẩm với tìm kiếm, lọc, sắp xếp
     */
    public function index(Request $request)
    {
        try {
            $query = Product::with('category');

            // Tìm kiếm theo tên, mô tả
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Lọc theo danh mục
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Lọc theo trạng thái
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Lọc theo giá
            if ($request->has('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }
            if ($request->has('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }

            // Lọc theo tồn kho
            if ($request->has('low_stock')) {
                $query->where('stock', '<=', 5);
            }

            // Sắp xếp
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Phân trang
            $perPage = $request->get('per_page', 12);
            $products = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $products->items(),
                'pagination' => [
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải danh sách sản phẩm',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo sản phẩm mới
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'name' => 'required|string|max:255',
                'slug' => 'nullable|string|max:255|unique:products,slug',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'stock' => 'nullable|integer|min:0',
                'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,jfif|max:2048',
                'status' => 'boolean',
            ]);

            // Auto slug
            $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('images/products', 'public');
                $validated['image'] = $path;
            }

            $product = Product::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Thêm sản phẩm thành công',
                'data' => $product->load('category'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo sản phẩm',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Chi tiết sản phẩm
     */
    public function show($id)
    {
        try {
            $product = Product::with([
                'category',
                'reviews' => function ($q) {
                    $q->latest()->limit(10);
                },
                'comments' => function ($q) {
                    $q->latest()->limit(10);
                }
            ])->findOrFail($id);

            // Thống kê
            $stats = [
                'total_reviews' => $product->reviews()->count(),
                'average_rating' => round($product->reviews()->avg('rating'), 1),
                'total_comments' => $product->comments()->count(),
                'total_sold' => $product->orderItems()->sum('quantity'),
            ];

            return response()->json([
                'success' => true,
                'data' => array_merge($product->toArray(), ['stats' => $stats])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sản phẩm',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Cập nhật sản phẩm
     */
    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $validated = $request->validate([
                'category_id' => 'sometimes|required|exists:categories,id',
                'name' => 'sometimes|required|string|max:255',
                'slug' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('products')->ignore($product->id),
                ],
                'description' => 'nullable|string',
                'price' => 'sometimes|required|numeric|min:0',
                'stock' => 'nullable|integer|min:0',
                'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,jfif|max:2048',
                'image_url' => 'nullable|url',
                'status' => 'boolean',
            ]);

            // Nếu có thay đổi tên mà không có slug thì tự động tạo
            if (isset($validated['name']) && !isset($validated['slug'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            /**
             * 🧩 Xử lý ảnh
             */
            if ($request->hasFile('image')) {
                // Xóa ảnh cũ trong storage (nếu có và là ảnh local)
                if ($product->image && !str_starts_with($product->image, 'http') && Storage::disk('public')->exists($product->image)) {
                    Storage::disk('public')->delete($product->image);
                }

                // Lưu ảnh mới
                $path = $request->file('image')->store('images/products', 'public');
                $validated['image'] = $path;
            } elseif ($request->filled('image_url')) {
                // Nếu người dùng nhập URL ảnh ngoài
                $validated['image'] = $request->input('image_url');
            }

            // Cập nhật sản phẩm
            $product->update($validated);

            // Trả về kèm đường dẫn ảnh đầy đủ
            $product->refresh();
            $imageUrl = str_starts_with($product->image, 'http')
                ? $product->image
                : asset('storage/' . $product->image);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật sản phẩm thành công',
                'data' => array_merge($product->load('category')->toArray(), [
                    'image_url' => $imageUrl,
                ]),
            ]);
        } catch (ValidationException $e) {
            Log::error('❌ Validation lỗi khi update product:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật sản phẩm',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa sản phẩm
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            // Kiểm tra xem sản phẩm đã có trong đơn hàng chưa
            $hasOrders = $product->orderItems()->exists();
            if ($hasOrders) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể xóa sản phẩm đã có trong đơn hàng. Hãy ẩn sản phẩm thay thế.'
                ], 400);
            }

            // Xóa ảnh nếu có
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }


            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa sản phẩm thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa sản phẩm',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật số lượng tồn kho
     */
    public function updateStock(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $validated = $request->validate([
                'stock' => 'required|integer|min:0'
            ]);

            $product->update(['stock' => $validated['stock']]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật tồn kho thành công',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật tồn kho',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Thay đổi trạng thái sản phẩm (ẩn/hiện)
     */
    public function toggleStatus($id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->update(['status' => !$product->status]);

            return response()->json([
                'success' => true,
                'message' => $product->status ? 'Hiển thị sản phẩm' : 'Ẩn sản phẩm',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi thay đổi trạng thái',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa nhiều sản phẩm
     */
    public function bulkDelete(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:products,id'
            ]);

            $count = Product::whereIn('id', $validated['ids'])
                ->whereDoesntHave('orderItems') // Chỉ xóa sản phẩm chưa có trong đơn hàng
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "Đã xóa {$count} sản phẩm thành công"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa nhiều sản phẩm',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy sản phẩm theo danh mục
     */
    public function getByCategory($id)
    {
        try {
            $products = Product::where('category_id', $id)
                ->where('status', true)
                ->with('category')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải sản phẩm theo danh mục',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            Excel::import(new ProductsImport, $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Nhập sản phẩm từ Excel thành công!',
            ]);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }
            Log::error("Excel Import Validation Errors:", ['errors' => $errors]);
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra trong quá trình kiểm tra dữ liệu Excel. Vui lòng kiểm tra lại file.',
                'errors' => $errors,
            ], 422);
        } catch (\Throwable $e) {
            Log::error("Excel Import General Error:", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi nhập sản phẩm từ Excel: ' . $e->getMessage(),
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllWithoutPagination()
    {
        return response()->json([
            'data' => Product::with('category')
                ->where('status', true)
                ->orderBy('id', 'desc')
                ->get()
        ]);
    }
}

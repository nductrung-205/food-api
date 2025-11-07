<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Danh sách đơn hàng với lọc, tìm kiếm, sắp xếp
     */
    public function index(Request $request)
    {
        try {
            $query = Order::with('user', 'items.product');

            // Tìm kiếm theo mã đơn, tên khách, email, số điện thoại
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('order_code', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            }

            // Lọc theo trạng thái
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Lọc theo phương thức thanh toán
            if ($request->has('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }

            // Lọc theo khoảng thời gian
            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            // Lọc theo khoảng giá
            if ($request->has('min_price')) {
                $query->where('total_price', '>=', $request->min_price);
            }
            if ($request->has('max_price')) {
                $query->where('total_price', '<=', $request->max_price);
            }

            // Sắp xếp
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Phân trang
            $perPage = $request->get('per_page', 10);
            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $orders->items(),
                'pagination' => [
                    'total' => $orders->total(),
                    'per_page' => $orders->perPage(),
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải danh sách đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo đơn hàng mới
     */
    public function store(Request $request)
    {

        Log::info('📥 Received order request:', $request->all());
        Log::info('🎟️ Coupon code from request:', ['coupon_code' => $request->input('coupon_code')]);

        try {
            $validatedData = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'total_price' => 'required|numeric|min:0',
                // Cập nhật dòng này để bao gồm MoMo
                'payment_method' => 'required|string|in:COD,VNPay,MoMo', // <--- THÊM 'MoMo' VÀO ĐÂY
                'coupon_code' => 'nullable|string|max:255',
                'customer' => 'required|array',
                'customer.name' => 'required|string|max:255',
                'customer.email' => 'required|email|max:255',
                'customer.phone' => 'required|string|regex:/^\d{10,11}$/',
                'customer.city' => 'required|string|max:255',
                'customer.district' => 'required|string|max:255',
                'customer.ward' => 'required|string|max:255',
                'customer.address' => 'required|string|max:255',
                'customer.type' => 'required|string|in:Nhà Riêng,Văn Phòng',
                'customer.note' => 'nullable|string|max:500',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                // CÓ THỂ THÊM 'total_price', 'subtotal_price', 'delivery_fee' nếu bạn muốn client gửi lên
                // Nhưng tốt nhất là backend tự tính lại để tránh gian lận
            ]);


            DB::beginTransaction();

            $subtotal_price = 0;
            $orderItemsData = [];

            foreach ($validatedData['items'] as $item) {
                $product = Product::lockForUpdate()->find($item['product_id']);

                if (!$product) {
                    throw new \Exception("Sản phẩm với ID: {$item['product_id']} không tồn tại.");
                }

                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Sản phẩm '{$product->name}' không đủ hàng. Còn lại: {$product->stock}");
                }

                $itemPrice = $product->price;
                $subtotal_price += $itemPrice * $item['quantity'];

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_image' => $product->image,
                    'quantity' => $item['quantity'],
                    'price' => $itemPrice,
                ];

                $product->decrement('stock', $item['quantity']);
            }

            // TÍNH PHÍ GIAO HÀNG (Dựa trên subtotal đã tính ở backend)
            $delivery_fee = $subtotal_price >= 200000 ? 0 : 15000;
            $final_discount_amount = 0; // Khởi tạo giảm giá từ coupon
            $coupon_code_applied = null; // Mã coupon thực sự được áp dụng

            Log::info('🔍 Checking coupon code:', ['input' => $validatedData['coupon_code'] ?? 'NULL']);

            // LOGIC XỬ LÝ COUPON MỚI
            if (!empty($validatedData['coupon_code'])) {
                $coupon = Coupon::where('code', $validatedData['coupon_code'])
                    ->where('is_active', true)
                    ->first();

                Log::info('🎟️ Coupon found:', ['coupon' => $coupon ? $coupon->toArray() : 'NOT FOUND']);

                if ($coupon) {
                    // Kiểm tra điều kiện coupon (valid_from, valid_to, usage_limit, min_order_amount)
                    $now = now();
                    if ($coupon->valid_from && $now->isBefore($coupon->valid_from)) {
                        throw new \Exception("Mã giảm giá chưa đến thời gian áp dụng.");
                    }
                    if ($coupon->valid_to && $now->isAfter($coupon->valid_to)) {
                        throw new \Exception("Mã giảm giá đã hết hạn.");
                    }
                    if ($coupon->usage_limit !== null && $coupon->usage_limit <= 0) {
                        throw new \Exception("Mã giảm giá đã hết lượt sử dụng.");
                    }
                    if ($coupon->min_order_amount && $subtotal_price < $coupon->min_order_amount) {
                        throw new \Exception("Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã giảm giá ({$coupon->min_order_amount}).");
                    }

                    // Tính toán số tiền giảm giá
                    if ($coupon->discount_amount) {
                        $final_discount_amount = $coupon->discount_amount;
                    } elseif ($coupon->discount_percent) {
                        $final_discount_amount = $subtotal_price * ($coupon->discount_percent / 100);
                    }
                    // Đảm bảo giảm giá không vượt quá tổng phụ
                    $final_discount_amount = min($final_discount_amount, $subtotal_price);

                    $coupon_code_applied = $coupon->code;

                    // Giảm lượt sử dụng coupon
                    if ($coupon->usage_limit !== null) {
                        $coupon->decrement('usage_limit');
                    }

                    Log::info('✅ Coupon applied:', [
                        'code' => $coupon_code_applied,
                        'discount' => $final_discount_amount
                    ]);
                } else {
                    // Nếu coupon code không tồn tại hoặc không hợp lệ, không áp dụng và có thể báo lỗi
                    // Hoặc bạn có thể bỏ qua và chỉ không áp dụng giảm giá, tùy theo UX mong muốn
                    // Hiện tại, chúng ta sẽ cho phép nó đi tiếp mà không áp dụng coupon nếu nó không tồn tại/không hợp lệ
                    // và frontend đã xử lý thông báo lỗi cho người dùng khi áp dụng.
                    // Nếu bạn muốn báo lỗi, hãy uncomment dòng dưới:
                    // throw new \Exception("Mã giảm giá không hợp lệ hoặc không tồn tại.");
                }
            }
            // KẾT THÚC LOGIC XỬ LÝ COUPON MỚI

            $total_price = max(0, $subtotal_price + $delivery_fee - $final_discount_amount); // Sử dụng $final_discount_amount

            Log::info('💰 Order prices:', [
                'subtotal' => $subtotal_price,
                'delivery_fee' => $delivery_fee,
                'discount' => $final_discount_amount,
                'total' => $total_price,
                'coupon_code' => $coupon_code_applied
            ]);

            $order = Order::create([
                'user_id' => $validatedData['user_id'],
                'order_code' => 'ORD-' . time() . rand(1000, 9999),
                'subtotal_price' => $subtotal_price,
                'delivery_fee' => $delivery_fee,
                'discount_amount' => $final_discount_amount ?? 0, // ĐẢM BẢO KHÔNG NULL
                'coupon_code' => $coupon_code_applied ?? '', // ĐỔI TỪ null THÀNH ''
                'total_price' => $total_price,
                'payment_method' => $validatedData['payment_method'],
                'status' => 'pending',
                'customer_name' => $validatedData['customer']['name'],
                'customer_email' => $validatedData['customer']['email'],
                'customer_phone' => $validatedData['customer']['phone'],
                'customer_address' => $validatedData['customer']['address'],
                'customer_ward' => $validatedData['customer']['ward'],
                'customer_district' => $validatedData['customer']['district'],
                'customer_city' => $validatedData['customer']['city'],
                'customer_type' => $validatedData['customer']['type'],
                'customer_note' => $validatedData['customer']['note'] ?? null,
            ]);

            Log::info('✅ Order created in DB:', $order->toArray());

            foreach ($orderItemsData as $item) {
                $order->items()->create($item);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Đặt hàng thành công!',
                'data' => $order->load('items.product')
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            Log::error('❌ Validation error:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Order creation error:', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi xử lý đơn hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Chi tiết đơn hàng
     */
    public function show($id)
    {
        try {
            $order = Order::with('items.product', 'user')->findOrFail($id);

            $order->customer = (object) [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
                'city' => $order->customer_city,
                'district' => $order->customer_district,
                'ward' => $order->customer_ward,
                'address' => $order->customer_address,
                'type' => $order->customer_type,
                'note' => $order->customer_note,
            ];

            // THÊM DÒNG NÀY ĐỂ ĐẢM BẢO coupon_code KHÔNG BỊ NULL
            $order->coupon_code = $order->coupon_code ?: null;
            $order->discount_amount = $order->discount_amount ?: 0;

            return response()->json([
                'success' => true,
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Cập nhật đơn hàng
     */
    public function update(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);

            $validated = $request->validate([
                'customer_name' => 'sometimes|string|max:255',
                'customer_email' => 'sometimes|email|max:255',
                'customer_phone' => 'sometimes|string|regex:/^\d{10,11}$/',
                'customer_address' => 'sometimes|string|max:255',
                'customer_ward' => 'sometimes|string|max:255',
                'customer_district' => 'sometimes|string|max:255',
                'customer_city' => 'sometimes|string|max:255',
                'customer_type' => 'sometimes|string|in:Nhà Riêng,Văn Phòng',
                'customer_note' => 'nullable|string|max:500',

            ]);

            $order->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật đơn hàng thành công',
                'data' => $order->fresh()->load('items.product')
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật trạng thái đơn hàng
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);

            $validated = $request->validate([
                'status' => 'required|string|in:pending,confirmed,shipping,delivered,cancelled'
            ]);

            $oldStatus = $order->status;
            $newStatus = $validated['status'];
            $paymentMethod = $order->payment_method; // Lấy phương thức thanh toán

            // --- LOGIC MỚI: TỰ ĐỘNG ÉP BUỘC TRẠNG THÁI CHO PTTT CHUYỂN KHOẢN (Banking) ---
            $isBankingAndNotCancelled = ($paymentMethod === 'Banking' && $newStatus !== 'cancelled');
            $shouldBeDelivered = $isBankingAndNotCancelled;

            if ($shouldBeDelivered) {
                // Nếu là Banking và KHÔNG phải là Cancelled, ép buộc trạng thái là delivered
                // Điều này ghi đè bất kỳ trạng thái nào khác (pending, confirmed, shipping) thành delivered
                $newStatus = 'delivered';
                // Cập nhật lại giá trị validated['status'] để nó đi tiếp với delivered
                $validated['status'] = 'delivered';
            }
            // -----------------------------------------------------------------------------

            // Validate luồng chuyển trạng thái
            $validTransitions = [
                'pending' => ['confirmed', 'cancelled', 'delivered'], // Thêm 'delivered' để cho phép chuyển trực tiếp (chỉ cho COD/MoMo/VNPay trường hợp đặc biệt)
                'confirmed' => ['shipping', 'cancelled', 'delivered'], // Thêm 'delivered' để cho phép chuyển trực tiếp
                'shipping' => ['delivered', 'cancelled'],
                'delivered' => [],
                'cancelled' => []
            ];

            // --- Bỏ qua kiểm tra chuyển trạng thái nếu đã được Ép buộc thành 'delivered' ---
            // Nếu $oldStatus !== $newStatus (vì có thể $oldStatus đã là delivered), và $oldStatus không phải là 'cancelled'/'delivered' (đã kết thúc),
            // VÀ $newStatus không nằm trong luồng cho phép.

            // Nếu đơn hàng có PTTT là COD/MoMo/VNPay, chúng ta cần kiểm tra luồng.
            // Nếu đơn hàng đã được ép buộc thành 'delivered' ở trên, chúng ta chỉ kiểm tra nếu trạng thái cũ đã kết thúc.

            // Điều kiện kiểm tra luồng chuẩn:
            $isInvalidTransition = !in_array($newStatus, $validTransitions[$oldStatus]);

            // Cho phép chuyển trạng thái nếu:
            // 1. Luồng là hợp lệ theo $validTransitions. HOẶC
            // 2. Đơn hàng là Banking và được ép buộc thành 'delivered', và trạng thái cũ chưa phải là 'delivered' hay 'cancelled'.
            $isAllowedByBanking = $shouldBeDelivered && !in_array($oldStatus, ['delivered', 'cancelled']);

            if ($isInvalidTransition && !$isAllowedByBanking) {
                return response()->json([
                    'success' => false,
                    'message' => "Không thể chuyển trạng thái từ '{$oldStatus}' sang '{$newStatus}'"
                ], 400);
            }

            // Nếu hủy đơn, hoàn trả tồn kho (Luôn giữ lại logic này)
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                DB::beginTransaction();
                foreach ($order->items as $item) {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $product->increment('stock', $item->quantity);
                    }
                }
                $order->update(['status' => $newStatus]);
                DB::commit();
            } else if ($newStatus !== $oldStatus) { // Chỉ cập nhật nếu trạng thái thay đổi
                $order->update(['status' => $newStatus]);
            }


            return response()->json([
                'success' => true,
                'message' => "Đã cập nhật trạng thái đơn hàng thành '{$newStatus}'",
                'data' => $order->fresh()
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật trạng thái',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa đơn hàng (chỉ admin)
     */
    public function destroy($id)
    {
        try {
            $order = Order::findOrFail($id);

            // Chỉ cho phép xóa đơn đã hủy hoặc đã giao
            if (!in_array($order->status, ['cancelled', 'delivered'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể xóa đơn hàng đã hủy hoặc đã giao'
                ], 400);
            }

            $order->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa đơn hàng thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy đơn hàng của user hiện tại
     */
    public function myOrders(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Người dùng chưa đăng nhập.'
                ], 401);
            }

            $orders = Order::with('items.product')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách đơn hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hủy đơn hàng (user)
     */
    public function cancelOrder(Request $request, $id)
    {
        try {
            $user = $request->user();
            $order = Order::findOrFail($id);

            // Kiểm tra quyền sở hữu
            if ($order->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền hủy đơn hàng này.'
                ], 403);
            }

            // Chỉ cho phép hủy đơn hàng đang chờ xử lý hoặc đã xác nhận
            if (!in_array($order->status, ['pending', 'confirmed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể hủy đơn hàng ở trạng thái hiện tại.'
                ], 400);
            }

            DB::beginTransaction();

            // Hoàn trả tồn kho
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->increment('stock', $item->quantity);
                }
            }

            $order->update(['status' => 'cancelled']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Đơn hàng đã được hủy thành công.',
                'data' => $order->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi hủy đơn hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Thống kê đơn hàng
     */
    public function statistics(Request $request)
    {
        try {
            $period = $request->get('period', 'month'); // day, week, month, year

            $baseQuery = Order::query();

            switch ($period) {
                case 'day':
                    $baseQuery->whereDate('created_at', today());
                    break;
                case 'week':
                    $baseQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $baseQuery->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year);
                    break;
                case 'year':
                    $baseQuery->whereYear('created_at', now()->year);
                    break;
            }

            $stats = [
                'total_orders' => (clone $baseQuery)->count(),
                'total_revenue' => (clone $baseQuery)->where('status', 'delivered')->sum('total_price'),
                'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
                'confirmed' => (clone $baseQuery)->where('status', 'confirmed')->count(),
                'shipping' => (clone $baseQuery)->where('status', 'shipping')->count(),
                'delivered' => (clone $baseQuery)->where('status', 'delivered')->count(),
                'cancelled' => (clone $baseQuery)->where('status', 'cancelled')->count(),
                'average_order_value' => (clone $baseQuery)->where('status', 'delivered')->avg('total_price'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period' => $period
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải thống kê',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Xóa nhiều đơn hàng
     */
    public function bulkDelete(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:orders,id'
            ]);

            $count = Order::whereIn('id', $validated['ids'])
                ->whereIn('status', ['cancelled', 'delivered'])
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "Đã xóa {$count} đơn hàng thành công"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa nhiều đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CouponController extends Controller
{
    /**
     * 🧩 ADMIN – Lấy danh sách tất cả coupon
     */
    public function index()
    {
        $coupons = Coupon::latest()->get();

        return response()->json([
            'success' => true,
            'data' => $coupons
        ]);
    }

    /**
     * 🧩 ADMIN – Tạo mới coupon
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|integer|min:0|max:100',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'usage_limit' => 'nullable|integer|min:1'
        ]);

        $coupon = Coupon::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Tạo mã giảm giá thành công',
            'data' => $coupon
        ]);
    }

    /**
     * 🧩 ADMIN – Cập nhật coupon
     */
    public function update(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);

        $request->validate([
            'code' => 'required|string|unique:coupons,code,' . $id,
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|integer|min:0|max:100',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'usage_limit' => 'nullable|integer|min:1'
        ]);

        $coupon->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật mã giảm giá thành công',
            'data' => $coupon
        ]);
    }

    /**
     * 🧩 ADMIN – Xóa coupon
     */
    public function destroy($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa mã giảm giá'
        ]);
    }

    /**
     * 🧠 USER – Áp dụng mã giảm giá ở trang checkout
     */
    public function apply(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        $coupon = Coupon::where('code', $request->code)->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Mã giảm giá không tồn tại'
            ], 404);
        }

        $now = Carbon::now();

        if ($coupon->valid_from && $now->lt(Carbon::parse($coupon->valid_from))) {
            return response()->json([
                'success' => false,
                'message' => 'Mã giảm giá chưa đến thời gian sử dụng'
            ]);
        }

        if ($coupon->valid_to && $now->gt(Carbon::parse($coupon->valid_to))) {
            return response()->json([
                'success' => false,
                'message' => 'Mã giảm giá đã hết hạn'
            ]);
        }

        if ($coupon->usage_limit <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Mã giảm giá đã hết lượt sử dụng'
            ]);
        }

        // ✅ Nếu hợp lệ → trả thông tin giảm giá
        return response()->json([
            'success' => true,
            'message' => 'Áp dụng mã giảm giá thành công',
            'data' => [
                'discount_amount' => $coupon->discount_amount,
                'discount_percent' => $coupon->discount_percent,
            ]
        ]);
    }
}

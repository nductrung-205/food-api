<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    // Lấy danh sách review của 1 sản phẩm
    public function index($productId)
    {
        $reviews = Review::with('user:id,fullname')
            ->where('product_id', $productId)
            ->where('is_visible', true)
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $reviews]);
    }

    public function all()
    {
        $reviews = Review::with(['user:id,fullname', 'product:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    // Tạo mới review
    public function store(Request $request, $productId)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = Review::create([
            'user_id' => Auth::id(),
            'product_id' => $productId,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'success' => true,
            'data' => $review
        ]);
    }

    // Xóa review của user
    public function destroy($id)
    {
        $review = Review::findOrFail($id);

        if ($review->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Không được phép xóa'
            ], 403);
        }

        $review->delete();

        return response()->json([
            'success' => true
        ]);
    }

    // Hiển thị hoặc ẩn review
    public function toggleVisibility($id)
    {
        $review = Review::findOrFail($id);
        $review->is_visible = !$review->is_visible;
        $review->save();

        return response()->json([
            'success' => true,
            'message' => $review->is_visible ? 'Đã ẩn đánh giá' : 'Đã hiển thị lại',
            'data' => $review
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Models\User; // Import User model
use Illuminate\Support\Facades\DB; // Để sử dụng transaction

class NotificationController extends Controller
{
    // Lấy danh sách thông báo của user hiện tại (Dành cho người dùng)
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    // Lấy TẤT CẢ thông báo cho trang quản trị (Dành cho Admin)
    public function allNotifications(Request $request)
    {
        // Có thể thêm filter, search, paginate tại đây
        $notifications = Notification::with('user') // Nạp thông tin user để hiển thị tên
            ->orderByDesc('created_at')
            ->paginate(10); // Phân trang

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    // Tạo thông báo mới (Admin)
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id', // Có thể gửi cho user cụ thể hoặc null (nếu là gửi cho tất cả sau)
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|string',
            'data' => 'nullable|array',
            'is_read' => 'boolean', // Admin có thể set trạng thái đọc ban đầu
        ]);

        $notification = Notification::create([
            'user_id' => $request->user_id, // Có thể là null nếu chưa gán cho user cụ thể
            'title' => $request->title,
            'message' => $request->message,
            'type' => $request->type ?? 'info', // Mặc định là 'info'
            'data' => $request->data ?? [],
            'is_read' => $request->is_read ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification created successfully.',
            'data' => $notification
        ], 201);
    }

    // Cập nhật thông báo (Admin)
    public function update(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);

        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|string',
            'data' => 'nullable|array',
            'is_read' => 'boolean',
        ]);

        $notification->update([
            'user_id' => $request->user_id,
            'title' => $request->title,
            'message' => $request->message,
            'type' => $request->type ?? $notification->type,
            'data' => $request->data ?? $notification->data,
            'is_read' => $request->is_read ?? $notification->is_read,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification updated successfully.',
            'data' => $notification
        ]);
    }

    // Xóa thông báo (Admin)
    public function destroy(Request $request, $id)
    {
        // Ở đây là Admin xóa thông báo của bất kỳ ai
        $notification = Notification::findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.'
        ]);
    }

    // Đánh dấu là đã đọc (Dành cho người dùng)
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $notification->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    // Chuyển đổi trạng thái đọc (Dành cho Admin)
    public function toggleReadStatus(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);
        $notification->update(['is_read' => !$notification->is_read]);

        return response()->json([
            'success' => true,
            'message' => 'Read status toggled successfully.',
            'data' => $notification
        ]);
    }

    // Gửi thông báo đến tất cả người dùng (Dành cho Admin)
    public function sendToAllUsers(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|string',
            'data' => 'nullable|array',
        ]);

        $title = $request->title;
        $message = $request->message;
        $type = $request->type ?? 'promotion'; // Mặc định là khuyến mãi cho thông báo gửi tới tất cả
        $data = $request->data ?? [];

        // Lấy tất cả user IDs
        $userIds = User::pluck('id');

        // Tạo thông báo cho từng người dùng
        // Sử dụng transaction để đảm bảo tất cả hoặc không có thông báo nào được tạo
        DB::beginTransaction();
        try {
            foreach ($userIds as $userId) {
                Notification::create([
                    'user_id' => $userId,
                    'title' => $title,
                    'message' => $message,
                    'type' => $type,
                    'data' => $data,
                    'is_read' => false,
                ]);
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Notifications sent to all users successfully.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notifications to all users.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}